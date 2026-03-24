<?php
// public/index.php  ← Controlador Frontal
session_start();

// Definir ruta base del proyecto (muy importante)
define('BASE_PATH', dirname(__DIR__) . '/');

// Cargar configuración
require_once BASE_PATH . 'config/config.php';
require_once BASE_PATH . 'database/DatabasePortal.php';
require_once BASE_PATH . 'database/DatabaseCajas.php';

// Routing simple
$controller = $_GET['controller'] ?? 'auth';
$action     = $_GET['action']     ?? 'login';

$controllerClass = ucfirst($controller) . 'Controller';
$controllerFile  = BASE_PATH . "app/controllers/{$controllerClass}.php";

if (file_exists($controllerFile)) {
    require_once $controllerFile;

    if (class_exists($controllerClass)) {
        $ctrl = new $controllerClass();
        
        if (method_exists($ctrl, $action)) {
            $ctrl->$action();
            exit;
        }
    }
}

// Si algo falla → página 404
http_response_code(404);
require_once BASE_PATH . 'app/views/errores/404.php';