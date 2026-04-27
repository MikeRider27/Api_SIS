<?php
// Enrutador MVC con soporte para rutas dinámicas y API REST

require_once __DIR__ . '/core/connection.php';


// Configuración de rutas
$routes = [
    'GET' => [
        '/' => ['controller' => 'UserController', 'method' => 'showLogin'],
        '/api/patient/{documento}' => ['controller' => 'PatientController', 'method' => 'getPatient'],
        '/api/practitioner/{documento}' => ['controller' => 'PractitionerController', 'method' => 'getPractitioner'],
        '/api/organization/{nit}' => ['controller' => 'OrganizationController', 'method' => 'getOrganization'],
        '/api/rda/{documento}' => ['controller' => 'RDAController', 'method' => 'getDocumentRDA'],
        '/api/rda/bundle/{id}' => ['controller' => 'RDAController', 'method' => 'getBundleRDA'],

    ],
    'POST' => [
        '/api/patient' => ['controller' => 'PatientController', 'method' => 'createPatient'],
        '/api/practitioner' => ['controller' => 'PractitionerController', 'method' => 'createPractitioner'],
        '/api/organization' => ['controller' => 'OrganizationController', 'method' => 'createOrganization'],
        '/api/rda' => ['controller' => 'RDAController', 'method' => 'createRDA'],
    ],
    'OPTIONS' => [
        '/api/patient' => ['controller' => null, 'method' => null],
        '/api/practitioner' => ['controller' => null, 'method' => null],
        '/api/organization' => ['controller' => null, 'method' => null],
        '/api/rda' => ['controller' => null, 'method' => null],
    ]
];

// Obtener método y URI actual
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

/**
 * Función para responder con JSON en errores de API
 */
function sendJsonError($errorCode, $message) {
    http_response_code($errorCode);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    echo json_encode([
        'error' => true,
        'status' => $errorCode,
        'message' => $message
    ]);
    exit;
}

/**
 * Función para manejar errores (detecta si es API o Web)
 */
function showErrorView($errorCode, $errorViews) {
    global $requestUri;
    
    http_response_code($errorCode);
    
    // Si es una petición a /api, responder en JSON
    if (strpos($requestUri, '/api/') === 0) {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        
        $message = $errorCode === 404 ? 'Endpoint no encontrado' : 'Error interno del servidor';
        echo json_encode([
            'error' => true,
            'status' => $errorCode,
            'message' => $message
        ]);
        exit;
    }
    
    // Para peticiones web, mostrar vista HTML
    $errorFile = $errorViews[$errorCode] ?? null;
    
    if ($errorFile && file_exists($errorFile)) {
        require $errorFile;
    } else {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Error $errorCode</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                h1 { font-size: 50px; }
                p { font-size: 20px; }
            </style>
        </head>
        <body>
            <h1>Error $errorCode</h1>
            <p>" . ($errorCode === 404 ? 'Página no encontrada' : 'Error del servidor') . "</p>
        </body>
        </html>";
    }
    exit;
}

/**
 * Función para cargar y ejecutar controlador
 */
function dispatch($controllerName, $methodName, $params = [], $errorViews)
{
    // Si es una petición OPTIONS, responder inmediatamente (CORS)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        http_response_code(200);
        exit;
    }
    
    // Si no hay controlador (como en OPTIONS), salir
    if (!$controllerName || !$methodName) {
        exit;
    }
    
    $controllerFile = __DIR__ . "/controllers/{$controllerName}.php";

    if (!file_exists($controllerFile)) {
        showErrorView(500, $errorViews);
    }

    require_once $controllerFile;

    if (!class_exists($controllerName)) {
        showErrorView(500, $errorViews);
    }

    $controller = new $controllerName();

    if (!method_exists($controller, $methodName)) {
        showErrorView(500, $errorViews);
    }

    try {
        call_user_func_array([$controller, $methodName], $params);
    } catch (Exception $e) {
        error_log("Error en controlador: " . $e->getMessage());
        showErrorView(500, $errorViews);
    }
}

// Manejar peticiones OPTIONS (CORS preflight)
if ($method === 'OPTIONS') {
    // Verificar si la ruta OPTIONS está definida
    if (isset($routes['OPTIONS'][$requestUri])) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400'); // 24 horas
        http_response_code(200);
        exit;
    }
    
    // Si no está definida pero es /api/, responder igual
    if (strpos($requestUri, '/api/') === 0) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        http_response_code(200);
        exit;
    }
}

// Buscar coincidencia exacta
if (isset($routes[$method][$requestUri])) {
    $route = $routes[$method][$requestUri];
    dispatch($route['controller'], $route['method'], [], $errorViews ?? []);
    exit;
}

// Intentar hacer match con rutas dinámicas
foreach ($routes[$method] ?? [] as $routePattern => $routeData) {
    // Convertir {param} a regex
    $pattern = preg_replace('#\{[a-zA-Z_][a-zA-Z0-9_]*\}#', '([^/]+)', $routePattern);
    $pattern = "#^" . rtrim($pattern, '/') . "$#";
    
    if (preg_match($pattern, $requestUri, $matches)) {
        array_shift($matches); // Eliminar el match completo
        dispatch($routeData['controller'], $routeData['method'], $matches, $errorViews ?? []);
        exit;
    }
}

// Si no hay coincidencias, mostrar error 404
showErrorView(404, $errorViews ?? []);

?>