<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Ramsey\Uuid\Uuid;

class Patient
{
    // Mapeos constantes para mejor mantenimiento
    const DOCUMENT_TYPE_MAP = [
        '01' => 'Cédula de Identidad',
        '02' => 'Pasaporte',
        '03' => 'Cédula Extranjera'      
    ];
    
    const GENDER_MAP = [
        'masculino' => 'male',
        'femenino' => 'female',
        'otro' => 'other',
        'desconocido' => 'unknown'
    ];
    
    public function __construct()
    {
        // Constructor vacío
    }
    
    /**
     * Transforma los datos de entrada al formato FHIR Patient
     * 
     * @param array $inputData Datos recibidos del cliente
     * @return array Datos transformados al formato FHIR
     */
    public function transform($inputData)
    {
        // Construir apellidos
        $apellidos = trim($inputData['primer_apellido']);
        if (!empty($inputData['segundo_apellido'])) {
            $apellidos .= ' ' . $inputData['segundo_apellido'];
        }
        
        // Construir nombres
        $nombres = [$inputData['primer_nombre']];
        if (!empty($inputData['segundo_nombre'])) {
            $nombres[] = $inputData['segundo_nombre'];
        }
        
        // Obtener valores mapeados
        $tipoDocDisplay = self::DOCUMENT_TYPE_MAP[$inputData['tipo_documento']] ?? 'Documento de Identidad';
        $gender = self::GENDER_MAP[strtolower($inputData['sexo'])] ?? 'unknown';
        
        // Generar UUID
        $uuid = Uuid::uuid4()->toString();
        
        // Construir nombre completo para el campo text
        $nombreCompleto = trim(
            $inputData['primer_nombre'] . ' ' . 
            ($inputData['segundo_nombre'] ?? '') . ' ' . 
            $apellidos
        );
        
        // Construir el recurso FHIR Patient
        $fhirPatient = [
            'resourceType' => 'Patient',
            'id' => $uuid,
            'meta' => [
                'profile' => [
                    'https://mspbs.gov.py/fhir/StructureDefinition/PacientePy'
                ]
            ],
            "text" => [
                "status" => "generated",
                "div" => "<div xmlns=\"http://www.w3.org/1999/xhtml\"><p class=\"res-header-id\"><b>Generated Narrative: Patient</b></p><div style=\"background-color: #e6e6ff; padding: 10px; border: 1px solid #661aff;\"> {$nombreCompleto}, {$gender}, DoB: {$inputData['fecha_nacimiento']} ( {$tipoDocDisplay}: {$inputData['documento']} )</div></div>"
            ],
            'identifier' => [
                [
                    'type' => [
                        'coding' => [
                            [
                                'system' => 'https://mspbs.gov.py/fhir/CodeSystem/IdentificadoresPersonaCS',
                                'code' => $inputData['tipo_documento'],
                                'display' => $tipoDocDisplay
                            ]
                        ]
                    ],
                    'value' => $inputData['documento']
                ]
            ],
            'name' => [
                [
                    'family' => $apellidos,
                    'given' => $nombres
                ]
            ],
            'gender' => $gender,
            'birthDate' => $inputData['fecha_nacimiento']
        ];       
        
        return $fhirPatient;
    }
}

?>