<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'config/database.php';
require_once 'controllers/SchoolController.php';

$method = $_SERVER['REQUEST_METHOD'];
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request = str_replace('/track_x', '', $request);
$segments = explode('/', trim($request, '/'));

$db = new Database();
$connection = $db->getConnection();

// Route requests
switch($segments[0] ?? '') {
    case 'schools':
        $controller = new SchoolController($connection);
        $id = $segments[1] ?? null;
        
        switch($method) {
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
                }
                break;
            case 'DELETE':
                if ($id) {
                    $controller->delete($id);
                }
                break;
        }
        break;
    default:
        http_response_code(404);
        echo json_encode(["message" => "Endpoint not found"]);
        break;
}
?>