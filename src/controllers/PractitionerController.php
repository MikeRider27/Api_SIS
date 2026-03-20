<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../models/Practitioner.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Ramsey\Uuid\Uuid;

class PractitionerController
{
    private $practitionerModel;

    public function __construct()
    {
        $this->practitionerModel = new Practitioner();
    }

    public function createPractitioner()
    {
        // Configurar headers para API
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        // NOTA: El manejo de OPTIONS ya se hace en el router, 
        // así que aquí no es necesario repetirlo

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
        $requiredFields = ['documento', 'primer_nombre', 'primer_apellido'];
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

        // Validar formato de documento (opcional, según tus necesidades)
        if (!preg_match('/^[0-9]+$/', $data['documento'])) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => 'Formato de documento inválido',
                'details' => 'El documento debe contener solo números'
            ]);
            exit();
        }

        try {
            // Transformar los datos al formato FHIR
            $fhirPractitioner = $this->practitionerModel->transform($data);

            // Devolver la respuesta exitosa
            http_response_code(201); // 201 Created
            echo json_encode($fhirPractitioner, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            error_log("Error en PractitionerController::createPractitioner: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'error' => true,
                'message' => 'Error interno del servidor',
                'details' => $e->getMessage() // En producción, quita esto o solo en modo debug
            ]);
        }
    }
}