<?php

function crearOrganizacion($organizationData, $options = []) {   
    $defaults = [
        'baseUrl' => APP_FHIR_SERVER,
        'timeout' => 30,
        'verifySsl' => true,
        'debug' => true
    ];
    
    $options = array_merge($defaults, $options);
    
    // Extraer identificadores de la organización
    $identifiers = $organizationData['identifier'] ?? [];
    if (empty($identifiers)) {
        return [
            'success' => false,
            'error' => 'La organización no tiene identifier.'
        ];
    }
    
    // Obtener el identifier value
    $identifierValue = null;
    foreach ($identifiers as $identifier) {
        if (isset($identifier['value'])) {
            $identifierValue = $identifier['value'];
            break;
        }
    }
    
    if (!$identifierValue) {
        return [
            'success' => false,
            'error' => 'No se encontró identifier value en la organización'
        ];
    }
    
    // Obtener el type text
    $types = $organizationData['type'] ?? [];
    $typeText = null;
    foreach ($types as $type) {
        if (isset($type['text'])) {
            $typeText = $type['text'];
            break;
        }
    }
    
    if (!$typeText) {
        return [
            'success' => false,
            'error' => 'No se encontró type text en la organización'
        ];
    }
    
    $debugInfo = [];
    
    // ========== PROBAR DIFERENTES ESTRATEGIAS DE BÚSQUEDA ==========
    
    $existingOrganizationId = null;
    $allDuplicates = [];
    
    // ESTRATEGIA 1: Búsqueda por identifier + type (usando _and o búsqueda combinada)
    // Formato: /Organization?identifier=valor&type=text
    $searchUrl1 = $options['baseUrl'] . '/Organization?identifier=' . urlencode($identifierValue) . '&type=' . urlencode($typeText);
    $debugInfo[] = "🔍 Estrategia 1 (identifier + type): " . $searchUrl1;
    
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
                $organization = $entry['resource'] ?? null;
                if ($organization) {
                    // Validar que coincida tanto identifier como type
                    $orgIdentifier = $organization['identifier'][0]['value'] ?? null;
                    $orgType = $organization['type'][0]['text'] ?? null;
                    
                    if ($orgIdentifier === $identifierValue && $orgType === $typeText) {
                        $allDuplicates[] = $organization;
                        $debugInfo[] = "  - ID: " . ($organization['id'] ?? 'N/A') . " (coincide exactamente)";
                    }
                }
            }
        }
    }
    
    // ESTRATEGIA 2: Buscar solo por identifier y luego filtrar por type
    if (empty($allDuplicates)) {
        $searchUrl2 = $options['baseUrl'] . '/Organization?identifier=' . urlencode($identifierValue);
        $debugInfo[] = "🔍 Estrategia 2 (solo identifier): " . $searchUrl2;
        
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
                    $organization = $entry['resource'] ?? null;
                    if ($organization) {
                        $orgIdentifier = $organization['identifier'][0]['value'] ?? null;
                        $orgType = $organization['type'][0]['text'] ?? null;
                        
                        // Filtrar por type
                        if ($orgIdentifier === $identifierValue && $orgType === $typeText) {
                            $allDuplicates[] = $organization;
                            $debugInfo[] = "  - ID: " . ($organization['id'] ?? 'N/A') . " (coincide identifier y type)";
                        }
                    }
                }
            }
        }
    }
    
    // ESTRATEGIA 3: Buscar solo por type y luego filtrar por identifier
    if (empty($allDuplicates)) {
        $searchUrl3 = $options['baseUrl'] . '/Organization?type=' . urlencode($typeText);
        $debugInfo[] = "🔍 Estrategia 3 (solo type): " . $searchUrl3;
        
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
                    $organization = $entry['resource'] ?? null;
                    if ($organization) {
                        $orgIdentifier = $organization['identifier'][0]['value'] ?? null;
                        $orgType = $organization['type'][0]['text'] ?? null;
                        
                        // Filtrar por identifier
                        if ($orgIdentifier === $identifierValue && $orgType === $typeText) {
                            $allDuplicates[] = $organization;
                            $debugInfo[] = "  - ID: " . ($organization['id'] ?? 'N/A') . " (coincide identifier y type)";
                        }
                    }
                }
            }
        }
    }
    
    // ========== DECIDIR QUÉ ID USAR ==========
    
    if (!empty($allDuplicates)) {
        // Tomar la PRIMERA organización encontrada
        $existingOrganizationId = $allDuplicates[0]['id'];
        $debugInfo[] = "✅ Encontrada organización existente con ID: $existingOrganizationId";
        $debugInfo[] = "⚠️ IMPORTANTE: Se usará ESTE ID para ACTUALIZAR";
        $debugInfo[] = "⚠️ El ID del JSON (" . ($organizationData['id'] ?? 'ninguno') . ") será IGNORADO";
        
        // FORZAR el uso del ID existente
        $organizationData['id'] = $existingOrganizationId;
    } else {
        $debugInfo[] = "❌ No se encontró organización existente con identifier='$identifierValue' y type='$typeText'";
        $debugInfo[] = "🆕 Se CREARÁ nueva organización";
        
        // Si no tiene ID, usar el identifier value como ID
        if (empty($organizationData['id'])) {
            // Limpiar el identifier para usarlo como ID (reemplazar caracteres no válidos)
            $organizationData['id'] = preg_replace('/[^A-Za-z0-9\-.]/', '', $identifierValue);
            $debugInfo[] = "ID generado desde identifier: " . $organizationData['id'];
        }
    }
    
    // ========== HACER PUT ==========
    
    $url = $options['baseUrl'] . '/Organization/' . $organizationData['id'];
    $jsonData = json_encode($organizationData);
    $debugInfo[] = "🚀 HACIENDO PUT a: $url";
    $debugInfo[] = "📦 Datos enviados: " . substr($jsonData, 0, 300);
    
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
            'debug' => $options['debug'] ? $debugInfo : null
        ];
    }
    
    // ========== RESULTADO ==========
    
    if ($httpCode === 200 || $httpCode === 201) {
        $resultOrganization = json_decode($response, true);
        
        return $resultOrganization;
    } else {
        return [
            'success' => false,
            'error' => "Error HTTP $httpCode",
            'response' => $response,
            'debug' => $options['debug'] ? $debugInfo : null
        ];
    }
}

