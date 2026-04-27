<?php
// app/controllers/AdminController.php
require_once BASE_PATH . 'app/models/FacturaModel.php';

class AdminController {
    private $pdo;
    
    public function __construct() {
        $this->pdo = DatabasePortal::getInstance()->getPdo();
    }
    
    // Verificar si la IP está permitida
    private function ipPermitida() {
        $allowed_ips = ['127.0.0.1', '192.168.1.%', '::1']; // IPs permitidas
        $client_ip = $_SERVER['REMOTE_ADDR'];
        
        foreach ($allowed_ips as $allowed) {
            if (strpos($allowed, '%') !== false) {
                $allowed_prefix = str_replace('%', '', $allowed);
                if (strpos($client_ip, $allowed_prefix) === 0) {
                    return true;
                }
            } elseif ($client_ip === $allowed) {
                return true;
            }
        }
        return false;
    }
    
    public function gestionarContraseñas() {
        // Verificar IP o autenticación especial
        if (!$this->ipPermitida() && (!isset($_SESSION['admin_agrosistemas']) || $_SESSION['admin_agrosistemas'] !== true)) {
            // Mostrar login especial
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && $_POST['password'] === 'Agrosistemas2026!') {
                $_SESSION['admin_agrosistemas'] = true;
            } else {
                $this->mostrarLoginAgrosistemas();
                return;
            }
        }
        
        $error = '';
        $success = '';
        $factura = null;
        $facturaModel = new FacturaModel();
        
        // Buscar factura por número
        if (isset($_GET['buscar']) || isset($_POST['numero_factura'])) {
            $numero_factura = $_GET['buscar'] ?? $_POST['numero_factura'] ?? '';
            
            if (!empty($numero_factura)) {
                $stmt = $this->pdo->prepare("
                    SELECT f.*, p.nombre as proveedor_nombre, p.cardcode
                    FROM facturas f
                    JOIN proveedores p ON f.cardcode = p.cardcode
                    WHERE f.numero_factura = ?
                ");
                $stmt->execute([$numero_factura]);
                $factura = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$factura) {
                    $error = "Factura no encontrada: " . htmlspecialchars($numero_factura);
                }
            }
        }
        
        // Actualizar contraseña manualmente
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar'])) {
            $factura_id = $_POST['factura_id'] ?? 0;
            $nueva_contrasena = trim($_POST['nueva_contrasena'] ?? '');
            $fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-d');
            
            if (empty($nueva_contrasena)) {
                $error = "Debe ingresar una contraseña";
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE facturas 
                    SET contrasena_pago = ?, fecha_inicio_credito = ?
                    WHERE id = ?
                ");
                if ($stmt->execute([$nueva_contrasena, $fecha_inicio, $factura_id])) {
                    $success = "Contraseña actualizada correctamente";
                    // Recargar datos
                    $stmt = $this->pdo->prepare("
                        SELECT f.*, p.nombre as proveedor_nombre, p.cardcode
                        FROM facturas f
                        JOIN proveedores p ON f.cardcode = p.cardcode
                        WHERE f.id = ?
                    ");
                    $stmt->execute([$factura_id]);
                    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = "Error al actualizar la contraseña";
                }
            }
        }
        
        // Listar últimas facturas reportadas
        $stmt = $this->pdo->prepare("
            SELECT f.id, f.numero_factura, f.fecha_emision, f.monto, f.estado, 
                   f.contrasena_pago, f.fecha_inicio_credito,
                   p.nombre as proveedor_nombre, p.cardcode
            FROM facturas f
            JOIN proveedores p ON f.cardcode = p.cardcode
            WHERE f.estado != 'pagada'
            ORDER BY f.fecha_emision DESC
            LIMIT 50
        ");
        $stmt->execute();
        $ultimas_facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        require_once BASE_PATH . 'app/views/admin/gestionar-contrasenas.php';
    }
    
    private function mostrarLoginAgrosistemas() {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Acceso Agrosistemas</title>
            <style>
                body {
                    background: #1a1a2e;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    font-family: Arial, sans-serif;
                }
                .login-box {
                    background: white;
                    padding: 40px;
                    border-radius: 10px;
                    box-shadow: 0 0 30px rgba(0,0,0,0.3);
                    width: 350px;
                    text-align: center;
                }
                .login-box h2 {
                    color: #006400;
                    margin-bottom: 20px;
                }
                .login-box input {
                    width: 100%;
                    padding: 12px;
                    margin: 10px 0;
                    border: 1px solid #ccc;
                    border-radius: 5px;
                }
                .login-box button {
                    width: 100%;
                    padding: 12px;
                    background: #006400;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                }
                .error {
                    color: red;
                    margin-top: 10px;
                }
            </style>
        </head>
        <body>
            <div class="login-box">
                <h2>🔐 Acceso Agrosistemas</h2>
                <form method="POST">
                    <input type="password" name="password" placeholder="Contraseña de acceso" required autofocus>
                    <button type="submit">Ingresar</button>
                </form>
                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                    <div class="error">Contraseña incorrecta</div>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    public function logoutAgrosistemas() {
        $_SESSION['admin_agrosistemas'] = false;
        session_destroy();
        header('Location: index.php?controller=admin&action=gestionarContraseñas');
        exit;
    }
}
?>