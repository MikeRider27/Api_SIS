<?php
// Función para enviar el JSON al servidor FHIR
function crearRDA($rdaData)
{
    // Define la URL del servidor FHIR
    $fhirUrl = APP_FHIR_SERVER; // Ajusta el endpoint según tu recurso específico
   
    // Configurar la solicitud cURL
    $ch = curl_init($fhirUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_POSTFIELDS => $rdaData,
        CURLOPT_TIMEOUT => 30, // evita bloqueos si el servidor no responde
    ]);

    $response = curl_exec($ch);

    // Validar errores de cURL
    if ($response === false) {
        $errorMsg = 'Error al enviar los datos al servidor FHIR: ' . curl_error($ch);
        curl_close($ch);
        return ['error' => $errorMsg];
    }

    curl_close($ch);
    return $response;
}

/**
 * Obtiene un DocumentReference FHIR y retorna solo los datos simplificados
 * 
 * @param string $documento Número de documento del paciente
 * @return array Respuesta simplificada con los datos esenciales
 */
function obtenerRDA($documento) {
    try {
        // Validar entrada
        if (empty($documento) || !is_string($documento)) {
            return [
                'error' => true,
                'message' => 'Documento inválido',
                'data' => []
            ];
        }

        // 1. Obtener DocumentReference
        $fhirUrl = APP_FHIR_SERVER . "/DocumentReference/?patient.identifier=$documento&_format=json&status=current";
        $data = fetchPatientData($fhirUrl);
        
        if (!$data) {
            return [
                'error' => true,
                'message' => 'No se encontraron documentos para el paciente',
                'data' => []
            ];
        }

        $bundle = json_decode($data, true);
        
        // 2. Enriquecer el bundle con las organizaciones
        $bundle = enrichBundleWithOrganizations($bundle);
        
        // 3. Procesar y simplificar los datos
        $simplifiedData = simplifyBundle($bundle);
        
        return [
            'error' => false,
            'message' => 'RDA obtenido exitosamente',
            'total' => count($simplifiedData),
            'data' => $simplifiedData
        ];
        
    } catch (Exception $e) {
        return [
            'error' => true,
            'message' => 'Error al procesar la solicitud: ' . $e->getMessage(),
            'data' => []
        ];
    }
}

/**
 * Enriquece el bundle con las organizaciones faltantes
 * 
 * @param array $bundle Bundle FHIR
 * @return array Bundle enriquecido con organizaciones
 */
function enrichBundleWithOrganizations($bundle) {
    if (!isset($bundle['entry'])) {
        return $bundle;
    }
    
    // IDs de organizaciones que ya tenemos
    $existingOrgIds = [];
    foreach ($bundle['entry'] as $entry) {
        if (isset($entry['resource']['resourceType']) && 
            $entry['resource']['resourceType'] === 'Organization') {
            $existingOrgIds[] = $entry['resource']['id'];
        }
    }
    
    // Buscar organizaciones faltantes
    foreach ($bundle['entry'] as $entry) {
        if (isset($entry['resource']['resourceType']) && 
            $entry['resource']['resourceType'] === 'DocumentReference') {
            
            // Verificar si tiene custodian
            if (isset($entry['resource']['custodian']['reference'])) {
                $custodianRef = $entry['resource']['custodian']['reference'];
                $orgId = explode('/', $custodianRef)[1] ?? null;
                
                // Si la organización no está en el bundle, la buscamos
                if ($orgId && !in_array($orgId, $existingOrgIds)) {
                    $orgUrl = APP_FHIR_SERVER . "/Organization/$orgId";
                    $orgData = fetchPatientData($orgUrl);
                    
                    if ($orgData) {
                        $orgResource = json_decode($orgData, true);
                        if (isset($orgResource['resourceType']) && 
                            $orgResource['resourceType'] === 'Organization') {
                            
                            // Agregar organización al bundle
                            $bundle['entry'][] = [
                                "fullUrl" => $orgUrl,
                                "resource" => $orgResource
                            ];
                            $existingOrgIds[] = $orgId;
                        }
                    }
                }
            }
        }
    }
    
    return $bundle;
}

