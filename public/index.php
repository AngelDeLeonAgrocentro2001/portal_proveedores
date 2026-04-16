<?php
// public/index.php - Versión con debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();
session_start();

define('BASE_PATH', dirname(__DIR__) . '/');

require_once BASE_PATH . 'config/config.php';
require_once BASE_PATH . 'database/DatabasePortal.php';
require_once BASE_PATH . 'database/DatabaseCajas.php';
require_once BASE_PATH . 'database/DatabaseSAP.php';


$controller = $_GET['controller'] ?? 'auth';
$action     = $_GET['action']     ?? 'login';

$controllerFile = BASE_PATH . "app/controllers/" . ucfirst($controller) . "Controller.php";

if (file_exists($controllerFile)) {
    require_once $controllerFile;
    
    $className = ucfirst($controller) . "Controller";
    if (class_exists($className)) {
        $ctrl = new $className();
        if (method_exists($ctrl, $action)) {
            $ctrl->$action();
            exit;
        }
    }
}

echo "<h2 style='color:red;text-align:center;margin-top:100px;'>Error: Controlador o acción no encontrado </h2>";