<?php
// database/DatabasePortal.php

require_once BASE_PATH . 'config/config.php';

class DatabasePortal {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST_LOCAL . ";dbname=" . DB_PORTAL . ";charset=utf8mb4",
                DB_USER_LOCAL,
                DB_PASS_LOCAL          // ← Contraseña correcta del portal
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("✅ Conexión exitosa a base de datos PORTAL_PROVEEDORES");
            }
        } catch (PDOException $e) {
            error_log("❌ Error al conectar a PORTAL_PROVEEDORES: " . $e->getMessage());
            throw new Exception("Error de conexión a la base de datos del portal: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new DatabasePortal();
        }
        return self::$instance;
    }

    public function getPdo() {
        return $this->pdo;
    }
}