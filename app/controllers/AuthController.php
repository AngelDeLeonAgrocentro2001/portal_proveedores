<?php
// app/controllers/AuthController.php
require_once BASE_PATH . 'app/models/UsuarioModel.php';

class AuthController {

    public function login() {
        $error = null;

        if (($_GET['error'] ?? '') === 'proveedor_inactivo') {
            $error = "Este proveedor fue desactivado. Contacta al administrador si crees que esto es un error.";
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $cardcode = trim($_POST['cardcode'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($cardcode) || empty($email) || empty($password)) {
                $error = "Todos los campos son obligatorios";
            } else {
                $model = new UsuarioModel();
                $user = $model->login($cardcode, $email, $password);

                if ($user) {
                    $_SESSION['user'] = $user;
                    
                    // Redirigir según el rol del usuario
                    if ($user['rol'] === 'superadmin') {
                        // Redirigir al panel de super administrador
                        header('Location: index.php?controller=superadmin&action=dashboard');
                    } elseif ($user['rol'] === 'supervisor_compras') {
                        // Redirigir al panel de administración de compras
                        header('Location: index.php?controller=admin&action=gestionarContraseñas');
                    } elseif ($user['rol'] === 'supervisor_finanzas') {
                        // Redirigir a panel de finanzas
                        header('Location: index.php?controller=finanzas&action=dashboard');
                    } elseif ($user['rol'] === 'contabilidad') {
                        // Redirigir a panel de contabilidad
                        header('Location: index.php?controller=contabilidad&action=dashboard');
                    } else {
                        // Proveedor normal
                        header('Location: index.php?controller=proveedor&action=dashboard');
                    }
                    exit;
                } else {
                    $error = "Código de cliente, correo o contraseña incorrectos";
                }
            }
        }

        require_once BASE_PATH . 'app/views/auth/login.php';
    }

    // Login para personal interno (Super Admin, Contabilidad, Finanzas, Compras/SACI/Material
    // Empaque/Transporte): solo correo y contraseña. No toca login() ni su flujo/formulario.
    public function loginStaff() {
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $error = "Correo y contraseña son obligatorios";
            } else {
                $model = new UsuarioModel();
                $user = $model->loginStaff($email, $password);

                if ($user) {
                    $_SESSION['user'] = $user;

                    // Mismo enrutamiento por rol que usa el login de proveedores
                    if ($user['rol'] === 'superadmin') {
                        header('Location: index.php?controller=superadmin&action=dashboard');
                    } elseif ($user['rol'] === 'supervisor_compras') {
                        header('Location: index.php?controller=admin&action=gestionarContraseñas');
                    } elseif ($user['rol'] === 'supervisor_finanzas') {
                        header('Location: index.php?controller=finanzas&action=dashboard');
                    } elseif ($user['rol'] === 'contabilidad') {
                        header('Location: index.php?controller=contabilidad&action=dashboard');
                    } else {
                        header('Location: index.php?controller=proveedor&action=dashboard');
                    }
                    exit;
                } else {
                    $error = "Correo o contraseña incorrectos";
                }
            }
        }

