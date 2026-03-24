<?php
// app/controllers/AuthController.php
require_once BASE_PATH . 'app/models/UsuarioModel.php';

class AuthController {

    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            $model = new UsuarioModel();
            $user = $model->login($username, $password);

            if ($user) {
                $_SESSION['user'] = $user;
                // Redirección CORRECTA (importante)
                header('Location: index.php?controller=proveedor&action=dashboard');
                exit;
            } else {
                $error = "Usuario o contraseña incorrectos";
            }
        }
        require_once BASE_PATH . 'app/views/auth/login.php';
    }

    public function logout() {
        session_destroy();
        header('Location: index.php');
        exit;
    }
}