<?php

/**
 * Busca un paciente por número de cédula en el servidor FHIR
 * 
 * @param string $cedula Número de cédula a buscar
 * @param array $options Opciones de búsqueda
 * @return array Resultado de la búsqueda
 */
function buscarPacientePorCedula($cedula, $options = []) {
    // Configuración por defecto
    $defaults = [
        'baseUrl' => APP_FHIR_SERVER,
        'onDuplicate' => 'first', // 'first', 'newest', 'oldest', 'throw', 'error'
        'timeout' => 30,
        'verifySsl' => true
    ];
    
    $options = array_merge($defaults, $options);
    $url = $options['baseUrl'] . '/Patient?identifier=' . urlencode($cedula);
    
    // Inicializar cURL
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $options['timeout'],
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => $options['verifySsl']
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Manejar errores de conexión
    if ($response === false) {
        return [
            'success' => false,
            'error' => 'Error de conexión: ' . $curlError,
            'found' => false,
            'patient' => null,
            'duplicates' => []
        ];
    }
    
    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => "Error HTTP: $httpCode",
            'found' => false,
            'patient' => null,
            'duplicates' => []
        ];
    }
    
    $bundle = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => 'Error al parsear JSON: ' . json_last_error_msg(),
            'found' => false,
            'patient' => null,
            'duplicates' => []
        ];
    }
    
    $total = $bundle['total'] ?? 0;
    $entries = $bundle['entry'] ?? [];
    $patients = [];
    
    // Extraer los pacientes del bundle
    foreach ($entries as $entry) {
        if (isset($entry['resource']) && $entry['resource']['resourceType'] === 'Patient') {
            $patients[] = $entry['resource'];
        }
    }
    
    $count = count($patients);
    
    // Caso 1: No se encontró ningún paciente
    if ($count === 0) {
        return [
            'success' => true,
            'found' => false,
            'patient' => null,
            'duplicates' => [],
            'message' => "No se encontró ningún paciente con cédula: $cedula"
        ];
    }
    
    // Caso 2: Se encontró exactamente un paciente
    if ($count === 1) {
        return [
            'success' => true,
            'found' => true,
            'patient' => $patients[0],
            'duplicates' => [],
            'message' => 'Paciente encontrado'
        ];
    }
    
    // Caso 3: Múltiples pacientes (duplicados)
    $selectedPatient = null;
    $errorMessage = null;
    
    switch ($options['onDuplicate']) {
        case 'first':
            $selectedPatient = $patients[0];
            $message = "Se encontraron $count pacientes duplicados. Usando el primero.";
            break;
            
        case 'newest':
            // Ordenar por lastUpdated (más reciente primero)
            usort($patients, function($a, $b) {
                $dateA = $a['meta']['lastUpdated'] ?? '1970-01-01';
                $dateB = $b['meta']['lastUpdated'] ?? '1970-01-01';
                return strcmp($dateB, $dateA);
            });
            $selectedPatient = $patients[0];
            $message = "Se encontraron $count pacientes duplicados. Usando el más reciente.";
            break;
            
        case 'oldest':
            // Ordenar por lastUpdated (más antiguo primero)
            usort($patients, function($a, $b) {
                $dateA = $a['meta']['lastUpdated'] ?? '1970-01-01';
                $dateB = $b['meta']['lastUpdated'] ?? '1970-01-01';
                return strcmp($dateA, $dateB);
            });
            $selectedPatient = $patients[0];
            $message = "Se encontraron $count pacientes duplicados. Usando el más antiguo.";
            break;
            
        case 'throw':
        case 'error':
            return [
                'success' => false,
                'error' => "Se encontraron $count pacientes duplicados para la cédula: $cedula",
                'found' => true,
                'patient' => null,
                'duplicates' => $patients,
                'message' => 'Múltiples pacientes encontrados'
            ];
            
        default:
            // Si es una función callback
            if (is_callable($options['onDuplicate'])) {
                $selectedPatient = call_user_func($options['onDuplicate'], $patients);
                $message = "Se encontraron $count pacientes duplicados. Se aplicó función personalizada.";
            } else {
                $selectedPatient = $patients[0];
                $message = "Se encontraron $count pacientes duplicados. Usando el primero por defecto.";
            }
            break;
    }
    
    return [
        'success' => true,
        'found' => true,
        'patient' => $selectedPatient,
        'duplicates' => $patients,
        'duplicateCount' => $count,
        'message' => $message
    ];
}

// Función auxiliar para extraer datos específicos del paciente
function extraerDatosPaciente($patient) {
    if (!$patient) {
        return null;
    }
    
    $name = $patient['name'][0] ?? [];
    $identifier = $patient['identifier'][0] ?? [];
    
    return [
        'id' => $patient['id'] ?? null,
        'cedula' => $identifier['value'] ?? null,
        'nombres' => implode(' ', $name['given'] ?? []),
        'apellidos' => $name['family'] ?? '',
        'nombre_completo' => trim(($name['family'] ?? '') . ' ' . implode(' ', $name['given'] ?? [])),
        'genero' => $patient['gender'] ?? null,
        'fecha_nacimiento' => $patient['birthDate'] ?? null,
        'lastUpdated' => $patient['meta']['lastUpdated'] ?? null,
        'versionId' => $patient['meta']['versionId'] ?? null
    ];
}

// Función para crear un nuevo paciente (si no existe o quieres forzar creación)
function crearPaciente($pacienteData, $options = []) {
    $defaults = [
        'baseUrl' => APP_FHIR_SERVER,
        'timeout' => 30,
        'verifySsl' => true
    ];
    
    $options = array_merge($defaults, $options);
    $url = $options['baseUrl'] . '/Patient';
    
    $jsonData = json_encode($pacienteData);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $options['timeout'],
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonData       
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201 || $httpCode === 200) {
        return [
            'success' => true,
            'patient' => json_decode($response, true),
            'message' => 'Paciente creado exitosamente'
        ];
    }
    
    return [
        'success' => false,
        'error' => "Error HTTP: $httpCode",
        'response' => $response
    ];
}

