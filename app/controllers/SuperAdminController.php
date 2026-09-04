<?php
// app/controllers/SuperAdminController.php
require_once BASE_PATH . 'app/models/SuperAdminModel.php';

class SuperAdminController {
    private $model;

    const ROLES_VALIDOS = [
        'admin', 'consultas', 'crear_contrasenas', 'supervisor_compras',
        'supervisor_finanzas', 'contabilidad', 'superadmin'
    ];

    public function __construct() {
        $this->verificarAcceso();
        $this->model = new SuperAdminModel();
    }

    // Acceso restringido exclusivamente al rol 'superadmin' (muy pocas cuentas deben tenerlo)
    private function verificarAcceso() {
        if (!isset($_SESSION['user']) || ($_SESSION['user']['rol'] ?? '') !== 'superadmin') {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }
    }

    private function flash($tipo, $mensaje) {
        $_SESSION['superadmin_flash'][$tipo] = $mensaje;
    }

    private function redirectDashboard() {
        header('Location: index.php?controller=superadmin&action=dashboard');
        exit;
    }

    private function mensajeErrorDuplicado(PDOException $e, $campo) {
        if ($e->getCode() == 23000) {
            return "Ya existe un registro con ese $campo (debe ser único)";
        }
        return 'Error de base de datos: ' . $e->getMessage();
    }

    public function dashboard() {
        $flash = $_SESSION['superadmin_flash'] ?? [];
        unset($_SESSION['superadmin_flash']);
        $error = $flash['error'] ?? '';
        $success = $flash['success'] ?? '';

        $proveedores = $this->model->getProveedores();
        $usuarios = $this->model->getUsuarios();

        require_once BASE_PATH . 'app/views/superadmin/dashboard.php';
    }

    // ==================== PROVEEDORES ====================

    public function crearProveedor() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirectDashboard();

        $cardcode = trim($_POST['cardcode'] ?? '');
        $nit = trim($_POST['nit'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');

        if ($cardcode === '' || $nit === '' || $nombre === '') {
            $this->flash('error', 'CardCode, NIT y Nombre son obligatorios');
            $this->redirectDashboard();
        }

        $data = [
            'cardcode' => $cardcode,
            'nit' => $nit,
            'nombre' => $nombre,
            'direccion' => trim($_POST['direccion'] ?? ''),
            'telefono' => trim($_POST['telefono'] ?? '') ?: null,
            'email' => trim($_POST['email'] ?? '') ?: null,
            'dias_credito' => (int)($_POST['dias_credito'] ?? 30),
            'tipo_proveedor' => $_POST['tipo_proveedor'] ?? 'normal',
        ];
        $dobleFactura = isset($_POST['doble_factura']);

        try {
            $this->model->crearProveedor($data);
            $this->model->setDobleFactura($cardcode, $nombre, $dobleFactura);
            $this->flash('success', "Proveedor $cardcode creado correctamente");
        } catch (PDOException $e) {
            $this->flash('error', $this->mensajeErrorDuplicado($e, 'CardCode/NIT'));
        }
        $this->redirectDashboard();
    }

    // AJAX: trae los días de crédito reales de SAP para un CardCode, usado en el formulario
    // "Nuevo Proveedor" para autocompletar el campo en vez de digitarlos a mano. No afecta
    // crearProveedor()/actualizarProveedor(): el campo dias_credito sigue siendo un input normal
    // que se envía tal cual en el POST, esto solo lo rellena antes de que el usuario lo confirme.
    public function buscarDiasCreditoSAP() {
        header('Content-Type: application/json');

        $cardcode = trim($_GET['cardcode'] ?? '');
        if ($cardcode === '') {
            echo json_encode(['success' => false, 'message' => 'CardCode requerido']);
            exit;
        }

        $resultado = $this->model->getDiasCreditoSAP($cardcode);

        if ($resultado === null) {
            echo json_encode(['success' => false, 'message' => 'No se encontró ese CardCode en SAP']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'cardname' => $resultado['cardname'],
            'dias_credito' => $resultado['dias_credito']
        ]);
        exit;
    }

    public function actualizarProveedor() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirectDashboard();

        $id = (int)($_POST['id'] ?? 0);
        if (!$id) $this->redirectDashboard();

        $data = [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'direccion' => trim($_POST['direccion'] ?? ''),
            'telefono' => trim($_POST['telefono'] ?? '') ?: null,
            'email' => trim($_POST['email'] ?? '') ?: null,
            'dias_credito' => (int)($_POST['dias_credito'] ?? 30),
            'tipo_proveedor' => $_POST['tipo_proveedor'] ?? 'normal',
        ];
        $dobleFactura = isset($_POST['doble_factura']);

        try {
            $this->model->actualizarProveedor($id, $data);

            $proveedor = $this->model->getProveedorById($id);
            if ($proveedor) {
                $this->model->setDobleFactura($proveedor['cardcode'], $data['nombre'], $dobleFactura);
            }

            $this->flash('success', 'Proveedor actualizado correctamente');
        } catch (PDOException $e) {
            $this->flash('error', 'Error al actualizar proveedor: ' . $e->getMessage());
        }
        $this->redirectDashboard();
    }