/**
 * Simplifica el bundle FHIR extrayendo solo la información relevante
 * 
 * @param array $bundle Bundle FHIR completo
 * @return array Datos simplificados
 */
function simplifyBundle($bundle) {
    $simplified = [];
    
    if (!isset($bundle['entry'])) {
        return $simplified;
    }
    
    // Extraer organizaciones del bundle (para mapear IDs con nombres)
    $organizations = [];
    foreach ($bundle['entry'] as $entry) {
        if (isset($entry['resource']['resourceType']) && 
            $entry['resource']['resourceType'] === 'Organization') {
            $orgId = $entry['resource']['id'] ?? null;
            if ($orgId) {
                $organizations[$orgId] = [
                    'organizacion_id' => $orgId,
                    'organizacion_name' => $entry['resource']['name'] ?? $entry['resource']['identifier'][0]['value'] ?? 'Sin nombre'
                ];
            }
        }
    }
    
    // Procesar cada DocumentReference
    foreach ($bundle['entry'] as $entry) {
        if (!isset($entry['resource']['resourceType']) || 
            $entry['resource']['resourceType'] !== 'DocumentReference') {
            continue;
        }
        
        $resource = $entry['resource'];
        
        // Extraer ID del DocumentReference
        $docId = $resource['id'] ?? null;
        
        // Extraer información de la organización (formato plano)
        $organizacion_id = null;
        $organizacion_name = null;
        
        if (isset($resource['custodian']['reference'])) {
            $custodianRef = $resource['custodian']['reference'];
            // Extraer ID de la referencia (ej: "Organization/0005000.00010102" -> "0005000.00010102")
            $orgId = explode('/', $custodianRef)[1] ?? null;
            if ($orgId && isset($organizations[$orgId])) {
                $organizacion_id = $organizations[$orgId]['organizacion_id'];
                $organizacion_name = $organizations[$orgId]['organizacion_name'];
            } elseif ($orgId) {
                // Si no encontramos la organización, al menos mostrar el ID
                $organizacion_id = $orgId;
                $organizacion_name = 'No disponible';
            }
        }
        
        // Extraer fecha
        $date = $resource['date'] ?? null;
        if ($date) {
            // Formatear fecha para mejor lectura
            $date = date('Y-m-d H:i:s', strtotime($date));
        }
        
        // Extraer Bundle ID del content
        $bundleId = null;
        if (isset($resource['content'][0]['attachment']['url'])) {
            $bundleUrl = $resource['content'][0]['attachment']['url'];
            $bundleId = explode('/', $bundleUrl)[1] ?? null;
        }
        
        // Extraer Patient ID
        $patientId = null;
        if (isset($resource['subject']['reference'])) {
            $patientRef = $resource['subject']['reference'];
            $patientId = explode('/', $patientRef)[1] ?? null;
        }
        
        // Extraer tipo de documento
        $docType = null;
        if (isset($resource['type']['coding'][0]['display'])) {
            $docType = $resource['type']['coding'][0]['display'];
        } elseif (isset($resource['type']['coding'][0]['code'])) {
            $docType = $resource['type']['coding'][0]['code'];
        }
        
        // Solo agregar si tenemos al menos el ID del documento
        if ($docId) {
            $item = [
                'documento_id' => $docId,
                'paciente_id' => $patientId,
                'fecha' => $date,
                'bundle_id' => $bundleId,
                'tipo' => $docType,
                'estado' => $resource['status'] ?? null
            ];
            
            // Agregar organización si existe
            if ($organizacion_id) {
                $item['organizacion_id'] = $organizacion_id;
                $item['organizacion_name'] = $organizacion_name;
            }
            
            $simplified[] = array_filter($item);
        }
    }
    
    return $simplified;
}

/**
 * Obtiene un Bundle FHIR y lo simplifica al formato requerido
 * 
 * @param string $id ID del Bundle
 * @return array Respuesta simplificada con los datos estructurados
 */
