<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Ramsey\Uuid\Uuid;

class Organization
{
    public function __construct()
    {
        
    }
    
    /**
     * Transforma los datos de entrada al formato FHIR Organization
     * 
     * @param array $inputData Datos recibidos del cliente
     * @return array Datos transformados al formato FHIR
     */
    public function transform($inputData)
    {
        // Preparar variables para el campo text
        $codigo = $inputData['codigo'];
        $tipo = $inputData['tipo'];
        $nombreMayusculas = strtoupper($inputData['nombre']);
        
        // Construir el recurso FHIR Organization según estructura definida
        $fhirOrganization = [
            'resourceType' => 'Organization',
            'id' => $codigo,
            'meta' => [
                'profile' => [
                    'https://mspbs.gov.py/fhir/StructureDefinition/OrganizacionPy'
                ]
            ],
            "text" => [
                "status" => "generated",
                "div" => "<div xmlns=\"http://www.w3.org/1999/xhtml\"><p class=\"res-header-id\"><b>Generated Narrative: Organization {$codigo}</b></p><a name=\"{$codigo}\"> </a><a name=\"hc{$codigo}\"> </a><div style=\"display: inline-block; background-color: #d9e0e7; padding: 6px; margin: 4px; border: 1px solid #8da1b4; border-radius: 5px; line-height: 60%\"><p style=\"margin-bottom: 0px\"/><p style=\"margin-bottom: 0px\">Profile: <a href=\"StructureDefinition-OrganizacionPy.html\">Organizacion Paraguay</a></p></div><p><b>identifier</b>: {$codigo}</p><p><b>type</b>: <span title=\"Codes:\">{$tipo}</span></p><p><b>name</b>: {$nombreMayusculas}</p></div>"
            ],
            'identifier' => [
                [                       
                    'value' => $codigo
                ]
            ],
            'type' => [
                [
                    "text" => $tipo
                ]
            ],
            'name' => $nombreMayusculas
        ];

        return $fhirOrganization;
    }

 
}
?>