/**
 * Busca una organización por su identificador (código)
 * 
 * @param string $identifier Identificador de la organización (ej: "0005000.00010102")
 * @param array $options Configuraciones adicionales
 * @return array Respuesta estandarizada con la organización encontrada
 */
function buscarOrganizacionPorIdentificador($identifier, $options = []) {
    // Configuración por defecto
    $defaults = [
        'baseUrl' => APP_FHIR_SERVER,
        'onDuplicate' => 'first', // 'first', 'newest', 'oldest', 'throw', 'error'
        'timeout' => 30,
        'verifySsl' => true
    ];
    
    $options = array_merge($defaults, $options);
    $url = $options['baseUrl'] . '/Organization?identifier=' . urlencode($identifier);
    
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
            'organization' => null,
            'duplicates' => []
        ];
    }
    
    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => "Error HTTP: $httpCode",
            'found' => false,
            'organization' => null,
            'duplicates' => []
        ];
    }
    
    $bundle = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => 'Error al parsear JSON: ' . json_last_error_msg(),
            'found' => false,
            'organization' => null,
            'duplicates' => []
        ];
    }
    
    $total = $bundle['total'] ?? 0;
    $entries = $bundle['entry'] ?? [];
    $organizations = [];
    
    // Extraer las organizaciones del bundle
    foreach ($entries as $entry) {
        if (isset($entry['resource']) && $entry['resource']['resourceType'] === 'Organization') {
            $organizations[] = $entry['resource'];
        }
    }
    
    $count = count($organizations);
    
    // Caso 1: No se encontró ninguna organización
    if ($count === 0) {
        return [
            'success' => true,
            'found' => false,
            'organization' => null,
            'duplicates' => [],
            'message' => "No se encontró ninguna organización con identificador: $identifier"
        ];
    }
    
    // Caso 2: Se encontró exactamente una organización
    if ($count === 1) {
        return [
            'success' => true,
            'found' => true,
            'organization' => $organizations[0],
            'duplicates' => [],
            'message' => 'Organización encontrada'
        ];
    }
    
    // Caso 3: Múltiples organizaciones (duplicados)
    $selectedOrganization = null;
    $message = null;
    
    switch ($options['onDuplicate']) {
        case 'first':
            $selectedOrganization = $organizations[0];
            $message = "Se encontraron $count organizaciones duplicadas. Usando la primera.";
            break;
            
        case 'newest':
            // Ordenar por lastUpdated (más reciente primero)
            usort($organizations, function($a, $b) {
                $dateA = $a['meta']['lastUpdated'] ?? '1970-01-01';
                $dateB = $b['meta']['lastUpdated'] ?? '1970-01-01';
                return strcmp($dateB, $dateA);
            });
            $selectedOrganization = $organizations[0];
            $message = "Se encontraron $count organizaciones duplicadas. Usando la más reciente.";
            break;
            
        case 'oldest':
            // Ordenar por lastUpdated (más antiguo primero)
            usort($organizations, function($a, $b) {
                $dateA = $a['meta']['lastUpdated'] ?? '1970-01-01';
                $dateB = $b['meta']['lastUpdated'] ?? '1970-01-01';
                return strcmp($dateA, $dateB);
            });
            $selectedOrganization = $organizations[0];
            $message = "Se encontraron $count organizaciones duplicadas. Usando la más antigua.";
            break;
            
        case 'throw':
        case 'error':
            return [
                'success' => false,
                'error' => "Se encontraron $count organizaciones duplicadas para el identificador: $identifier",
                'found' => true,
                'organization' => null,
                'duplicates' => $organizations,
                'message' => 'Múltiples organizaciones encontradas'
            ];
            
        default:
            // Si es una función callback
            if (is_callable($options['onDuplicate'])) {
                $selectedOrganization = call_user_func($options['onDuplicate'], $organizations);
                $message = "Se encontraron $count organizaciones duplicadas. Se aplicó función personalizada.";
            } else {
                $selectedOrganization = $organizations[0];
                $message = "Se encontraron $count organizaciones duplicadas. Usando la primera por defecto.";
            }
            break;
    }
    
    return [
        'success' => true,
        'found' => true,
        'organization' => $selectedOrganization,
        'duplicates' => $organizations,
        'duplicateCount' => $count,
        'message' => $message
    ];
}