function obtenerBundleRDA($id) {
    try {
        // 1. Obtener Bundle completo desde el servidor FHIR
        $fhirUrl = APP_FHIR_SERVER . "/Bundle/$id?_format=json";
        $data = fetchPatientData($fhirUrl);
        
        if (!$data) {
            return [
                'error' => true,
                'message' => 'No se encontró el RDA con el ID proporcionado',
                'data' => []
            ];
        }
        
        $bundle = json_decode($data, true);
        
        // 2. Simplificar los datos
        $simplifiedData = simplifyBundleRDA($bundle);
        
        return [
            'error' => false,
            'message' => 'RDA obtenido exitosamente',
            'data' => $simplifiedData,
            'fhirData' => $bundle // JSON original del FHIR
        ];
        
    } catch (Exception $e) {
        return [
            'error' => true,
            'message' => 'Error al procesar la solicitud: ' . $e->getMessage(),
            'data' => [],
            'fhirData' => null
        ];
    }
}

/**
 * Simplifica el Bundle RDA extrayendo solo la información relevante
 * 
 * @param array $bundle Bundle FHIR completo
 * @return array Datos simplificados y estructurados
 */
function simplifyBundleRDA($bundle) {
    $result = [];
    
    if (!isset($bundle['entry'])) {
        return $result;
    }
    
    // Extraer recursos por tipo
    $resources = [
        'Patient' => null,
        'Practitioner' => null,
        'Organization' => null,
        'Condition' => [],
        'MedicationStatement' => [],
        'AllergyIntolerance' => []
    ];
    
    foreach ($bundle['entry'] as $entry) {
        $resource = $entry['resource'] ?? [];
        $resourceType = $resource['resourceType'] ?? '';
        
        switch ($resourceType) {
            case 'Patient':
                $resources['Patient'] = $resource;
                break;
            case 'Practitioner':
                $resources['Practitioner'] = $resource;
                break;
            case 'Organization':
                $resources['Organization'] = $resource;
                break;
            case 'Condition':
                $resources['Condition'][] = $resource;
                break;
            case 'MedicationStatement':
                $resources['MedicationStatement'][] = $resource;
                break;
            case 'AllergyIntolerance':
                $resources['AllergyIntolerance'][] = $resource;
                break;
        }
    }
    
    // 1. Procesar Paciente
    if ($resources['Patient']) {
        $patient = $resources['Patient'];
        $name = $patient['name'][0] ?? [];
        
        // Obtener tipo de documento e identificación
        $identifier = $patient['identifier'][0] ?? [];
        $tipoDocumento = $identifier['type']['coding'][0]['code'] ?? null;
        $documento = $identifier['value'] ?? null;
        
        // Separar primer y segundo apellido si hay espacio
        $fullFamily = $name['family'] ?? null;
        $primerApellido = $fullFamily;
        $segundoApellido = null;
        
        if ($fullFamily && strpos($fullFamily, ' ') !== false) {
            $apellidos = explode(' ', $fullFamily, 2);
            $primerApellido = $apellidos[0];
            $segundoApellido = $apellidos[1] ?? null;
        }
        
        $result['paciente'] = array_filter([
            'tipo_documento' => $tipoDocumento,
            'documento' => $documento,
            'primer_nombre' => $name['given'][0] ?? null,
            'segundo_nombre' => $name['given'][1] ?? null,
            'primer_apellido' => $primerApellido,
            'segundo_apellido' => $segundoApellido,
            'fecha_nacimiento' => $patient['birthDate'] ?? null,
            'sexo' => mapGender($patient['gender'] ?? null)
        ]);
    }
    
    // 2. Procesar Profesional
    if ($resources['Practitioner']) {
        $practitioner = $resources['Practitioner'];
        $name = $practitioner['name'][0] ?? [];
        $identifier = $practitioner['identifier'][0] ?? [];
        
        // Separar primer y segundo apellido si hay espacio
        $fullFamily = $name['family'] ?? null;
        $primerApellido = $fullFamily;
        $segundoApellido = null;
        
        if ($fullFamily && strpos($fullFamily, ' ') !== false) {
            $apellidos = explode(' ', $fullFamily, 2);
            $primerApellido = $apellidos[0];
            $segundoApellido = $apellidos[1] ?? null;
        }
        
        $result['profesional'] = array_filter([
            'documento' => $identifier['value'] ?? null,
            'primer_nombre' => $name['given'][0] ?? null,
            'segundo_nombre' => $name['given'][1] ?? null,
            'primer_apellido' => $primerApellido,
            'segundo_apellido' => $segundoApellido
        ]);
    }
    
    // 3. Procesar Organización
    if ($resources['Organization']) {
        $organization = $resources['Organization'];
        $identifier = $organization['identifier'][0] ?? [];
        $type = $organization['type'][0] ?? [];
        
        $result['organizacion'] = array_filter([
            'codigo' => $identifier['value'] ?? $organization['id'] ?? null,
            'tipo' => $type['text'] ?? null,
            'nombre' => $organization['name'] ?? null
        ]);
    }
    
    // 4. Procesar Diagnósticos (Condition)
    foreach ($resources['Condition'] as $condition) {
        $code = $condition['code']['coding'][0] ?? [];
        $verificationStatus = $condition['verificationStatus']['coding'][0] ?? [];
        
        $diagnostico = [
            'cie10_code' => $code['code'] ?? null,
            'cie10_term' => $code['display'] ?? $condition['code']['text'] ?? null,
            'diagnostico_fecha' => isset($condition['onsetPeriod']['start']) 
                ? date('Y-m-d', strtotime($condition['onsetPeriod']['start']))
                : null,
            'status' => $verificationStatus['code'] ?? null,
            'nota' => $condition['note'][0]['text'] ?? null
        ];
        
        $result['diagnosticos'][] = array_filter($diagnostico);
    }
    
    // 5. Procesar Medicamentos (MedicationStatement)
    foreach ($resources['MedicationStatement'] as $medication) {
        $medicamento = [
            'medicamento_term' => $medication['medicationCodeableConcept']['text'] ?? null,
            'medicamento_fecha' => isset($medication['effectiveDateTime'])
                ? date('Y-m-d', strtotime($medication['effectiveDateTime']))
                : null,
            'medicamento_dosis' => $medication['dosage'][0]['text'] ?? null,
            'medicamento_via' => $medication['dosage'][0]['route']['text'] ?? null
        ];
        
        $result['medicamentos'][] = array_filter($medicamento);
    }
    
    // 6. Procesar Alergias (AllergyIntolerance)
    foreach ($resources['AllergyIntolerance'] as $allergy) {
        $categoria = $allergy['category'][0] ?? null;
        $categoriaMap = [
            'medication' => 'medicamento',
            'food' => 'comida',
            'environment' => 'ambiente',
            'biologic' => 'biológico'
        ];
        
        $alergia = [
            'alergia_term' => $allergy['code']['text'] ?? null,
            'categoria' => $categoriaMap[$categoria] ?? $categoria
        ];
        
        $result['alergias'][] = array_filter($alergia);
    }
    
    // Inicializar arrays vacíos si no existen
    $result['diagnosticos'] = $result['diagnosticos'] ?? [];
    $result['medicamentos'] = $result['medicamentos'] ?? [];
    $result['alergias'] = $result['alergias'] ?? [];
    
    return $result;
}

/**
 * Mapea el género de FHIR al formato requerido
 * 
 * @param string|null $gender Género en FHIR (male, female, other, unknown)
 * @return string|null Género en español
 */
function mapGender($gender) {
    $map = [
        'male' => 'masculino',
        'female' => 'femenino',
        'other' => 'otro',
        'unknown' => 'desconocido'
    ];
    
    return $map[$gender] ?? $gender;
}





/**
 * Realiza peticiones HTTP usando cURL
 * 
 * @param string $url URL a consultar
 * @return string|false Respuesta HTTP o false en caso de error
 */
function fetchPatientData($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        error_log("URL inválida: $url");
        return false;
    }
    
    $ch = curl_init();
    
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/fhir+json',
            'User-Agent: FHIR-Client/1.0'
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ];
    
    curl_setopt_array($ch, $options);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($httpCode >= 400 || empty($response)) {
        error_log("Error HTTP $httpCode al consultar $url: $error");
        return false;
    }
    
    return $response;
}