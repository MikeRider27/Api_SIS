<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusión de archivos necesarios
require_once __DIR__ . '/../models/RDA.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Cargar autoload de Composer para ramsey/uuid
require_once __DIR__ . '/../utils/patient.php'; // Cargar utilidades de paciente
require_once __DIR__ . '/../utils/practitioner.php'; // Cargar utilidades de profesional

use Ramsey\Uuid\Uuid;

class RDAController
{
    private $rdaModel;

    public function __construct()
    {
        $this->rdaModel = new RDA(); // Instancia del modelo RDA
    }

    public function createRDA()
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

        // Validar toda la estructura del JSON
        $validation = $this->validateRDA($data);
        
        if (!$validation['valid']) {
            http_response_code(400);
            echo json_encode([
                'error' => 'Datos inválidos',
                'validation_errors' => $validation['errors']
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit();
        }

        try {
            // Transformar los datos al formato FHIR
            $fhirRDA = $this->rdaModel->transform($data);

            // Devolver la respuesta
            http_response_code(200);
            echo json_encode($fhirRDA, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]);
        }
    }

    /**
     * Valida la estructura completa del RDA
     * @param array $data Datos a validar
     * @return array ['valid' => bool, 'errors' => array]
     */
    private function validateRDA($data)
    {
        $errors = [];

        // Validar paciente
        if (!isset($data['paciente']) || !is_array($data['paciente'])) {
            $errors[] = "El campo 'paciente' es requerido y debe ser un objeto";
        } else {
            $pacienteErrors = $this->validatePaciente($data['paciente']);
            $errors = array_merge($errors, $pacienteErrors);
        }

        // Validar profesional
        if (!isset($data['profesional']) || !is_array($data['profesional'])) {
            $errors[] = "El campo 'profesional' es requerido y debe ser un objeto";
        } else {
            $profesionalErrors = $this->validateProfesional($data['profesional']);
            $errors = array_merge($errors, $profesionalErrors);
        }

        // Validar organización
        if (!isset($data['organizacion']) || !is_array($data['organizacion'])) {
            $errors[] = "El campo 'organizacion' es requerido y debe ser un objeto";
        } else {
            $organizacionErrors = $this->validateOrganizacion($data['organizacion']);
            $errors = array_merge($errors, $organizacionErrors);
        }

        // Validar diagnósticos (opcional pero si existe debe ser array)
        if (isset($data['diagnosticos']) && !is_array($data['diagnosticos'])) {
            $errors[] = "El campo 'diagnosticos' debe ser un array";
        } elseif (isset($data['diagnosticos']) && !empty($data['diagnosticos'])) {
            $diagnosticoErrors = $this->validateDiagnosticos($data['diagnosticos']);
            $errors = array_merge($errors, $diagnosticoErrors);
        }

        // Validar alergias (opcional)
        if (isset($data['alergias']) && !is_array($data['alergias'])) {
            $errors[] = "El campo 'alergias' debe ser un array";
        } elseif (isset($data['alergias']) && !empty($data['alergias'])) {
            $alergiasErrors = $this->validateAlergias($data['alergias']);
            $errors = array_merge($errors, $alergiasErrors);
        }

        // Validar medicamentos (opcional)
        if (isset($data['medicamentos']) && !is_array($data['medicamentos'])) {
            $errors[] = "El campo 'medicamentos' debe ser un array";
        } elseif (isset($data['medicamentos']) && !empty($data['medicamentos'])) {
            $medicamentosErrors = $this->validateMedicamentos($data['medicamentos']);
            $errors = array_merge($errors, $medicamentosErrors);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Valida los datos del paciente
     */
    private function validatePaciente($paciente)
    {
        $errors = [];
        $requiredFields = ['tipo_documento', 'documento', 'primer_nombre', 'primer_apellido', 'fecha_nacimiento', 'sexo'];
        
        foreach ($requiredFields as $field) {
            if (!isset($paciente[$field]) || empty(trim($paciente[$field]))) {
                $errors[] = "paciente.$field: Campo requerido";
            }
        }

        // Validar formato de fecha
        if (isset($paciente['fecha_nacimiento']) && !$this->validateDate($paciente['fecha_nacimiento'])) {
            $errors[] = "paciente.fecha_nacimiento: Formato inválido (debe ser YYYY-MM-DD)";
        }

        // Validar sexo
        if (isset($paciente['sexo']) && !in_array($paciente['sexo'], ['masculino', 'femenino', 'otro'])) {
            $errors[] = "paciente.sexo: Debe ser 'masculino', 'femenino' u 'otro'";
        }

        return $errors;
    }

    /**
     * Valida los datos del profesional
     */
    private function validateProfesional($profesional)
    {
        $errors = [];
        $requiredFields = ['documento', 'primer_nombre', 'primer_apellido'];
        
        foreach ($requiredFields as $field) {
            if (!isset($profesional[$field]) || empty(trim($profesional[$field]))) {
                $errors[] = "profesional.$field: Campo requerido";
            }
        }

        return $errors;
    }

    /**
     * Valida los datos de la organización
     */
    private function validateOrganizacion($organizacion)
    {
        $errors = [];
        $requiredFields = ['codigo', 'tipo', 'nombre'];
        
        foreach ($requiredFields as $field) {
            if (!isset($organizacion[$field]) || empty(trim($organizacion[$field]))) {
                $errors[] = "organizacion.$field: Campo requerido";
            }
        }

        // Validar tipo (según tus necesidades)
        $validTypes = ['HG', 'HR', 'HD', 'HP', 'HE', 'HESC', 'IP', 'IPS']; // Ajusta según necesites
        if (isset($organizacion['tipo']) && !in_array($organizacion['tipo'], $validTypes)) {
            $errors[] = "organizacion.tipo: Tipo inválido. Permitidos: " . implode(', ', $validTypes);
        }

        return $errors;
    }

    /**
     * Valida los diagnósticos
     */
    private function validateDiagnosticos($diagnosticos)
    {
        $errors = [];
        
        foreach ($diagnosticos as $index => $dx) {
            $requiredFields = ['cie10_code', 'cie10_term', 'status', 'diagnostico_fecha', 'nota'];
            
            foreach ($requiredFields as $field) {
                if (!isset($dx[$field]) || empty(trim($dx[$field]))) {
                    $errors[] = "diagnostico[$index].$field: Campo requerido";
                }
            }

            // Validar status
            if (isset($dx['status']) && !in_array($dx['status'], ['confirmado', 'provisional', 'descartado'])) {
                $errors[] = "diagnostico[$index].status: Debe ser 'confirmado', 'provisional' o 'descartado'";
            }

            // Validar formato de fecha
            if (isset($dx['diagnostico_fecha']) && !$this->validateDate($dx['diagnostico_fecha'])) {
                $errors[] = "diagnostico[$index].diagnostico_fecha: Formato inválido (debe ser YYYY-MM-DD)";
            }

            // Validar nota
            if (isset($dx['nota']) && !is_string($dx['nota'])) {
                $errors[] = "diagnostico[$index].nota: Debe ser una cadena de texto";
            }
        }

        return $errors;
    }

    /**
     * Valida las alergias
     */
    private function validateAlergias($alergias)
    {
        $errors = [];
        
        foreach ($alergias as $index => $alergia) {
             $requiredFields = ['alergia_term', 'categoria'];

            foreach ($requiredFields as $field) {
                if (!isset($alergia[$field]) || empty(trim($alergia[$field]))) {
                    $errors[] = "alergias[$index].$field: Campo requerido";
                }
            }
            // Validar categoría si existe
            if (isset($alergia['categoria'])) {
                $validCategories = ['medicamento', 'alimento', 'animal', 'otro'];
                if (!in_array($alergia['categoria'], $validCategories)) {
                    $errors[] = "alergias[$index].categoria: Categoría inválida";
                }
            }

            // Validar termino de alergia
            if (isset($alergia['alergia_term']) && !is_string($alergia['alergia_term'])) {
                $errors[] = "alergias[$index].alergia_term: Debe ser una cadena de texto";
            }
        }

        return $errors;
    }

    /**
     * Valida los medicamentos
     */
    private function validateMedicamentos($medicamentos)
    {
        $errors = [];
        
        foreach ($medicamentos as $index => $med) {
            $requiredFields = ['medicamento_term', 'medicamento_fecha', 'medicamento_dosis', 'medicamento_via'];
            
            foreach ($requiredFields as $field) {
                if (!isset($med[$field]) || empty(trim($med[$field]))) {
                    $errors[] = "medicamentos[$index].$field: Campo requerido";
                }
            }

            // Validar formato de fecha
            if (isset($med['medicamento_fecha']) && !$this->validateDate($med['medicamento_fecha'])) {
                $errors[] = "medicamentos[$index].medicamento_fecha: Formato inválido (debe ser YYYY-MM-DD)";
            }

            // Validar vía de administración si existe
            if (isset($med['medicamento_via'])) {
                $validVias = ['oral', 'intravenosa', 'intramuscular', 'subcutanea', 'topica', 'inhalatoria'];
                if (!in_array($med['medicamento_via'], $validVias)) {
                    $errors[] = "medicamentos[$index].medicamento_via: Vía de administración inválida";
                }
            }
        }

        return $errors;
    }

    /**
     * Valida formato de fecha YYYY-MM-DD
     */
    private function validateDate($date, $format = 'Y-m-d')
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}