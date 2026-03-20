<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Ramsey\Uuid\Uuid;

class Practitioner
{
    public function __construct()
    {
        
    }
    
    /**
     * Transforma los datos de entrada al formato FHIR Practitioner
     * 
     * @param array $inputData Datos recibidos del cliente
     * @return array Datos transformados al formato FHIR
     */
    public function transform($inputData)
    {
        // Construir apellidos
        $apellidos = trim($inputData['primer_apellido'] . ' ' . ($inputData['segundo_apellido'] ?? ''));

        // Construir nombres
        $nombres = [$inputData['primer_nombre']];
        if (!empty($inputData['segundo_nombre'])) {
            $nombres[] = $inputData['segundo_nombre'];
        }
        
        // Construir nombre completo para el campo text
        $nombreCompleto = implode(' ', $nombres) . ' ' . $apellidos;
        
        // Generar un UUID para el profesional
        $uuid = Uuid::uuid4()->toString();
        
        // Obtener el documento
        $documento = $inputData['documento'];

        // Construir el recurso FHIR Practitioner
        $fhirPractitioner = [
            'resourceType' => 'Practitioner',
            'id' => $uuid,
            'meta' => [
                'profile' => [
                    'https://mspbs.gov.py/fhir/StructureDefinition/PractitionerPy'
                ]
            ],
            "text" => [
                "status" => "generated",
                "div" => "<div xmlns=\"http://www.w3.org/1999/xhtml\">
                            <p class=\"res-header-id\"><b>Generated Narrative: Practitioner</b></p>
                            <div style=\"background-color: #e6e6ff; padding: 10px; border: 1px solid #661aff;\">
                            {$nombreCompleto} ( Cédula de Identidad: {$documento} )
                            </div>
                        </div>"
            ],
            'identifier' => [
                [
                    'type' => [
                        'coding' => [
                            [
                                'system' => "https://mspbs.gov.py/fhir/CodeSystem/IdentificadoresProfesionalCS",
                                'code' => "01",
                                'display' => "Cédula de Identidad"
                            ]
                        ]
                    ],
                    'value' => $documento
                ]
            ],
            'name' => [
                [
                    'family' => $apellidos,
                    'given' => $nombres
                ]
            ]
        ];

        return $fhirPractitioner;
    }

    /**
     * Valida el formato de fecha de nacimiento
     * 
     * @param string $date Fecha a validar
     * @param string $format Formato esperado
     * @return bool
     */
    private function validateDate($date, $format = 'Y-m-d')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Normaliza texto (elimina espacios extras, convierte a mayúsculas)
     * 
     * @param string $text Texto a normalizar
     * @return string
     */
    private function normalizeText($text)
    {
        return trim(mb_strtoupper($text, 'UTF-8'));
    }
}
?>