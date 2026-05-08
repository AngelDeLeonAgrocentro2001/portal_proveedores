<?php
// app/controllers/ContabilidadController.php
require_once BASE_PATH . 'app/models/FacturaModel.php';

class ContabilidadController {
    private $pdo;
    
    public function __construct() {
        $this->pdo = DatabasePortal::getInstance()->getPdo();
        
        // Verificar que el usuario tenga rol de contabilidad o admin
        if (!isset($_SESSION['user']) || 
            !in_array($_SESSION['user']['rol'], ['contabilidad', 'admin'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }
    }
    
    public function dashboard() {
    $error = '';
    $success = '';
    $factura = null;
    
    // Procesar envío a SAP
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_sap'])) {
        $factura_id = $_POST['factura_id'] ?? 0;
        $comprobante_sap = trim($_POST['comprobante_sap'] ?? '');
        $observaciones = $_POST['observaciones'] ?? '';
        $usuario = $_SESSION['user']['username'] ?? 'contabilidad';
        
        if (!$factura_id) {
            $error = "ID de factura no válido";
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE facturas 
                SET estado = 'en_sap',
                    enviado_sap = 1,
                    fecha_envio_sap = NOW(),
                    enviado_por = ?,
                    comprobante_sap = ?,
                    observaciones_contabilidad = CONCAT(IFNULL(observaciones_contabilidad, ''), '\n[', NOW(), '] ', ?, ' Enviado a SAP: ', ?)
                WHERE id = ?
            ");
            
            if ($stmt->execute([$usuario, $comprobante_sap, $usuario, $observaciones, $factura_id])) {
                $success = "Factura enviada a SAP correctamente";
                $factura = $this->getFacturaById($factura_id);
            } else {
                $error = "Error al enviar a SAP";
            }
        }
    }
    
    // Procesar rechazo de factura (Contabilidad) - CON LIBERACIÓN DE DTE
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rechazar_factura'])) {
        $factura_id = $_POST['factura_id'] ?? 0;
        $motivo = $_POST['motivo_rechazo'] ?? '';
        $usuario = $_SESSION['user']['username'] ?? 'contabilidad';
        
        if (!$factura_id || empty($motivo)) {
            $error = "Debe ingresar un motivo de rechazo";
        } else {
            $this->pdo->beginTransaction();
            
            try {
                // Obtener datos de la factura principal
                $stmt = $this->pdo->prepare("
                    SELECT f.numero_factura, f.cardcode, f.ordenes_relacionadas, p.nit 
                    FROM facturas f
                    JOIN proveedores p ON f.cardcode = p.cardcode
                    WHERE f.id = ?
                ");
                $stmt->execute([$factura_id]);
                $factura_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($factura_data) {
                    // Liberar factura SAT principal - CAMBIAR DE 'Y' a 'X'
                    $partes = explode(' ', trim($factura_data['numero_factura']), 2);
                    $serie = trim($partes[0] ?? '');
                    $numero_dte = trim($partes[1] ?? $factura_data['numero_factura']);
                    
                    if ($serie && $numero_dte && !empty($factura_data['nit'])) {
                        try {
                            $dbCajas = DatabaseCajas::getInstance()->getPdo();
                            
                            $stmtDte = $dbCajas->prepare("
                                UPDATE dte 
                                SET usado = 'X'
                                WHERE nit_emisor = ? 
                                  AND serie = ? 
                                  AND numero_dte = ?
                                  AND usado = 'Y'
                            ");
                            $stmtDte->execute([$factura_data['nit'], $serie, $numero_dte]);
                            
                            $affected = $stmtDte->rowCount();
                            error_log("Contabilidad: DTE liberado - NIT: {$factura_data['nit']}, Serie: $serie, Número: $numero_dte, Filas afectadas: $affected");
                            
                            if ($affected == 0) {
                                // Intentar sin la condición usado = 'Y' por si acaso
                                $stmtDte = $dbCajas->prepare("
                                    UPDATE dte 
                                    SET usado = 'X'
                                    WHERE nit_emisor = ? 
                                      AND serie = ? 
                                      AND numero_dte = ?
                                ");
                                $stmtDte->execute([$factura_data['nit'], $serie, $numero_dte]);
                                error_log("Contabilidad: DTE liberado (2do intento) - Filas afectadas: " . $stmtDte->rowCount());
                            }
                        } catch (Exception $e) {
                            error_log("Contabilidad - Error al liberar DTE principal: " . $e->getMessage());
                            throw new Exception("Error al liberar factura SAT: " . $e->getMessage());
                        }
                    }
                }
                
                // Liberar facturas adicionales
                $stmtAd = $this->pdo->prepare("
                    SELECT fa.*
                    FROM facturas_adicionales fa
                    WHERE fa.factura_id = ?
                ");
                $stmtAd->execute([$factura_id]);
                $adicionales = $stmtAd->fetchAll(PDO::FETCH_ASSOC);
                
                $dbCajas = DatabaseCajas::getInstance()->getPdo();
                
                foreach ($adicionales as $adicional) {
                    if (!empty($adicional['numero_dte']) && !empty($adicional['serie']) && !empty($adicional['nit_proveedor'])) {
                        try {
                            $stmtDte = $dbCajas->prepare("
                                UPDATE dte 
                                SET usado = 'X'
                                WHERE nit_emisor = ? 
                                  AND serie = ? 
                                  AND numero_dte = ?
                            ");
                            $stmtDte->execute([
                                $adicional['nit_proveedor'],
                                $adicional['serie'],
                                $adicional['numero_dte']
                            ]);
                            
                            error_log("Contabilidad: DTE adicional liberado: {$adicional['numero_dte']}, NIT: {$adicional['nit_proveedor']}, Filas: " . $stmtDte->rowCount());
                        } catch (Exception $e) {
                            error_log("Contabilidad - Error al liberar DTE adicional: " . $e->getMessage());
                        }
                    }
                    
                    // Marcar factura adicional como liberada
                    try {
                        $stmtUpdAd = $this->pdo->prepare("
                            UPDATE facturas_adicionales 
                            SET liberada = 1, 
                                fecha_liberacion = NOW(),
                                motivo_liberacion = ?
                            WHERE id = ? AND (liberada = 0 OR liberada IS NULL)
                        ");
                        $stmtUpdAd->execute([$motivo, $adicional['id']]);
                    } catch (Exception $e) {
                        error_log("Contabilidad - Error al actualizar factura adicional: " . $e->getMessage());
                    }
                }
                
                // Actualizar factura principal - CAMBIAR a rechazada_contabilidad o devolver a aprobada_finanzas
                // Como el enum no tiene 'rechazada_contabilidad', usamos 'rechazada_finanzas' o lo devolvemos a 'aprobada_compras'
                $stmt = $this->pdo->prepare("
                    UPDATE facturas 
                    SET estado = 'rechazada_contabilidad',
                        contrasena_pago = NULL,
                        contrasena_cancelada = 1,
                        motivo_cancelacion = ?,
                        fecha_cancelacion = NOW(),
                        observaciones_contabilidad = CONCAT(IFNULL(observaciones_contabilidad, ''), '\n[', NOW(), '] ', ?, ' Rechazada por Contabilidad: ', ?)
                    WHERE id = ?
                ");
                
                $stmt->execute([$motivo, $usuario, $motivo, $factura_id]);
                
                $this->pdo->commit();
                $success = "Factura rechazada. La(s) factura(s) SAT ha(n) sido liberada(s) y puede(n) ser reutilizada(s).";
                $factura = $this->getFacturaById($factura_id);
                
            } catch (Exception $e) {
                $this->pdo->rollBack();
                $error = "Error al rechazar la factura: " . $e->getMessage();
                error_log("Contabilidad - Error en rechazo: " . $e->getMessage());
            }
        }
    }
    
    // Procesar registro de pago
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_pago'])) {
        $factura_id = $_POST['factura_id'] ?? 0;
        $numero_comprobante = trim($_POST['numero_comprobante'] ?? '');
        $fecha_pago = $_POST['fecha_pago'] ?? date('Y-m-d');
        $monto_pagado = floatval($_POST['monto_pagado'] ?? 0);
        $observaciones = $_POST['observaciones'] ?? '';
        $usuario = $_SESSION['user']['username'] ?? 'contabilidad';
        
        if (!$factura_id || empty($numero_comprobante) || $monto_pagado <= 0) {
            $error = "Datos de pago incompletos";
        } else {
            $this->pdo->beginTransaction();
            
            try {
                // Actualizar factura
                $stmt = $this->pdo->prepare("
                    UPDATE facturas 
                    SET estado = 'pagada',
                        pagado = 1,
                        fecha_pago_real = ?,
                        numero_comprobante_pago = ?,
                        observaciones_contabilidad = CONCAT(IFNULL(observaciones_contabilidad, ''), '\n[', NOW(), '] ', ?, ' Pagado: ', ?)
                    WHERE id = ?
                ");
                
                $stmt->execute([$fecha_pago, $numero_comprobante, $usuario, $observaciones, $factura_id]);
                
                // Registrar en tabla de pagos
                $stmtPago = $this->pdo->prepare("
                    INSERT INTO pagos (factura_id, fecha_pago, monto_pagado, detalle, registrado_por)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmtPago->execute([$factura_id, $fecha_pago, $monto_pagado, $observaciones, $usuario]);
                
                $this->pdo->commit();
                $success = "Pago registrado correctamente";
                $factura = $this->getFacturaById($factura_id);
                
            } catch (Exception $e) {
                $this->pdo->rollBack();
                $error = "Error al registrar pago: " . $e->getMessage();
            }
        }
    }
    
    // Buscar factura específica
    if (isset($_GET['buscar']) && !empty($_GET['buscar'])) {
        $numero_factura = $_GET['buscar'];
        $factura = $this->getFacturaByNumero($numero_factura);
        if (!$factura) {
            $error = "Factura no encontrada: " . htmlspecialchars($numero_factura);
        }
    }
    
    // Listar facturas pendientes de envío a SAP (aprobadas por Finanzas)
    $facturas_pendientes_sap = $this->getFacturasPendientesSAP();
    
    // Listar facturas en SAP (enviadas, no pagadas)
    $facturas_en_sap = $this->getFacturasEnSAP();
    
    // Listar facturas pagadas recientemente
    $facturas_pagadas = $this->getFacturasPagadas();
    
    // Estadísticas
    $estadisticas = $this->getEstadisticas();
    
    require_once BASE_PATH . 'app/views/layout/header_contabilidad.php';
    require_once BASE_PATH . 'app/views/contabilidad/dashboard.php';
    require_once BASE_PATH . 'app/views/layout/footer.php';
}
    
    private function getFacturaById($id) {
        $stmt = $this->pdo->prepare("
            SELECT f.*, p.nombre as proveedor_nombre, p.cardcode, p.nit, p.tipo_proveedor
            FROM facturas f
            JOIN proveedores p ON f.cardcode = p.cardcode
            WHERE f.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getFacturaByNumero($numero_factura) {
        $stmt = $this->pdo->prepare("
            SELECT f.*, p.nombre as proveedor_nombre, p.cardcode, p.nit, p.tipo_proveedor
            FROM facturas f
            JOIN proveedores p ON f.cardcode = p.cardcode
            WHERE f.numero_factura = ?
        ");
        $stmt->execute([$numero_factura]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getFacturasPendientesSAP() {
        $stmt = $this->pdo->prepare("
            SELECT f.*, p.nombre as proveedor_nombre, p.cardcode, p.tipo_proveedor
            FROM facturas f
            JOIN proveedores p ON f.cardcode = p.cardcode
            WHERE f.estado = 'aprobada_finanzas'
            ORDER BY f.fecha_pago_propuesta ASC, f.fecha_aprobacion_finanzas ASC
            LIMIT 50
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getFacturasEnSAP() {
        $stmt = $this->pdo->prepare("
            SELECT f.*, p.nombre as proveedor_nombre, p.cardcode, p.tipo_proveedor
            FROM facturas f
            JOIN proveedores p ON f.cardcode = p.cardcode
            WHERE f.estado = 'en_sap' AND f.pagado = 0
            ORDER BY f.fecha_envio_sap DESC
            LIMIT 50
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getFacturasPagadas() {
        $stmt = $this->pdo->prepare("
            SELECT f.*, p.nombre as proveedor_nombre, p.cardcode, p.tipo_proveedor
            FROM facturas f
            JOIN proveedores p ON f.cardcode = p.cardcode
            WHERE f.estado = 'pagada'
            ORDER BY f.fecha_pago_real DESC
            LIMIT 20
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getEstadisticas() {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(CASE WHEN estado = 'aprobada_finanzas' THEN 1 END) as pendientes_sap,
                COUNT(CASE WHEN estado = 'en_sap' THEN 1 END) as en_sap,
                COUNT(CASE WHEN estado = 'pagada' AND fecha_pago_real >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as pagadas_mes,
                COALESCE(SUM(CASE WHEN estado = 'aprobada_finanzas' THEN monto ELSE 0 END), 0) as monto_pendiente
            FROM facturas
        ");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Descargar PDF
    public function descargarPDF() {
        $id = $_GET['id'] ?? 0;
        $tipo = $_GET['tipo'] ?? 'factura';
        
        $stmt = $this->pdo->prepare("
            SELECT pdf_factura, pdf_orden_compra, pdf_constancia 
            FROM facturas WHERE id = ?
        ");
        $stmt->execute([$id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$factura) {
            die("Factura no encontrada");
        }
        
        $campo = '';
        switch ($tipo) {
            case 'factura': $campo = 'pdf_factura'; break;
            case 'orden': $campo = 'pdf_orden_compra'; break;
            case 'constancia': $campo = 'pdf_constancia'; break;
            default: die("Tipo no válido");
        }
        
        $ruta = BASE_PATH . $factura[$campo];
        if (empty($factura[$campo]) || !file_exists($ruta)) {
            die("Archivo no disponible");
        }
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($ruta) . '"');
        readfile($ruta);
        exit;
    }
    
    // Generar PDF de contraseña
    public function pdfContraseña() {
        $id = $_GET['id'] ?? 0;
        
        $stmt = $this->pdo->prepare("
            SELECT f.*, p.nombre as proveedor_nombre, p.cardcode, p.nit
            FROM facturas f
            JOIN proveedores p ON f.cardcode = p.cardcode
            WHERE f.id = ?
        ");
        $stmt->execute([$id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$factura || empty($factura['contrasena_pago'])) {
            die("Factura no encontrada o sin contraseña");
        }
        
        require_once BASE_PATH . 'vendor/tecnickcom/tcpdf/tcpdf.php';
        
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Agrocentro');
        $pdf->SetAuthor('Portal Proveedores - Contabilidad');
        $pdf->SetTitle('Contraseña de Pago - ' . $factura['numero_factura']);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();
        
        // Logo
        $logoUrl = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSkLb4zCuSBqdoqYloQhjlciiOINIhOwZrJIA&s';
        $logoContent = @file_get_contents($logoUrl);
        if ($logoContent !== false) {
            $tempLogo = tempnam(sys_get_temp_dir(), 'logo_');
            file_put_contents($tempLogo, $logoContent);
            $pdf->Image($tempLogo, 15, 15, 45);
            unlink($tempLogo);
        }
        
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, 'Agrocentro', 0, 1, 'R');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, '11 calle 6-44 zona 10 Oficina 704 Edificio Airali Guatemala', 0, 1, 'R');
        $pdf->Cell(0, 5, 'Tel: 2319-3200 / 2319-3210', 0, 1, 'R');
        
        $pdf->Ln(10);
        
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, 'RECEPCIÓN DE FACTURAS: DÍA LUNES', 0, 1);
        $pdf->Cell(0, 8, 'DÍA DE PAGO: VIERNES 8:00-12:00 y 14:00-16:00', 0, 1);
        
        $pdf->Ln(5);
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(40, 7, 'CÓDIGO:', 0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 7, $factura['cardcode'], 0, 1);
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(40, 7, 'PROVEEDOR:', 0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 7, mb_substr($factura['proveedor_nombre'] ?? 'N/A', 0, 60), 0, 1);
        
        $pdf->Ln(8);
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(60, 8, 'Factura', 1, 0, 'C');
        $pdf->Cell(50, 8, 'Documento', 1, 0, 'C');
        $pdf->Cell(30, 8, 'Fecha', 1, 0, 'C');
        $pdf->Cell(35, 8, 'Valor', 1, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(60, 8, $factura['numero_factura'], 1, 0, 'C');
        $pdf->Cell(50, 8, 'FACTURA', 1, 0, 'C');
        $pdf->Cell(30, 8, date('d/m/Y', strtotime($factura['fecha_emision'])), 1, 0, 'C');
        $pdf->Cell(35, 8, 'Q ' . number_format($factura['monto'], 2), 1, 1, 'C');
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(140, 8, 'TOTAL', 1, 0, 'R');
        $pdf->Cell(35, 8, 'Q ' . number_format($factura['monto'], 2), 1, 1, 'C');
        
        $pdf->Ln(10);
        
        if (!empty($factura['fecha_pago_propuesta'])) {
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 8, 'FECHA DE PAGO PROPUESTA: ' . date('d/m/Y', strtotime($factura['fecha_pago_propuesta'])), 0, 1, 'C');
            $pdf->Ln(5);
        }
        
        // Si ya está pagada, mostrar información de pago
        if ($factura['estado'] === 'pagada' && !empty($factura['fecha_pago_real'])) {
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetTextColor(0, 100, 0);
            $pdf->Cell(0, 8, '*** FACTURA PAGADA ***', 0, 1, 'C');
            $pdf->Cell(0, 8, 'Fecha de Pago: ' . date('d/m/Y', strtotime($factura['fecha_pago_real'])), 0, 1, 'C');
            $pdf->Cell(0, 8, 'Comprobante: ' . ($factura['numero_comprobante_pago'] ?? 'N/A'), 0, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Ln(5);
        }
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(60, 10, 'No. CONTRASEÑA:', 0);
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetTextColor(0, 100, 0);
        $pdf->Cell(0, 10, $factura['contrasena_pago'], 0, 1);
        
        $pdf->SetTextColor(0, 0, 0);
        
        if (ob_get_length()) {
            ob_clean();
        }
        
        $filename = 'Contraseña_' . $factura['numero_factura'] . '.pdf';
        $pdf->Output($filename, 'I');
        exit;
    }
}
?>