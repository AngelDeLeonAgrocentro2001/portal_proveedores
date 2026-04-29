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
        $allowed_ips = ['127.0.0.1', '192.168.1.%', '::1'];
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
        
        // Procesar cancelación de contraseña
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar_contrasena'])) {
            $factura_id = $_POST['factura_id'] ?? 0;
            $motivo = trim($_POST['motivo_cancelacion'] ?? '');
            
            if (empty($motivo)) {
                $error = "Debe ingresar un motivo para cancelar la contraseña";
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE facturas 
                    SET contrasena_pago = NULL, 
                        fecha_inicio_credito = NULL,
                        fecha_pago_esperada = NULL,
                        contrasena_cancelada = 1,
                        motivo_cancelacion = ?,
                        fecha_cancelacion = NOW()
                    WHERE id = ? AND cardcode = ?
                ");
                
                // Obtener cardcode de la factura
                $stmtCheck = $this->pdo->prepare("SELECT cardcode FROM facturas WHERE id = ?");
                $stmtCheck->execute([$factura_id]);
                $facturaData = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                
                if ($facturaData && $stmt->execute([$motivo, $factura_id, $facturaData['cardcode']])) {
                    $success = "Contraseña cancelada correctamente";
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
                    $error = "Error al cancelar la contraseña";
                }
            }
        }
        
        // Procesar subida de archivo adicional
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_archivo'])) {
            $factura_id = $_POST['factura_id'] ?? 0;
            
            if (isset($_FILES['archivo_adicional']) && $_FILES['archivo_adicional']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = BASE_PATH . 'uploads/documentos_admin/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                $ext = strtolower(pathinfo($_FILES['archivo_adicional']['name'], PATHINFO_EXTENSION));
                if ($ext !== 'pdf') {
                    $error = "Solo se permiten archivos PDF";
                } else {
                    $nuevoNombre = 'admin_' . uniqid() . '.pdf';
                    $rutaFinal = $uploadDir . $nuevoNombre;
                    
                    if (move_uploaded_file($_FILES['archivo_adicional']['tmp_name'], $rutaFinal)) {
                        $rutaRelativa = 'uploads/documentos_admin/' . $nuevoNombre;
                        
                        $stmt = $this->pdo->prepare("
                            INSERT INTO documentos_admin 
                            (factura_id, nombre_original, ruta_archivo, fecha_subida, usuario)
                            VALUES (?, ?, ?, NOW?, ?)
                        ");
                        $stmt->execute([
                            $factura_id,
                            $_FILES['archivo_adicional']['name'],
                            $rutaRelativa,
                            $_SESSION['admin_agrosistemas_user'] ?? 'admin'
                        ]);
                        
                        $success = "Documento subido correctamente";
                        
                        // Recargar factura
                        $stmt = $this->pdo->prepare("
                            SELECT f.*, p.nombre as proveedor_nombre, p.cardcode
                            FROM facturas f
                            JOIN proveedores p ON f.cardcode = p.cardcode
                            WHERE f.id = ?
                        ");
                        $stmt->execute([$factura_id]);
                        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $error = "Error al subir el archivo";
                    }
                }
            } else {
                $error = "Debe seleccionar un archivo PDF";
            }
        }
        
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
                
                if ($factura) {
                    // Obtener documentos adicionales
                    $stmtDocs = $this->pdo->prepare("
                        SELECT * FROM documentos_admin 
                        WHERE factura_id = ? 
                        ORDER BY fecha_subida DESC
                    ");
                    $stmtDocs->execute([$factura['id']]);
                    $documentos = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);
                    $factura['documentos'] = $documentos;
                } else {
                    $error = "Factura no encontrada: " . htmlspecialchars($numero_factura);
                }
            }
        }
        
        // Listar últimas facturas reportadas
        $stmt = $this->pdo->prepare("
            SELECT f.id, f.numero_factura, f.fecha_emision, f.monto, f.estado, 
                   f.contrasena_pago, f.fecha_inicio_credito, f.contrasena_cancelada,
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
    
    // Descargar documento de administración
    public function descargarDocumento() {
        if (!$this->ipPermitida() && (!isset($_SESSION['admin_agrosistemas']) || $_SESSION['admin_agrosistemas'] !== true)) {
            die("Acceso no autorizado");
        }
        
        $id = $_GET['id'] ?? 0;
        
        $stmt = $this->pdo->prepare("SELECT * FROM documentos_admin WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$doc) {
            die("Documento no encontrado");
        }
        
        $rutaCompleta = BASE_PATH . $doc['ruta_archivo'];
        
        if (!file_exists($rutaCompleta)) {
            die("El archivo no existe en el servidor");
        }
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $doc['nombre_original'] . '"');
        header('Content-Length: ' . filesize($rutaCompleta));
        readfile($rutaCompleta);
        exit;
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

    // Agregar este método en AdminController.php
// En AdminController.php - Método para generar PDF desde admin
// En AdminController.php - Método para generar PDF desde admin (VERSIÓN CORREGIDA)
public function pdfContraseña() {
    // Verificar acceso de admin
    if (!$this->ipPermitida() && (!isset($_SESSION['admin_agrosistemas']) || $_SESSION['admin_agrosistemas'] !== true)) {
        die("Acceso no autorizado");
    }
    
    $id = $_GET['id'] ?? 0;
    if (!$id) {
        die("ID de factura no válido");
    }
    
    // Obtener factura con datos del proveedor - CONSULTA DIRECTA, sin usar FacturaModel
    $stmt = $this->pdo->prepare("
        SELECT f.*, p.nombre as proveedor_nombre, p.cardcode, p.nit
        FROM facturas f
        JOIN proveedores p ON f.cardcode = p.cardcode
        WHERE f.id = ?
    ");
    $stmt->execute([$id]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$factura) {
        die("Factura no encontrada con ID: " . $id);
    }
    
    // Verificar que tenga contraseña
    if (empty($factura['contrasena_pago'])) {
        die("Esta factura no tiene contraseña generada");
    }
    
    // Limpiar el número de orden de compra - si viene como JSON array
    $ordenCompra = $factura['ordenes_relacionadas'] ?? '';
    if (preg_match('/\[\"(\d+)\"\]/', $ordenCompra, $matches)) {
        $ordenCompra = $matches[1];
    } elseif (preg_match('/\[(\d+)\]/', $ordenCompra, $matches)) {
        $ordenCompra = $matches[1];
    }
    
    // ====================== GENERAR PDF ======================
    require_once BASE_PATH . 'vendor/tecnickcom/tcpdf/tcpdf.php';
    
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Agrocentro');
    $pdf->SetAuthor('Portal Proveedores - Admin');
    $pdf->SetTitle('Contraseña de Pago - ' . $factura['numero_factura']);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->AddPage();
    
    // Logo usando URL externa
    $logoUrl = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSkLb4zCuSBqdoqYloQhjlciiOINIhOwZrJIA&s';
    $logoContent = @file_get_contents($logoUrl);
    if ($logoContent !== false) {
        $tempLogo = tempnam(sys_get_temp_dir(), 'logo_');
        file_put_contents($tempLogo, $logoContent);
        $pdf->Image($tempLogo, 15, 15, 45);
        unlink($tempLogo);
    } else {
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetXY(15, 15);
        $pdf->Cell(45, 20, 'AGROCENTRO', 0, 0, 'C');
    }
    
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'Agrocentro', 0, 1, 'R');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, '11 calle 6-44 zona 10 Oficina 701 Edificio Airali Guatemala', 0, 1, 'R');
    $pdf->Cell(0, 5, 'Tel: 2319-3200 / 2319-3210', 0, 1, 'R');
    
    $pdf->Ln(10);
    
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'RECEPCIÓN DE FACTURAS: DÍA LUNES', 0, 1);
    $pdf->Cell(0, 8, 'DÍA DE PAGO: VIERNES 8:00-12:00 y 14:00-16:00', 0, 1);
    $pdf->Cell(0, 8, 'Quetzales', 0, 1);
    
    $pdf->Ln(5);
    
    // Si la contraseña está cancelada, agregar nota
    if (!empty($factura['contrasena_cancelada'])) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(255, 0, 0);
        $pdf->Cell(0, 8, '*** CONTRASEÑA CANCELADA ***', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, 'Motivo: ' . ($factura['motivo_cancelacion'] ?? 'No especificado'), 0, 1, 'C');
        $pdf->Cell(0, 5, 'Fecha cancelación: ' . date('d/m/Y H:i', strtotime($factura['fecha_cancelacion'] ?? 'now')), 0, 1, 'C');
        $pdf->Ln(5);
    }
    
    // Datos del proveedor
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(40, 7, 'CÓDIGO:', 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 7, $factura['cardcode'], 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(40, 7, 'PROVEEDOR:', 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 7, mb_substr($factura['proveedor_nombre'] ?? 'N/A', 0, 60), 0, 1);
    
    $pdf->Cell(40, 7, 'TIPO DE DOCUMENTO:', 0);
    $pdf->Cell(0, 7, 'FACTURA', 0, 1);
    
    $pdf->Ln(8);
    
    // Tabla
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(60, 8, 'Orden de Compra', 1, 0, 'C');
    $pdf->Cell(50, 8, 'Documento', 1, 0, 'C');
    $pdf->Cell(30, 8, 'Fecha', 1, 0, 'C');
    $pdf->Cell(35, 8, 'Valor', 1, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(60, 8, $ordenCompra ?: 'N/A', 1, 0, 'C');
    $pdf->Cell(50, 8, $factura['numero_factura'], 1, 0, 'C');
    $pdf->Cell(30, 8, date('d/m/Y', strtotime($factura['fecha_emision'])), 1, 0, 'C');
    $pdf->Cell(35, 8, 'Q ' . number_format($factura['monto'], 2), 1, 1, 'C');
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(140, 8, 'TOTAL', 1, 0, 'R');
    $pdf->Cell(35, 8, 'Q ' . number_format($factura['monto'], 2), 1, 1, 'C');
    
    $pdf->Ln(10);
    
    // Información final
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(60, 7, 'FECHA DE PAGO:', 0);
    $fechaPago = !empty($factura['fecha_pago_esperada']) ? date('d/m/Y', strtotime($factura['fecha_pago_esperada'])) : 'No calculada';
    $pdf->Cell(0, 7, $fechaPago, 0, 1);
    
    $pdf->Cell(60, 7, 'FECHA INICIO CRÉDITO:', 0);
    $fechaInicio = !empty($factura['fecha_inicio_credito']) ? date('d/m/Y', strtotime($factura['fecha_inicio_credito'])) : 'No definida';
    $pdf->Cell(0, 7, $fechaInicio, 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(60, 10, 'No. CONTRASEÑA:', 0);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(0, 100, 0);
    $pdf->Cell(0, 10, $factura['contrasena_pago'], 0, 1);
    
    $pdf->SetTextColor(0, 0, 0);
    
    // Si está cancelada, agregar sello
    if (!empty($factura['contrasena_cancelada'])) {
        $pdf->SetY($pdf->GetY() + 10);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(255, 0, 0);
        $pdf->Cell(0, 8, 'DOCUMENTO ANULADO / CONTRASEÑA CANCELADA', 0, 1, 'C');
    }
    
    // Limpiar buffer antes de enviar PDF
    if (ob_get_length()) {
        ob_clean();
    }
    
    // Salida
    $filename = 'Contraseña_' . $factura['numero_factura'] . '.pdf';
    $pdf->Output($filename, 'I');
    exit;
}
}
?>