        require_once BASE_PATH . 'app/views/auth/login_staff.php';
    }

    public function sso() {
        $token     = $_GET['token'] ?? '';
        $email     = $_GET['email'] ?? '';
        $timestamp = $_GET['ts'] ?? 0;
        $redirect  = $_GET['redirect'] ?? 'index.php?controller=contabilidad&action=dashboard';

        $expectedToken = hash_hmac('sha256', $email . '|' . $timestamp, SSO_SECRET);

        if (!hash_equals($expectedToken, $token) || (time() - (int)$timestamp) > SSO_TTL) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        $model = new UsuarioModel();
        $user  = $model->getUserByEmail($email);

        if (!$user) {
            // Usuario no existe en portal_proveedores, crear sesión mínima con rol contabilidad
            $_SESSION['user'] = [
                'id'              => 0,
                'cardcode'        => '',
                'email'           => $email,
                'username'        => $email,
                'rol'             => 'contabilidad',
                'tipo_supervisor' => null,
                'area'            => null,
                'nombre'          => $email,
                'nit'             => '',
                'dias_credito'    => 30,
                'tipo_proveedor'  => 'normal'
            ];
        } else {
            $_SESSION['user'] = $user;
        }

        // Forzar rol según el módulo destino (viene de agrosistemas SSO)
        $rolMap = [
            'contabilidad' => 'contabilidad',
            'finanzas'     => 'supervisor_finanzas',
            'compras'      => 'compras',
        ];
        parse_str(parse_url($redirect, PHP_URL_QUERY) ?: $redirect, $redirectParams);
        $destCtrl = $redirectParams['controller'] ?? 'contabilidad';
        if (isset($rolMap[$destCtrl])) {
            $_SESSION['user']['rol'] = $rolMap[$destCtrl];
        }

        // Ejecutar el controlador destino directamente sin redirect
        parse_str(parse_url($redirect, PHP_URL_QUERY) ?: $redirect, $params);
        $destController = $params['controller'] ?? 'contabilidad';
        $destAction     = $params['action']     ?? 'dashboard';

        $controllerFile = BASE_PATH . "app/controllers/" . ucfirst($destController) . "Controller.php";
        if (file_exists($controllerFile)) {
            require_once $controllerFile;
            $className = ucfirst($destController) . "Controller";
            try {
                $ctrl = new $className();
                if (method_exists($ctrl, $destAction)) {
                    $ctrl->$destAction();
                } else {
                    die("SSO OK - Acción '{$destAction}' no existe en {$className}");
                }
            } catch (Exception $e) {
                die("SSO OK pero error en {$className}: " . $e->getMessage());
            }
        } else {
            die("SSO OK pero controlador no encontrado: {$controllerFile}");
        }
        exit;
    }

    // "Olvidé mi contraseña" — mismo flujo de dos pasos que ya usa agrocaja-chica: primero se
    // verifica que el correo exista (GET muestra el formulario, POST solo valida y responde
    // JSON), y luego, ya en el formulario, se pide la nueva contraseña vía changePassword().
    public function resetPassword() {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            require_once BASE_PATH . 'app/views/auth/reset-password.php';
            return;
        }

        $email = trim($_POST['email'] ?? '');
        header('Content-Type: application/json');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error' => 'Email inválido']);
            exit;
        }

        $model = new UsuarioModel();
        $user = $model->getUserByEmail($email);

        if ($user) {
            echo json_encode(['success' => true, 'message' => 'Email verificado correctamente', 'email' => $email]);
        } else {
            echo json_encode(['success' => false, 'error' => 'El email no está registrado en el sistema. Por favor, verifica tu dirección de correo.']);
        }
        exit;
    }

    // Segundo paso de "Olvidé mi contraseña": ya con el correo verificado, guarda la nueva
    // contraseña y envía un correo de confirmación (mismo patrón que agrocaja-chica).
    public function changePassword() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=auth&action=resetPassword');
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';

        header('Content-Type: application/json');

        if (empty($email) || empty($newPassword)) {
            echo json_encode(['success' => false, 'error' => 'Email y contraseña son obligatorios']);
            exit;
        }

        if (strlen($newPassword) < 6) {
            echo json_encode(['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres']);
            exit;
        }

        $model = new UsuarioModel();
        $user = $model->getUserByEmail($email);

        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
            exit;
        }

        if (!$model->actualizarPasswordPorEmail($email, $newPassword)) {
            echo json_encode(['success' => false, 'error' => 'Error al actualizar la contraseña en la base de datos']);
            exit;
        }

        require_once BASE_PATH . 'app/models/MailerService.php';
        $nombre = $user['username'] ?? $user['nombre'] ?? 'usuario';
        $cuerpoHtml = "
            <div style='font-family: Arial, sans-serif; max-width:600px; margin:0 auto; color:#333;'>
                <h2 style='color:#1d6f3c;'>Contraseña Actualizada — Portal Proveedores Agrocentro</h2>
                <p>Hola <strong>" . htmlspecialchars($nombre) . "</strong>,</p>
                <p>Tu contraseña en el Portal de Proveedores ha sido actualizada exitosamente.</p>
                <p><strong>✅ Cambio realizado con éxito</strong></p>
                <p>Si no realizaste este cambio, por favor contacta inmediatamente al administrador del sistema.</p>
                <div style='margin-top:20px; padding-top:20px; border-top:1px solid #ddd; font-size:12px; color:#666;'>
                    <p>Este es un mensaje automático, por favor no respondas.</p>
                    <p>Agrocentro &copy; " . date('Y') . "</p>
                </div>
            </div>
        ";
        $cuerpoTexto = "Hola $nombre,\n\nTu contraseña en el Portal de Proveedores ha sido actualizada exitosamente.\n\nSi no realizaste este cambio, contacta inmediatamente al administrador.\n\nAgrocentro";
        $emailEnviado = MailerService::enviarConAdjunto($email, $nombre, 'Contraseña Actualizada - Portal Proveedores', $cuerpoHtml, $cuerpoTexto);

        echo json_encode([
            'success' => true,
            'message' => $emailEnviado
                ? 'Contraseña actualizada exitosamente. Se ha enviado un correo de confirmación.'
                : 'Contraseña actualizada exitosamente.'
        ]);
        exit;
    }

    public function logout() {
        // El personal interno (login por correo/contraseña) vuelve a su propio login al salir;
        // los proveedores (login con CardCode) siguen yendo al login normal, sin cambios para ellos.
        $rolesStaff = ['superadmin', 'contabilidad', 'supervisor_finanzas', 'supervisor_compras'];
        $esStaff = in_array($_SESSION['user']['rol'] ?? '', $rolesStaff, true);

        $_SESSION = array();

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();

        if ($esStaff) {
            header('Location: index.php?controller=auth&action=loginStaff');
        } else {
            header('Location: index.php?controller=auth&action=login');
        }
        exit;
    }
}