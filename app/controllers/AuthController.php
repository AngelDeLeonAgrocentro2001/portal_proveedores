<?php
// app/controllers/AuthController.php
require_once BASE_PATH . 'app/models/UsuarioModel.php';

class AuthController {

    public function login() {
        $error = null;

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
                    header('Location: index.php?controller=proveedor&action=dashboard');
                    exit;
                } else {
                    $error = "Código de cliente, correo o contraseña incorrectos";
                }
            }
        }

        require_once BASE_PATH . 'app/views/auth/login.php';
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