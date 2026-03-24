<?php
require_once __DIR__ . '/../config/config.php';

class DatabaseCajas {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_CAJAS . ";charset=utf8mb4",
                DB_USER,
                DB_PASS
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            if (DEBUG_MODE) {
                error_log("✅ Conexión exitosa a DB_CAJAS: " . DB_CAJAS);
            }
        } catch (PDOException $e) {
            error_log("❌ Error DB Cajas Chicas: " . $e->getMessage());
            throw new Exception("Error al conectar a la base de datos cajas_chicas");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new DatabaseCajas();
        }
        return self::$instance;
    }

    public function getPdo() {
        return $this->pdo;
    }
}