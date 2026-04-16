<?php
// database/DatabaseCajas.php - Conexión a la base de PRODUCCIÓN (REMOTA)

require_once BASE_PATH . 'config/config.php';

class DatabaseCajas {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            // 🔥 USAR LA IP DEL SERVIDOR DE PRODUCCIÓN
            $host = DB_HOST_LOCAL;      // IP del servidor remoto
            $user = DB_USER_LOCAL;      // Usuario de producción
            $pass = DB_PASS_LOCAL;      // Contraseña de producción
            $db   = DB_CAJAS;          // Nombre de la base (cajas_chicas)
            
            // Opcional: Si el puerto es diferente a 3306
            $port = 3306;  // Puerto por defecto de MySQL, cambiar si es necesario
            
            $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8";
            
            error_log("🔌 Conectando a base de datos PRODUCCIÓN: $host -> $db");
            
            $this->pdo = new PDO($dsn, $user, $pass);
            
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Forzar UTF-8
            $this->pdo->exec("SET NAMES utf8");
            
            error_log("✅ Conexión exitosa a CAJAS_CHICAS (PRODUCCIÓN REMOTA)");

        } catch (PDOException $e) {
            error_log("❌ Error al conectar a CAJAS_CHICAS (PRODUCCIÓN): " . $e->getMessage());
            throw new Exception("No se pudo conectar a la base de datos SAT de producción: " . $e->getMessage());
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