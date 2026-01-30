<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'config/database.php';
require_once 'controllers/SchoolController.php';
require_once 'controllers/PlanController.php';
require_once 'controllers/SubscriptionController.php';

$db = new Database();
$connection = $db->getConnection();

// Parse the request
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptDir = dirname($_SERVER['SCRIPT_NAME']); 
$request = substr($requestUri, strlen($scriptDir));
$request = preg_replace('#^/index\.php#', '', $request); 

$segments = explode('/', trim($request, '/'));
$resource = $segments[0] ?? '';
$id = $segments[1] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

// Route the request
switch ($resource) {

    case 'schools':
        $controller = new SchoolController($connection);
        match ($method) {
            'GET' => $id ? $controller->getById($id) : $controller->getAll(),
            'POST' => $controller->create(),
            'PUT' => $id ? $controller->update($id) : http_response_code(400),
            'DELETE' => $id ? $controller->delete($id) : http_response_code(400),
            default => http_response_code(405)
        };
        break;

    case 'plans':
        $controller = new PlanController($connection);
        match ($method) {
            'GET' => $id ? $controller->getById($id) : $controller->getAll(),
            'POST' => $controller->create(),
            'PUT' => $id ? $controller->update($id) : http_response_code(400),
            'DELETE' => $id ? $controller->delete($id) : http_response_code(400),
            default => http_response_code(405)
        };
        break;

    case 'subscriptions':
        $controller = new SubscriptionController($connection);
        match ($method) {
            'GET' => $controller->getAll(),
            'POST' => $controller->create(),
            default => http_response_code(405)
        };
        break;

    case '':
        echo json_encode(["message" => "API is running. Try /schools, /plans, or /subscriptions"]);
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Endpoint not found"]);
}
