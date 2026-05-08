<?php
// app/controllers/ComprasController.php
require_once BASE_PATH . 'app/models/FacturaModel.php';
require_once BASE_PATH . 'app/models/ProveedorModel.php';

class ComprasController {
    private $pdo;
    
    public function __construct() {
        $this->pdo = DatabasePortal::getInstance()->getPdo();
        $this->verificarAcceso();
    }
    
    private function verificarAcceso() {
        // Solo rol 'compras' o 'admin' puede acceder
        if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['rol'], ['admin', 'compras'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }
    }
    
    // Listar facturas pendientes de revisión por Compras
    public function revisionPendiente() {
        $estado = $_GET['estado'] ?? 'reportada';
        
        $stmt = $this->pdo->prepare("
            SELECT f.*, p.nombre as proveedor_nombre, p.cardcode
            FROM facturas f
            JOIN proveedores p ON f.cardcode = p.cardcode
            WHERE f.estado IN ('reportada', 'revision_compras')
            ORDER BY f.fecha_emision DESC
        ");
        $stmt->execute();
        $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/compras/revision-pendiente.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }
    
    // Ver detalle de una factura para revisión
    public function revisarFactura() {
        $id = $_GET['id'] ?? 0;
        
        $stmt = $this->pdo->prepare("
            SELECT f.*, p.nombre as proveedor_nombre, p.nit as proveedor_nit
            FROM facturas f
            JOIN proveedores p ON f.cardcode = p.cardcode
            WHERE f.id = ?
        ");
        $stmt->execute([$id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$factura) {
            die("Factura no encontrada");
        }
        
        // Obtener órdenes de compra actuales
        $ordenesActuales = json_decode($factura['ordenes_relacionadas'] ?? '[]', true);
        
        // Obtener órdenes de compra disponibles del proveedor (desde SAP)
        $proveedorModel = new ProveedorModel();
        $ordenesDisponibles = $proveedorModel->getOrdenesCompraByCardcode($factura['cardcode'], 'abierta');
        
        // Obtener facturas adicionales si existen
        $stmtAd = $this->pdo->prepare("SELECT * FROM facturas_adicionales WHERE factura_id = ?");
        $stmtAd->execute([$id]);
        $facturasAdicionales = $stmtAd->fetchAll(PDO::FETCH_ASSOC);
        
        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/compras/revisar-factura.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }
    
    // Cambiar órdenes de compra de una factura
    public function cambiarOrdenesCompra() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=compras&action=revisionPendiente');
            exit;
        }
        
        $factura_id = $_POST['factura_id'] ?? 0;
        $ordenes = $_POST['ordenes'] ?? []; // Array de docentry
        $comentario = trim($_POST['comentario'] ?? '');
        
        $stmt = $this->pdo->prepare("
            UPDATE facturas 
            SET ordenes_relacionadas = ?, 
                estado = 'revision_compras',
                comentarios_compras = ?
            WHERE id = ?
        ");
        
        $ordenesJson = json_encode($ordenes);
        
        if ($stmt->execute([$ordenesJson, $comentario, $factura_id])) {
            $_SESSION['success'] = "Órdenes de compra actualizadas correctamente";
        } else {
            $_SESSION['error'] = "Error al actualizar las órdenes";
        }
        
        header("Location: index.php?controller=compras&action=revisarFactura&id={$factura_id}");
        exit;
    }
    
    // Rechazar factura (anula contraseña, libera DTE)
    public function rechazarFactura() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=compras&action=revisionPendiente');
            exit;
        }
        
        $factura_id = $_POST['factura_id'] ?? 0;
        $motivo = trim($_POST['motivo_rechazo'] ?? '');
        
        if (empty($motivo)) {
            $_SESSION['error'] = "Debe ingresar un motivo de rechazo";
            header("Location: index.php?controller=compras&action=revisarFactura&id={$factura_id}");
            exit;
        }
        
        // Obtener datos de la factura
        $stmt = $this->pdo->prepare("SELECT * FROM facturas WHERE id = ?");
        $stmt->execute([$factura_id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$factura) {
            $_SESSION['error'] = "Factura no encontrada";
            header('Location: index.php?controller=compras&action=revisionPendiente');
            exit;
        }
        
        // Iniciar transacción
        $this->pdo->beginTransaction();
        
        try {
            // 1. Anular la contraseña de la factura principal
            $stmt = $this->pdo->prepare("
                UPDATE facturas 
                SET contrasena_pago = NULL,
                    fecha_inicio_credito = NULL,
                    fecha_pago_esperada = NULL,
                    contrasena_cancelada = 1,
                    motivo_cancelacion = ?,
                    fecha_cancelacion = NOW(),
                    estado = 'rechazada_compras',
                    rechazado_por = ?,
                    fecha_rechazo = NOW(),
                    motivo_rechazo = ?
                WHERE id = ?
            ");
            $stmt->execute([$motivo, $_SESSION['user']['username'], $motivo, $factura_id]);
            
            // 2. Liberar la factura principal (DTE en cajas_chicas)
            if (!empty($factura['numero_factura'])) {
                $proveedorStmt = $this->pdo->prepare("SELECT nit FROM proveedores WHERE cardcode = ?");
                $proveedorStmt->execute([$factura['cardcode']]);
                $proveedor = $proveedorStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($proveedor && !empty($proveedor['nit'])) {
                    $partes = explode(' ', trim($factura['numero_factura']), 2);
                    $serie = trim($partes[0] ?? '');
                    $numero_dte = trim($partes[1] ?? $factura['numero_factura']);
                    
                    try {
                        $dbCajas = DatabaseCajas::getInstance()->getPdo();
                        $stmtDte = $dbCajas->prepare("
                            UPDATE dte 
                            SET usado = 'X' 
                            WHERE nit_emisor = ? AND serie = ? AND numero_dte = ?
                        ");
                        $stmtDte->execute([$proveedor['nit'], $serie, $numero_dte]);
                    } catch (Exception $e) {
                        error_log("Error liberando DTE principal: " . $e->getMessage());
                    }
                }
            }
            
            // 3. Liberar facturas adicionales
            $stmtAd = $this->pdo->prepare("SELECT * FROM facturas_adicionales WHERE factura_id = ?");
            $stmtAd->execute([$factura_id]);
            $adicionales = $stmtAd->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($adicionales as $adicional) {
                try {
                    $dbCajas = DatabaseCajas::getInstance()->getPdo();
                    $stmtDte = $dbCajas->prepare("
                        UPDATE dte 
                        SET usado = 'X' 
                        WHERE nit_emisor = ? AND serie = ? AND numero_dte = ?
                    ");
                    $stmtDte->execute([
                        $adicional['nit_proveedor'],
                        $adicional['serie'],
                        $adicional['numero_dte']
                    ]);
                } catch (Exception $e) {
                    error_log("Error liberando DTE adicional: " . $e->getMessage());
                }
            }
            
            $this->pdo->commit();
            $_SESSION['success'] = "Factura rechazada correctamente. La contraseña ha sido anulada y la factura SAT está disponible nuevamente.";
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $_SESSION['error'] = "Error al rechazar la factura: " . $e->getMessage();
        }
        
        header('Location: index.php?controller=compras&action=revisionPendiente');
        exit;
    }
    
    // Aprobar factura (Compras validó las OC)
    public function aprobarFactura() {
        $factura_id = $_POST['factura_id'] ?? 0;
        
        $stmt = $this->pdo->prepare("
            UPDATE facturas 
            SET estado = 'aprobada_compras',
                aprobado_por_compras = ?,
                fecha_aprobacion_compras = NOW()
            WHERE id = ?
        ");
        
        if ($stmt->execute([$_SESSION['user']['username'], $factura_id])) {
            $_SESSION['success'] = "Factura aprobada por Compras. Pasa a validación financiera.";
        } else {
            $_SESSION['error'] = "Error al aprobar la factura";
        }
        
        header("Location: index.php?controller=compras&action=revisarFactura&id={$factura_id}");
        exit;
    }
}