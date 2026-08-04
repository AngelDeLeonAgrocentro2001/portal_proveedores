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

    public function logout() {
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        
        header('Location: index.php?controller=auth&action=login');
        exit;
    }
}