<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusión de archivos necesarios
require_once __DIR__ . '/../models/Organization.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Cargar autoload de Composer para ramsey/uuid

use Ramsey\Uuid\Uuid;

class OrganizationController
{
    private $organizationModel;

    public function __construct()
    {
        $this->organizationModel = new Organization(); // Instancia del modelo Organization
    }

    // Muestra el formulario de login, verificando primero si hay sesión activa
    public function createOrganization()
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

      
        // Manejar preflight requests de CORS
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }

        // Verificar que sea una petición POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido. Use POST.']);
            exit();
        }

        // Obtener el cuerpo de la petición
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        // Validar que los datos sean válidos
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'JSON inválido']);
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
                'error' => 'Campos requeridos faltantes',
                'missing_fields' => $missingFields
            ]);
            exit();
        }

        try {
            // Transformar los datos al formato FHIR
            $fhirOrganization = $this->organizationModel->transform($data);

            // Devolver la respuesta
            http_response_code(200);
            echo json_encode($fhirOrganization, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]);
        }
    }

  
}
