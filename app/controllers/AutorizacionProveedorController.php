<?php
// app/controllers/AutorizacionProveedorController.php
require_once BASE_PATH . 'app/models/ProveedorAutorizacionModel.php';

class AutorizacionProveedorController {
    private $model;
    private $pdo;
    
    public function __construct() {
        $this->pdo = DatabasePortal::getInstance()->getPdo();
        $this->model = new ProveedorAutorizacionModel();
    }
    
    // Formulario de solicitud para nuevos proveedores
    public function solicitar() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $resultado = $this->model->solicitarAutorizacion($_POST, $_FILES);
            
            if ($resultado['success']) {
                $_SESSION['registro_exitoso'] = $resultado['message'];
                header('Location: index.php?controller=autorizacionProveedor&action=exito');
                exit;
            } else {
                $error = $resultado['message'];
            }
        }
        
        require_once BASE_PATH . 'app/views/autorizacion/solicitar.php';
    }
    
    // Página de éxito después de solicitar
    public function exito() {
        $mensaje = $_SESSION['registro_exitoso'] ?? 'Solicitud enviada correctamente';
        unset($_SESSION['registro_exitoso']);
        require_once BASE_PATH . 'app/views/autorizacion/exito.php';
    }
    
    // Panel de aprobaciones (para Compras y Finanzas)
    public function aprobacionesPendientes() {
        $this->verificarAcceso(['admin', 'compras', 'finanzas']);
        
        $rol = $_SESSION['user']['rol'];
        $solicitudes = $this->model->getSolicitudesPendientes($rol);
        
        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/autorizacion/aprobaciones-pendientes.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }
    
    // Revisar detalle de un proveedor
    public function revisar() {
        $this->verificarAcceso(['admin', 'compras', 'finanzas']);
        
        $id = $_GET['id'] ?? 0;
        $proveedor = $this->model->getProveedorById($id);
        
        if (!$proveedor) {
            die("Proveedor no encontrado");
        }
        
        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/autorizacion/revisar-proveedor.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }
    
    // Acción de aprobar
    public function aprobar() {
        $this->verificarAcceso(['admin', 'compras', 'finanzas']);
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=autorizacionProveedor&action=aprobacionesPendientes');
            exit;
        }
        
        $id = $_POST['id'] ?? 0;
        $rol = $_SESSION['user']['rol'];
        $usuario = $_SESSION['user']['username'];
        
        $resultado = $this->model->aprobarProveedor($id, $rol, $usuario);
        
        $_SESSION['aprobacion_resultado'] = $resultado;
        header("Location: index.php?controller=autorizacionProveedor&action=revisar&id={$id}");
        exit;
    }
    
    // Acción de rechazar
    public function rechazar() {
        $this->verificarAcceso(['admin', 'compras', 'finanzas']);
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=autorizacionProveedor&action=aprobacionesPendientes');
            exit;
        }
        
        $id = $_POST['id'] ?? 0;
        $motivo = $_POST['motivo_rechazo'] ?? '';
        $usuario = $_SESSION['user']['username'];
        
        if (empty($motivo)) {
            $_SESSION['error'] = "Debe ingresar un motivo de rechazo";
            header("Location: index.php?controller=autorizacionProveedor&action=revisar&id={$id}");
            exit;
        }
        
        $resultado = $this->model->rechazarProveedor($id, $motivo, $usuario);
        
        $_SESSION['aprobacion_resultado'] = $resultado;
        header("Location: index.php?controller=autorizacionProveedor&action=aprobacionesPendientes");
        exit;
    }
    
    // Crear usuario para proveedor ya aprobado
    public function crearUsuario() {
        $this->verificarAcceso(['admin']);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $proveedor_id = $_POST['proveedor_id'] ?? 0;
            $email = $_POST['email'] ?? '';
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $rol = $_POST['rol'] ?? 'crear_contrasenas';
            
            if ($this->model->crearUsuarioInicial($proveedor_id, $email, $username, $password, $rol)) {
                $_SESSION['success'] = "Usuario creado correctamente";
            } else {
                $_SESSION['error'] = "Error al crear usuario";
            }
            
            header("Location: index.php?controller=autorizacionProveedor&action=revisar&id={$proveedor_id}");
            exit;
        }
    }
    
    private function verificarAcceso($rolesPermitidos) {
        if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], $rolesPermitidos)) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }
    }
}