    // "Eliminar" un proveedor es un soft-delete (estado='inactivo'): un DELETE real fallaría en cuanto
    // exista alguna factura/orden/usuario asociado a ese cardcode (llaves foráneas en facturas,
    // ordenes_compra y usuarios), y perder el historial de un proveedor real sería irreversible.
    public function cambiarEstadoProveedor() {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $estado = (($_POST['estado'] ?? $_GET['estado'] ?? '') === 'activo') ? 'activo' : 'inactivo';

        if ($id) {
            $this->model->cambiarEstadoProveedor($id, $estado);
            $this->flash('success', 'Estado del proveedor actualizado');
        }
        $this->redirectDashboard();
    }

    // ==================== USUARIOS ====================

    public function crearUsuario() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirectDashboard();

        $cardcode = trim($_POST['cardcode'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $rol = $_POST['rol'] ?? 'crear_contrasenas';
        $tipoSupervisor = trim($_POST['tipo_supervisor'] ?? '') ?: null;
        $area = trim($_POST['area'] ?? '') ?: null;

        // Los autorizadores de Finanzas y Contabilidad son personal interno de Agrocentro, no
        // están ligados a ningún proveedor específico — no tiene sentido pedirles un CardCode.
        // La columna usuarios.cardcode tiene FOREIGN KEY hacia proveedores.cardcode (no admite
        // vacío ni un valor que no exista), así que en vez de dejarlo en blanco se usa el
        // proveedor centinela 'INTERNO' (creado para este fin, estado inactivo para que no
        // aparezca en listados de proveedores reales).
        $rolesSinCardcode = ['supervisor_finanzas', 'contabilidad'];
        $requiereCardcode = !in_array($rol, $rolesSinCardcode, true);

        if (($requiereCardcode && $cardcode === '') || $email === '' || $username === '' || $password === '') {
            $this->flash('error', 'Correo, usuario y contraseña son obligatorios' . ($requiereCardcode ? ' (y CardCode)' : ''));
            $this->redirectDashboard();
        }
        if (!in_array($rol, self::ROLES_VALIDOS, true)) {
            $this->flash('error', 'Rol inválido');
            $this->redirectDashboard();
        }
        if (!$requiereCardcode && $cardcode === '') {
            $cardcode = 'INTERNO';
        }
        if ($requiereCardcode && !$this->model->cardcodeExiste($cardcode)) {
            $this->flash('error', "El CardCode '$cardcode' no existe en Proveedores. Créalo primero.");
            $this->redirectDashboard();
        }

        $data = [
            'cardcode' => $cardcode,
            'email' => $email,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'rol' => $rol,
            'tipo_supervisor' => $tipoSupervisor,
            'area' => $area,
        ];

        try {
            $this->model->crearUsuario($data);
            $this->flash('success', "Usuario '$username' creado correctamente");
        } catch (PDOException $e) {
            $this->flash('error', $this->mensajeErrorDuplicado($e, 'nombre de usuario'));
        }
        $this->redirectDashboard();
    }

    public function actualizarUsuario() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirectDashboard();

        $id = (int)($_POST['id'] ?? 0);
        if (!$id) $this->redirectDashboard();

        $rol = $_POST['rol'] ?? 'crear_contrasenas';
        if (!in_array($rol, self::ROLES_VALIDOS, true)) {
            $this->flash('error', 'Rol inválido');
            $this->redirectDashboard();
        }

        $rolesSinCardcode = ['supervisor_finanzas', 'contabilidad'];
        $cardcodePost = trim($_POST['cardcode'] ?? '');
        if (in_array($rol, $rolesSinCardcode, true) && $cardcodePost === '') {
            $cardcodePost = 'INTERNO';
        }

        $password = $_POST['password'] ?? '';
        $data = [
            'cardcode' => $cardcodePost,
            'email' => trim($_POST['email'] ?? ''),
            'username' => trim($_POST['username'] ?? ''),
            'rol' => $rol,
            'tipo_supervisor' => trim($_POST['tipo_supervisor'] ?? '') ?: null,
            'area' => trim($_POST['area'] ?? '') ?: null,
            'password_hash' => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
        ];

        try {
            $this->model->actualizarUsuario($id, $data);
            $this->flash('success', 'Usuario actualizado correctamente');
        } catch (PDOException $e) {
            $this->flash('error', $this->mensajeErrorDuplicado($e, 'nombre de usuario'));
        }
        $this->redirectDashboard();
    }

    public function eliminarUsuario() {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

        // No permitir que un superadmin se elimine a sí mismo por accidente y se quede sin acceso
        if ($id && $id === (int)($_SESSION['user']['id'] ?? 0)) {
            $this->flash('error', 'No puedes eliminar tu propia cuenta');
            $this->redirectDashboard();
        }

        if ($id) {
            try {
                $this->model->eliminarUsuario($id);
                $this->flash('success', 'Usuario eliminado correctamente');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $this->flash('error', 'No se puede eliminar: este usuario tiene facturas asociadas en el sistema. Considera cambiarle el rol en vez de eliminarlo.');
                } else {
                    $this->flash('error', 'Error al eliminar usuario: ' . $e->getMessage());
                }
            }
        }
        $this->redirectDashboard();
    }
}
