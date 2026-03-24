<?php
require_once __DIR__ . '/../config/config.php';

class DatabasePortal {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_PORTAL . ";charset=utf8mb4",
                DB_USER,
                DB_PASS
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            if (DEBUG_MODE) {
                error_log("✅ Conexión exitosa a DB_PORTAL: " . DB_PORTAL);
            }
        } catch (PDOException $e) {
            error_log("❌ Error DB Portal: " . $e->getMessage());
            throw new Exception("Error al conectar a la base de datos del portal");
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