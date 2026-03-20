<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusión de archivos necesarios
require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Ramsey\Uuid\Uuid;

class PatientController
{
    private $patientModel;

    public function __construct()
    {
        $this->patientModel = new Patient();
    }

    public function createPatient()
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
        $requiredFields = ['tipo_documento', 'documento', 'primer_nombre', 'primer_apellido', 'fecha_nacimiento', 'sexo'];
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

        // Validar formato de fecha
        if (!$this->validateDate($data['fecha_nacimiento'])) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => 'Formato de fecha inválido',
                'expected_format' => 'YYYY-MM-DD'
            ]);
            exit();
        }

        // Validar sexo
        $validGenders = ['masculino', 'femenino', 'otro', 'desconocido'];
        if (!in_array(strtolower($data['sexo']), $validGenders)) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => 'Sexo inválido',
                'valid_values' => $validGenders
            ]);
            exit();
        }

        try {
            // Transformar los datos al formato FHIR
            $fhirPatient = $this->patientModel->transform($data);

            // Devolver la respuesta exitosa
            http_response_code(201); // 201 Created es más adecuado para creación
            echo json_encode($fhirPatient, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            error_log("Error en PatientController::createPatient: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'error' => true,
                'message' => 'Error interno del servidor',
                'details' => $e->getMessage() // En producción, quita esto o solo en modo debug
            ]);
        }
    }

    /**
     * Valida el formato de fecha
     */
    private function validateDate($date, $format = 'Y-m-d')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}