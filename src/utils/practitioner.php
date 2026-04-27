<?php

/**
 * Busca un practitioner por número de cédula en el servidor FHIR
 * 
 * @param string $cedula Número de cédula a buscar
 * @param array $options Opciones de búsqueda
 * @return array Resultado de la búsqueda
 */
function buscarPractitionerPorCedula($cedula, $options = []) {
    // Configuración por defecto
    $defaults = [
        'baseUrl' => APP_FHIR_SERVER,
        'onDuplicate' => 'first', // 'first', 'newest', 'oldest', 'throw', 'error'
        'timeout' => 30,
        'verifySsl' => true
    ];
    
    $options = array_merge($defaults, $options);
    $url = $options['baseUrl'] . '/Practitioner?identifier=' . urlencode($cedula);
    
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
            'practitioner' => null,
            'duplicates' => []
        ];
    }
    
    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => "Error HTTP: $httpCode",
            'found' => false,
            'practitioner' => null,
            'duplicates' => []
        ];
    }
    
    $bundle = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => 'Error al parsear JSON: ' . json_last_error_msg(),
            'found' => false,
            'practitioner' => null,
            'duplicates' => []
        ];
    }
    
    $total = $bundle['total'] ?? 0;
    $entries = $bundle['entry'] ?? [];
    $practitioners = [];
    
    // Extraer los practitioners del bundle
    foreach ($entries as $entry) {
        if (isset($entry['resource']) && $entry['resource']['resourceType'] === 'Practitioner') {
            $practitioners[] = $entry['resource'];
        }
    }
    
    $count = count($practitioners);
    
    // Caso 1: No se encontró ningún practitioner
    if ($count === 0) {
        return [
            'success' => true,
            'found' => false,
            'practitioner' => null,
            'duplicates' => [],
            'message' => "No se encontró ningún practitioner con cédula: $cedula"
        ];
    }
    
    // Caso 2: Se encontró exactamente un practitioner
    if ($count === 1) {
        return [
            'success' => true,
            'found' => true,
            'practitioner' => $practitioners[0],
            'duplicates' => [],
            'message' => 'Practitioner encontrado'
        ];
    }
    
    // Caso 3: Múltiples practitioners (duplicados)
    $selectedPractitioner = null;
    
    switch ($options['onDuplicate']) {
        case 'first':
            $selectedPractitioner = $practitioners[0];
            $message = "Se encontraron $count practitioners duplicados. Usando el primero.";
            break;
            
        case 'newest':
            // Ordenar por lastUpdated (más reciente primero)
            usort($practitioners, function($a, $b) {
                $dateA = $a['meta']['lastUpdated'] ?? '1970-01-01';
                $dateB = $b['meta']['lastUpdated'] ?? '1970-01-01';
                return strcmp($dateB, $dateA);
            });
            $selectedPractitioner = $practitioners[0];
            $message = "Se encontraron $count practitioners duplicados. Usando el más reciente.";
            break;
            
        case 'oldest':
            // Ordenar por lastUpdated (más antiguo primero)
            usort($practitioners, function($a, $b) {
                $dateA = $a['meta']['lastUpdated'] ?? '1970-01-01';
                $dateB = $b['meta']['lastUpdated'] ?? '1970-01-01';
                return strcmp($dateA, $dateB);
            });
            $selectedPractitioner = $practitioners[0];
            $message = "Se encontraron $count practitioners duplicados. Usando el más antiguo.";
            break;
            
        case 'throw':
        case 'error':
            return [
                'success' => false,
                'error' => "Se encontraron $count practitioners duplicados para la cédula: $cedula",
                'found' => true,
                'practitioner' => null,
                'duplicates' => $practitioners,
                'message' => 'Múltiples practitioners encontrados'
            ];
            
        default:
            // Si es una función callback
            if (is_callable($options['onDuplicate'])) {
                $selectedPractitioner = call_user_func($options['onDuplicate'], $practitioners);
                $message = "Se encontraron $count practitioners duplicados. Se aplicó función personalizada.";
            } else {
                $selectedPractitioner = $practitioners[0];
                $message = "Se encontraron $count practitioners duplicados. Usando el primero por defecto.";
            }
            break;
    }
    
    return [
        'success' => true,
        'found' => true,
        'practitioner' => $selectedPractitioner,
        'duplicates' => $practitioners,
        'duplicateCount' => $count,
        'message' => $message
    ];
}

