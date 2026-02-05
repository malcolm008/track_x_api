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

// Get the request path without query string
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove the base directory (/track_x)
$path = str_replace('/track_x', '', $path);

// Remove leading/trailing slashes
$path = trim($path, '/');

// Split into segments
$segments = explode('/', $path);
$resource = $segments[0] ?? '';
$id = $segments[1] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

// Debug: Uncomment to see what's happening
// file_put_contents('debug.log', "Resource: $resource, ID: " . ($id ?? 'null') . "\n", FILE_APPEND);

// Route the request
switch ($resource) {
    case 'schools':
        $controller = new SchoolController($connection);
        switch ($method) {
            case 'GET':
                if ($id) {
                    $controller->getById($id);
                } else {
                    $controller->getAll();
                }
                break;
            case 'POST':
                $controller->create();
                break;
            case 'PUT':
                if ($id) {
                    $controller->update($id);
                } else {
                    http_response_code(400);
                    echo json_encode(["message" => "School ID required"]);
                }
                break;
            case 'DELETE':
                if ($id) {
                    $controller->delete($id);
                } else {
                    http_response_code(400);
                    echo json_encode(["message" => "School ID required"]);
                }
                break;
            default:
                http_response_code(405);
                echo json_encode(["message" => "Method not allowed"]);
        }
        break;

    case 'plans':
        $controller = new PlanController($connection);
        switch ($method) {
            case 'GET':
                if ($id) {
                    $controller->getById($id);
                } else {
                    $controller->getAll();
                }
                break;
            case 'POST':
                $controller->create();
                break;
            case 'PUT':
                if ($id) {
                    $controller->update($id);
                } else {
                    http_response_code(400);
                    echo json_encode(["message" => "Plan ID required"]);
                }
                break;
            case 'DELETE':
                if ($id) {
                    $controller->delete($id);
                } else {
                    http_response_code(400);
                    echo json_encode(["message" => "School ID required"]);
                }
                break;
            default:
                http_response_code(405);
                echo json_encode(["message" => "Method not allowed"]);
        }
        break;

    case 'subscriptions':
        $controller = new SubscriptionController($connection);
        switch ($method) {
            case 'GET':
                if ($id) {
                    $controller->getById($id);
                } else {
                    $controller->getAll();
                }
                break;
            case 'POST':
                $controller->create();
                break;
            case 'PUT':
                if ($id) {
                    $controller->update($id);
                } else {
                    http_response_code(400);
                    echo json_encode(["message" => "Subscription ID required"]);
                }
                break;
            case 'DELETE':
                if ($id) {
                    $controller->delete($id);
                } else {
                    http_response_code(400);
                    echo json_encode(["message" => "Subscription ID required"]);
                }
                break;
            default:
                http_response_code(405);
                echo json_encode(["message" => "Method not allowed"]);
        }
        break;

    case '':
        echo json_encode(["message" => "API is running. Try /schools, /plans, or /subscriptions"]);
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Endpoint not found: $resource"]);
}