<?php
// app/models/UsuarioModel.php
require_once BASE_PATH . 'database/DatabasePortal.php';

class UsuarioModel {
    private $pdo;

    public function __construct() {
        $this->pdo = DatabasePortal::getInstance()->getPdo();
    }

    public function login($username, $password) {
        $stmt = $this->pdo->prepare("
            SELECT u.*, p.nombre, p.cardcode 
            FROM usuarios u 
            JOIN proveedores p ON u.cardcode = p.cardcode 
            WHERE u.username = ? AND p.estado = 'activo'
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }
}