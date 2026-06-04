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

// ========== NUEVO CÓDIGO: Controladores permitidos ==========
$allowed_controllers = ['auth', 'proveedor', 'admin', 'compras', 'autorizacionProveedor', 'finanzas', 'contabilidad', 'ingresoManual'];
$controller = $_GET['controller'] ?? 'auth';

// Validar que el controlador esté permitido
if (!in_array($controller, $allowed_controllers)) {
    $controller = 'auth';
    $action = 'login'; // Redirigir a login si intentan acceder a controlador no permitido
}
// ============================================================

$action = $_GET['action'] ?? 'login';  // Esta línea debe estar DESPUÉS de la validación

$controllerFile = BASE_PATH . "app/controllers/" . ucfirst($controller) . "Controller.php";

if (file_exists($controllerFile)) {
    require_once $controllerFile;
    
    $className = ucfirst($controller) . "Controller";
    if (class_exists($className)) {
        $ctrl = new $className();
        
        // ========== ACCIONES ESPECIALES PARA CONTABILIDAD ==========
        // Manejar acciones específicas que no siguen el patrón estándar
        if ($controller === 'contabilidad') {
            switch ($action) {
                case 'enviarSAP':
                    $ctrl->enviarSAP();
                    break;
                case 'descargarPDF':
                    $ctrl->descargarPDF();
                    break;
                case 'pdfContraseña':
                    $ctrl->pdfContraseña();
                    break;
                case 'descargarRetencionIVA':
                    $ctrl->descargarRetencionIVA();
                    break;
                case 'descargarRetencionISR':
                    $ctrl->descargarRetencionISR();
                    break;
                default:
                    if (method_exists($ctrl, $action)) {
                        $ctrl->$action();
                    } else {
                        $ctrl->dashboard();
                    }
                    break;
            }
            exit;
        }
        
        // ========== ACCIONES ESPECIALES PARA FINANZAS ==========
        if ($controller === 'finanzas') {
            switch ($action) {
                case 'descargarPDF':
                    $ctrl->descargarPDF();
                    break;
                case 'pdfContraseña':
                    $ctrl->pdfContraseña();
                    break;
                default:
                    if (method_exists($ctrl, $action)) {
                        $ctrl->$action();
                    } else {
                        $ctrl->dashboard();
                    }
                    break;
            }
            exit;
        }
        
        // ========== ACCIONES ESPECIALES PARA ADMIN ==========
        if ($controller === 'admin') {
            switch ($action) {
                case 'getOrdenesDisponibles':
                    $ctrl->getOrdenesDisponibles();
                    break;
                case 'cambiarOrdenCompra':
                    $ctrl->cambiarOrdenCompra();
                    break;
                case 'aprobarFacturaCompras':
                    $ctrl->aprobarFacturaCompras();
                    break;
                case 'rechazarFacturaCompras':
                    $ctrl->rechazarFacturaCompras();
                    break;
                case 'descargarDocumento':
                    $ctrl->descargarDocumento();
                    break;
                case 'pdfContraseña':
                    $ctrl->pdfContraseña();
                    break;
                case 'logoutAgrosistemas':
                    $ctrl->logoutAgrosistemas();
                    break;
                default:
                    if (method_exists($ctrl, $action)) {
                        $ctrl->$action();
                    } else {
                        $ctrl->gestionarContraseñas();
                    }
                    break;
            }
            exit;
        }
        
        // ========== ACCIONES ESPECIALES PARA PROVEEDOR ==========
        if ($controller === 'proveedor') {
            switch ($action) {
                case 'pdfContraseña':
                    $ctrl->pdfContraseña();
                    break;
                case 'pdfOrdenCompra':
                    $ctrl->pdfOrdenCompra();
                    break;
                case 'descargar':
                    $ctrl->descargar();
                    break;
                case 'descargarComprobanteGasto':
                    $ctrl->descargarComprobanteGasto();
                    break;
                case 'enviarContacto':
                    $ctrl->enviarContacto();
                    break;
                case 'buscarFacturaAdicional':
                    $ctrl->buscarFacturaAdicional();
                    break;
                case 'getFacturasDisponiblesAdicionales':
                    $ctrl->getFacturasDisponiblesAdicionales();
                    break;
                case 'buscarDTEsPorNit':
                    $ctrl->buscarDTEsPorNit();
                    break;
                case 'timelineFactura':
                    $ctrl->timelineFactura();
                    break;
                default:
                    if (method_exists($ctrl, $action)) {
                        $ctrl->$action();
                    } else {
                        $ctrl->dashboard();
                    }
                    break;
            }
            exit;
        }
        
        // ========== ACCIONES ESPECIALES PARA COMPRAS ==========
        if ($controller === 'compras') {
            switch ($action) {
                case 'cambiarOrdenesCompra':
                    $ctrl->cambiarOrdenesCompra();
                    break;
                case 'rechazarFactura':
                    $ctrl->rechazarFactura();
                    break;
                case 'aprobarFactura':
                    $ctrl->aprobarFactura();
                    break;
                default:
                    if (method_exists($ctrl, $action)) {
                        $ctrl->$action();
                    } else {
                        $ctrl->revisionPendiente();
                    }
                    break;
            }
            exit;
        }
        
        // ========== ACCIONES ESPECIALES PARA AUTORIZACION PROVEEDOR ==========
        if ($controller === 'autorizacionProveedor') {
            switch ($action) {
                case 'solicitar':
                    $ctrl->solicitar();
                    break;
                case 'exito':
                    $ctrl->exito();
                    break;
                case 'aprobacionesPendientes':
                    $ctrl->aprobacionesPendientes();
                    break;
                case 'revisar':
                    $ctrl->revisar();
                    break;
                case 'aprobar':
                    $ctrl->aprobar();
                    break;
                case 'rechazar':
                    $ctrl->rechazar();
                    break;
                case 'crearUsuario':
                    $ctrl->crearUsuario();
                    break;
                default:
                    $ctrl->solicitar();
                    break;
            }
            exit;
        }
        
        // ========== ACCIONES ESPECIALES PARA INGRESO MANUAL ==========
        if ($controller === 'ingresoManual') {
            switch ($action) {
                case 'index':
                    $ctrl->index();
                    break;
                case 'exito':
                    $ctrl->exito();
                    break;
                case 'buscarProveedor':
                    $ctrl->buscarProveedor();
                    break;
                default:
                    $ctrl->index();
                    break;
            }
            exit;
        }
        
        // Para cualquier otro controlador, ejecutar método normalmente
        if (method_exists($ctrl, $action)) {
            $ctrl->$action();
            exit;
        } else {
            // Si el método no existe, mostrar error o redirigir
            echo "<h2 style='color:red;text-align:center;margin-top:100px;'>Error: Acción '{$action}' no encontrada en controlador '{$controller}'</h2>";
            exit;
        }
    } else {
        echo "<h2 style='color:red;text-align:center;margin-top:100px;'>Error: Clase '{$className}' no existe</h2>";
        exit;
    }
} else {
    echo "<h2 style='color:red;text-align:center;margin-top:100px;'>Error: Controlador no encontrado: {$controller}</h2>";
    exit;
}
?>