// Función auxiliar para extraer datos específicos del practitioner encontrado
function extraerDatosPractitioner($practitioner) {
    if (!$practitioner) {
        return null;
    }
    
    $name = $practitioner['name'][0] ?? [];
    $identifier = $practitioner['identifier'][0] ?? [];
    
    // Determinar el tipo de identificación
    $identifierType = '';
    if (isset($identifier['type']['coding'][0])) {
        $coding = $identifier['type']['coding'][0];
        $identifierType = $coding['display'] ?? $coding['code'] ?? '';
    }
    
    return [
        'id' => $practitioner['id'] ?? null,
        'cedula' => $identifier['value'] ?? null,
        'tipo_identificacion' => $identifierType,
        'nombres' => implode(' ', $name['given'] ?? []),
        'apellidos' => $name['family'] ?? '',
        'nombre_completo' => trim(($name['family'] ?? '') . ' ' . implode(' ', $name['given'] ?? [])),
        'lastUpdated' => $practitioner['meta']['lastUpdated'] ?? null,
        'versionId' => $practitioner['meta']['versionId'] ?? null
    ];
}


// Función para crear un nuevo practitioner (si no existe o quieres forzar creación)
function crearPractitioner($practitionerData, $options = []) {
    $defaults = [
        'baseUrl' => APP_FHIR_SERVER,
        'timeout' => 30,
        'verifySsl' => true
    ];
    
    $options = array_merge($defaults, $options);
   
    // Extraer identificadores del profesional
    $identifiers = $practitionerData['identifier'] ?? [];
    if (empty($identifiers)) {
        return [
            'success' => false,
            'error' => 'El profesional no tiene identifier.'
        ];
    }
    
    // Buscar cédula
    $targetIdentifier = null;
    foreach ($identifiers as $identifier) {
        $typeCode = $identifier['type']['coding'][0]['code'] ?? null;
        if ($typeCode === '01') {
            $targetIdentifier = $identifier;
            break;
        }
    }
    
    if (!$targetIdentifier) {
        return [
            'success' => false,
            'error' => 'No se encontró identifier con code "01"'
        ];
    }
    
    $cedulaValue = $targetIdentifier['value'];
    $debugInfo = [];
    
    // ========== PROBAR DIFERENTES ESTRATEGIAS DE BÚSQUEDA ==========
    
    $existingPractitionerId = null;
    $allDuplicates = [];
    
    // ESTRATEGIA 1: Búsqueda por código|valor
    $searchUrl1 = $options['baseUrl'] . '/Practitioner?identifier=' . urlencode('01|' . $cedulaValue);
    $debugInfo[] = "🔍 Estrategia 1: " . $searchUrl1;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $searchUrl1,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $options['timeout'],
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);
    
    $response1 = curl_exec($ch);
    $httpCode1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $debugInfo[] = "HTTP Code: $httpCode1";
    if ($httpCode1 === 200) {
        $bundle1 = json_decode($response1, true);
        $total1 = $bundle1['total'] ?? count($bundle1['entry'] ?? []);
        $debugInfo[] = "Encontrados: $total1";
        
        if ($total1 > 0) {
            $entries = $bundle1['entry'] ?? [];
            foreach ($entries as $entry) {
                $practitioner = $entry['resource'] ?? null;
                if ($practitioner) {
                    $allDuplicates[] = $practitioner;
                    $debugInfo[] = "  - ID: " . ($practitioner['id'] ?? 'N/A');
                }
            }
        }
    }
    
    // ESTRATEGIA 2: Si no encontró, buscar solo por valor
    if (empty($allDuplicates)) {
        $searchUrl2 = $options['baseUrl'] . '/Practitioner?identifier=' . urlencode($cedulaValue);
        $debugInfo[] = "🔍 Estrategia 2: " . $searchUrl2;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $searchUrl2,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $options['timeout'],
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);
        
        $response2 = curl_exec($ch);
        $httpCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $debugInfo[] = "HTTP Code: $httpCode2";
        if ($httpCode2 === 200) {
            $bundle2 = json_decode($response2, true);
            $total2 = $bundle2['total'] ?? count($bundle2['entry'] ?? []);
            $debugInfo[] = "Encontrados: $total2";
            
            if ($total2 > 0) {
                $entries = $bundle2['entry'] ?? [];
                foreach ($entries as $entry) {
                    $practitioner = $entry['resource'] ?? null;
                    if ($practitioner) {
                        $allDuplicates[] = $practitioner;
                        $debugInfo[] = "  - ID: " . ($practitioner['id'] ?? 'N/A');
                    }
                }
            }
        }
    }
    
    // ESTRATEGIA 3: Buscar por identifier con system completo
    $system = $targetIdentifier['type']['coding'][0]['system'] ?? '';
    if (empty($allDuplicates) && $system) {
        $searchUrl3 = $options['baseUrl'] . '/Practitioner?identifier=' . urlencode($system . '|01|' . $cedulaValue);
        $debugInfo[] = "🔍 Estrategia 3: " . $searchUrl3;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $searchUrl3,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $options['timeout'],
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);
        
        $response3 = curl_exec($ch);
        $httpCode3 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $debugInfo[] = "HTTP Code: $httpCode3";
        if ($httpCode3 === 200) {
            $bundle3 = json_decode($response3, true);
            $total3 = $bundle3['total'] ?? count($bundle3['entry'] ?? []);
            $debugInfo[] = "Encontrados: $total3";
            
            if ($total3 > 0) {
                $entries = $bundle3['entry'] ?? [];
                foreach ($entries as $entry) {
                    $practitioner = $entry['resource'] ?? null;
                    if ($practitioner) {
                        $allDuplicates[] = $practitioner;
                        $debugInfo[] = "  - ID: " . ($practitioner['id'] ?? 'N/A');
                    }
                }
            }
        }
    }
    
    // ========== DECIDIR QUÉ ID USAR ==========
    
    if (!empty($allDuplicates)) {
        // Tomar el PRIMER profesional encontrado
        $existingPractitionerId = $allDuplicates[0]['id'];
        $debugInfo[] = "✅ Encontrado profesional existente con ID: $existingPractitionerId";
        $debugInfo[] = "⚠️ IMPORTANTE: Se usaré ESTE ID para ACTUALIZAR";
        $debugInfo[] = "⚠️ El ID del JSON (" . ($practitionerData['id'] ?? 'ninguno') . ") será IGNORADO";
        
        // FORZAR el uso del ID existente
        $practitionerData['id'] = $existingPractitionerId;
    } else {
        $debugInfo[] = "❌ No se encontró profesional existente";
        $debugInfo[] = "🆕 Se CREARÁ nuevo profesional";
        
        // Si no tiene ID, generar uno
        if (empty($practitionerData['id'])) {
            $practitionerData['id'] = uniqid();
            $debugInfo[] = "ID generado: " . $practitionerData['id'];
        }
    }
    
    // ========== HACER PUT ==========
    
    $url = $options['baseUrl'] . '/Practitioner/' . $practitionerData['id'];
    $jsonData = json_encode($practitionerData);
    $debugInfo[] = "🚀 HACIENDO PUT a: $url";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $options['timeout'],
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => $jsonData
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return [
            'success' => false,
            'error' => "CURL Error: $curlError",
            'debug' => $debugInfo
        ];
    }
    
    // ========== RESULTADO ==========
    
    if ($httpCode === 200 || $httpCode === 201) {
        $resultPractitioner = json_decode($response, true);
        
        return $resultPractitioner;
    } else {
        return [
            'success' => false,
            'error' => "Error HTTP $httpCode",
            'response' => $response,
            'debug' => $debugInfo
        ];
    }
}

?>