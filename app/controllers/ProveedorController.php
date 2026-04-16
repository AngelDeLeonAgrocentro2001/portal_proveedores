<?php
// app/controllers/ProveedorController.php
require_once BASE_PATH . 'app/models/ProveedorModel.php';
require_once BASE_PATH . 'app/models/FacturaModel.php';
require_once BASE_PATH . 'app/models/UsuarioModel.php';
class ProveedorController {

            public function dashboard() {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        // Refrescar datos del usuario actual para asegurar consistencia
    $usuarioModel = new UsuarioModel();
    $userActualizado = $usuarioModel->getUserByCardcodeAndEmail(
        $_SESSION['user']['cardcode'],
        $_SESSION['user']['email']
    );
    
    if ($userActualizado) {
        // Actualizar la sesión con los datos más recientes
        $_SESSION['user']['username'] = $userActualizado['username'];
        $_SESSION['user']['rol'] = $userActualizado['rol'];
        $_SESSION['user']['nombre'] = $userActualizado['nombre'];
        $_SESSION['user']['nit'] = $userActualizado['nit'];
    }

        $cardcode = $_SESSION['user']['cardcode'];
        $rol      = $_SESSION['user']['rol'] ?? 'crear_contrasenas';

        $proveedorModel = new ProveedorModel();
        $facturaModel   = new FacturaModel();

        $proveedor = $proveedorModel->getProveedorByCardcode($cardcode);
        $resumen   = $proveedorModel->getResumenFacturas($cardcode);
        $facturas  = $proveedorModel->getUltimasFacturas($cardcode, 5);
        $pagos     = $facturaModel->getUltimosPagos($cardcode, 5);

        // Control según rol
        $mostrarPagos = in_array($rol, ['admin', 'consultas']);
        $esAdmin      = ($rol === 'admin');
        $puedeCrear   = true; // todos pueden crear contraseñas

        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/dashboard.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }

