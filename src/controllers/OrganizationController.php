<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../models/Organization.php';
require_once __DIR__ . '/../utils/organization.php'; // Cargar utilidades de organization
require_once __DIR__ . '/../vendor/autoload.php';

use Ramsey\Uuid\Uuid;

class OrganizationController
{
    private $organizationModel;
    
    // Definir los tipos válidos con sus descripciones
    private $validTypes = [
        'HG' => 'HOSPITAL GENERAL',
        'HR' => 'HOSPITAL REGIONAL',
        'HD' => 'HOSPITAL DISTRITAL',
        'HP' => 'HOSPITAL PRIVADO',
        'HE' => 'HOSPITAL ESPECIALIZADO',
        'HESC' => 'HOSPITAL ESCUELA',
        'IP' => 'INSTITUCIÓN PRIVADA',
        'IPS' => 'INSTITUTO DE PREVISION SOCIAL'
    ];

    public function __construct()
    {
        $this->organizationModel = new Organization();
    }

    public function createOrganization()
    {
        // Verificar que sea una petición POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'error' => true,
                'message' => 'Método no permitido. Use POST.',
                'allowed_methods' => ['POST']
            ]);
            exit();
        }

        // Obtener el cuerpo de la petición
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        // Validar que los datos sean válidos
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => 'JSON inválido',
                'details' => json_last_error_msg()
            ]);
            exit();
        }

        // Validar campos requeridos
        $requiredFields = ['codigo', 'tipo', 'nombre'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => 'Campos requeridos faltantes',
                'missing_fields' => $missingFields,
                'required_fields' => $requiredFields
            ]);
            exit();
        }

        // Validar tipos de organización permitidos
        $tipoUpper = strtoupper($data['tipo']);
        
        if (!array_key_exists($tipoUpper, $this->validTypes)) {
            // Construir lista de valores válidos para mostrar
            $validValuesList = [];
            foreach ($this->validTypes as $code => $description) {
                $validValuesList[] = $code . ' (' . $description . ')';
            }
            
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => 'Tipo de organización inválido',
                'received_value' => $data['tipo'],
                'valid_values' => array_keys($this->validTypes),
                'valid_values_with_description' => $validValuesList
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit();
        }

        try {                       
            // Transformar los datos al formato FHIR
            $fhirOrganization = $this->organizationModel->transform($data);

            // Convertir el array a JSON para enviar al servidor FHIR
            $organizationData = json_encode($fhirOrganization, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            // Crear la organización en el servidor FHIR
            $createResponse = crearOrganizacion($fhirOrganization);
            // Devolver la respuesta exitosa
            http_response_code(201);
            echo json_encode([
                'error' => false,
                'message' => 'Organización creada exitosamente',
                'data' => $createResponse
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            error_log("Error en OrganizationController::createOrganization: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'error' => true,
                'message' => 'Error interno del servidor',
                'details' => $e->getMessage()
            ]);
        }
    }

    function getOrganization($identifier) {
            try {
            $organization = $this->organizationModel->getByIdentifier($identifier);

            if ($organization) {
                http_response_code(200);
                echo json_encode([
                    'error' => false,
                    'message' => 'Organización encontrada',
                    'data' => $organization
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'error' => true,
                    'message' => 'Organizacion no encontrado'
                ]);
            }
        } catch (Exception $e) {
            error_log("Error en OrganizationController::getOrganizationByIdentifier: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'error' => true,
                'message' => 'Error interno del servidor',
                'details' => $e->getMessage() // En producción, quita esto o solo en modo debug
            ]);
        }     
    }
}
?>