    // ====================== NUEVO: REPORTAR FACTURA ======================
                                public function reportarFactura() {
    if (!isset($_SESSION['user'])) {
        header('Location: index.php?controller=auth&action=login');
        exit;
    }

    $cardcode = $_SESSION['user']['cardcode'];
    $rol      = $_SESSION['user']['rol'] ?? 'crear_contrasenas';
    $error    = '';
    $success  = '';
    
    $preseleccion = trim($_GET['preseleccion'] ?? '');

    $proveedorModel = new ProveedorModel();
    $proveedor = $proveedorModel->getProveedorByCardcode($cardcode);
    $ordenesAbiertas = $proveedorModel->getOrdenesCompraByCardcode($cardcode, 'abierta');

    $facturasSAT = [];
    try {
        $dbCajas = DatabaseCajas::getInstance()->getPdo();
        $nit = trim($proveedor['nit'] ?? '');

        if ($nit) {
            $stmt = $dbCajas->prepare("
                SELECT serie, numero_dte, fecha_emision, gran_total, iva, nombre_emisor, usado
                FROM dte 
                WHERE nit_emisor = ?
                  AND (usado IS NULL OR usado = 'X' OR usado = '')
                ORDER BY fecha_emision DESC
            ");
            $stmt->execute([$nit]);
            $facturasSAT = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error al cargar facturas SAT: " . $e->getMessage());
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $facturaModel = new FacturaModel();
        $resultado = $facturaModel->reportarFactura($_POST, $_FILES, $cardcode);

        if ($resultado['success']) {
            // Guardamos la información para el modal con TODOS los datos necesarios
            $_SESSION['last_report'] = [
                'success'      => true,
                'contrasena'   => $resultado['contrasena'],
                'esLunes'      => $resultado['esLunes'],
                'proximoLunes' => $resultado['proximoLunes'],
                'message'      => $resultado['message']
            ];
            
            $success = "Factura reportada correctamente.";
            
            // Recargar lista de facturas SAT sin las usadas
            $facturasSAT = [];
            try {
                $dbCajas = DatabaseCajas::getInstance()->getPdo();
                $nit = trim($proveedor['nit'] ?? '');
                if ($nit) {
                    $stmt = $dbCajas->prepare("
                        SELECT serie, numero_dte, fecha_emision, gran_total, iva, nombre_emisor, usado
                        FROM dte 
                        WHERE nit_emisor = ?
                          AND (usado IS NULL OR usado = 'X' OR usado = '')
                        ORDER BY fecha_emision DESC
                    ");
                    $stmt->execute([$nit]);
                    $facturasSAT = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch (Exception $e) {
                error_log("Error recargando facturas SAT: " . $e->getMessage());
            }
        } else {
            $error = $resultado['message'];
        }
    }

    require_once BASE_PATH . 'app/views/layout/header.php';
    require_once BASE_PATH . 'app/views/proveedor/reportar-factura.php';
    require_once BASE_PATH . 'app/views/layout/footer.php';
}

    public function misFacturas() {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        $cardcode = $_SESSION['user']['cardcode'];
        $facturaModel = new FacturaModel();

        // Filtro por estado (opcional)
        $estado = $_GET['estado'] ?? '';

        $facturas = $facturaModel->getFacturasByProveedor($cardcode, $estado);

        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/mis-facturas.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }

        // Descargar archivo de forma segura
    public function descargar() {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        $id     = $_GET['id'] ?? 0;
        $tipo   = $_GET['tipo'] ?? ''; // factura, orden, constancia

        if (!$id || !in_array($tipo, ['factura', 'orden', 'constancia'])) {
            die("Acceso denegado");
        }

        $facturaModel = new FacturaModel();
        $factura = $facturaModel->getFacturaById($id, $_SESSION['user']['cardcode']);

        if (!$factura) {
            die("Factura no encontrada o no pertenece a tu cuenta");
        }

        $campo = '';
        switch ($tipo) {
            case 'factura':    $campo = 'pdf_factura'; break;
            case 'orden':      $campo = 'pdf_orden_compra'; break;
            case 'constancia': $campo = 'pdf_constancia'; break;
        }

        $rutaRelativa = $factura[$campo];
        if (empty($rutaRelativa)) {
            die("Archivo no disponible");
        }

        $rutaCompleta = BASE_PATH . 'public/' . $rutaRelativa;

        if (!file_exists($rutaCompleta)) {
            die("El archivo no existe en el servidor");
        }

        // Forzar descarga
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($rutaCompleta) . '"');
        header('Content-Length: ' . filesize($rutaCompleta));
        readfile($rutaCompleta);
        exit;
    }

        // Ver todos los pagos recibidos
    public function pagos() {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        $rol = $_SESSION['user']['rol'] ?? 'crear_contrasenas';

        // Solo admin y consultas pueden ver pagos
        if (!in_array($rol, ['admin', 'consultas'])) {
            header('Location: index.php?controller=proveedor&action=dashboard');
            exit;
        }

        $cardcode = $_SESSION['user']['cardcode'];
        $facturaModel = new FacturaModel();
        $pagos = $facturaModel->getPagosByProveedor($cardcode);

        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/pagos.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }

            public function ordenesCompra() {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        $cardcode = $_SESSION['user']['cardcode'];
        $model = new ProveedorModel();

        $estadoFiltro = $_GET['estado'] ?? 'abierta';

        $ordenes = $model->getOrdenesCompraByCardcode($cardcode, $estadoFiltro);

        $totalMonto = array_sum(array_column($ordenes, 'monto'));

        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/ordenes-compra.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }

      

    public function facturasSAT() {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        $cardcode = $_SESSION['user']['cardcode'];
        $proveedorModel = new ProveedorModel();
        $proveedor = $proveedorModel->getProveedorByCardcode($cardcode);

        $nit = trim($proveedor['nit'] ?? '');
        $facturasSAT = [];
        $errorSAT = '';

        if (empty($nit)) {
            $errorSAT = "No se encontró NIT registrado para este proveedor.";
        } else {
            try {
                $dbCajas = DatabaseCajas::getInstance()->getPdo();
                
                $sql = "SELECT 
                            serie, 
                            numero_dte, 
                            fecha_emision, 
                            gran_total, 
                            iva, 
                            nombre_emisor,
                            usado
                        FROM dte 
                        WHERE nit_emisor = :nit
                        ORDER BY fecha_emision DESC";
                        
                $stmt = $dbCajas->prepare($sql);
                $stmt->execute(['nit' => $nit]);
                $facturasSAT = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } catch (Exception $e) {
                $errorSAT = "Error al consultar facturas del SAT: " . $e->getMessage();
                error_log("❌ " . $errorSAT);
            }
        }

        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/facturas-sat.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }

        // ====================== GESTIÓN DE USUARIOS (SOLO ADMIN) ======================
    public function gestionarUsuarios() {
        if (!isset($_SESSION['user']) || $_SESSION['user']['rol'] !== 'admin') {
            header('Location: index.php?controller=proveedor&action=dashboard');
            exit;
        }

        $cardcode = $_SESSION['user']['cardcode'];
        $error = '';
        $success = '';

        $usuarioModel = new UsuarioModel();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email     = trim($_POST['email'] ?? '');
            $username  = trim($_POST['username'] ?? '');
            $password  = $_POST['password'] ?? '';
            $rol       = $_POST['rol'] ?? 'crear_contrasenas';

            if (empty($email) || empty($username) || empty($password)) {
                $error = "Todos los campos son obligatorios";
            } else {
                $resultado = $usuarioModel->crearUsuario($cardcode, $email, $username, $password, $rol);

                if ($resultado) {
                    $success = "Usuario creado correctamente con rol: " . ucfirst(str_replace('_', ' ', $rol));
                } else {
                    $error = "Error al crear el usuario. Puede que el username o email ya exista.";
                }
            }
        }

        // Obtener todos los usuarios del mismo cardcode
        $usuarios = $usuarioModel->getUsuariosByCardcode($cardcode);

        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/gestionar-usuarios.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }
}