<?php
// app/controllers/AdminController.php - VERSIÓN SIMPLIFICADA (SOLO FACTURAS)
require_once BASE_PATH . 'app/models/FacturaModel.php';

class AdminController {
    private $pdo;
    
    public function __construct() {
        $this->pdo = DatabasePortal::getInstance()->getPdo();
    }
    
    // Verificar si el usuario es supervisor de compras
    private function isSupervisorCompras() {
        return isset($_SESSION['user']) && 
               $_SESSION['user']['rol'] === 'supervisor_compras';
    }
    
    // Obtener el tipo de supervisor (transporte o material_empaque)
    private function getTipoSupervisor() {
        if ($this->isSupervisorCompras()) {
            return $_SESSION['user']['tipo_supervisor'] ?? null;
        }
        return null;
    }
    
    // Verificar acceso (IP permitida O supervisor autenticado)
    private function verificarAcceso() {
        // Permitir acceso por IP (admin global)
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
        
        // Permitir acceso si es supervisor de compras autenticado
        if ($this->isSupervisorCompras()) {
            return true;
        }
        
        // Verificar sesión de admin_agrosistemas
        if (isset($_SESSION['admin_agrosistemas']) && $_SESSION['admin_agrosistemas'] === true) {
            return true;
        }
        
        return false;
    }
    
    public function gestionarContraseñas() {
        // Verificar acceso
        if (!$this->verificarAcceso()) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && $_POST['password'] === 'Agrosistemas2026!') {
                $_SESSION['admin_agrosistemas'] = true;
                $_SESSION['admin_agrosistemas_user'] = 'admin_global';
            } else {
                $this->mostrarLoginAgrosistemas();
                return;
            }
        }
        
        $error = '';
        $success = '';
        $factura = null;
        $facturaModel = new FacturaModel();
        
        // Obtener tipo de supervisor
        $tipoSupervisor = $this->getTipoSupervisor();
        $esSupervisor = $this->isSupervisorCompras();
        
        // Procesar cancelación de contraseña (existente)
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
                    WHERE id = ? AND cardcode IN (SELECT cardcode FROM proveedores WHERE tipo_proveedor = ? OR ? IS NULL)
                ");
                
                $stmtCheck = $this->pdo->prepare("SELECT cardcode FROM facturas WHERE id = ?");
                $stmtCheck->execute([$factura_id]);
                $facturaData = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                
                if ($facturaData && $stmt->execute([$motivo, $factura_id, $tipoSupervisor, $tipoSupervisor === null])) {
                    $success = "Contraseña cancelada correctamente";
                    // Recargar datos
                    $stmt = $this->pdo->prepare("
                        SELECT f.*, p.nombre as proveedor_nombre, p.cardcode, p.tipo_proveedor
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
        
        // Procesar subida de archivo adicional (existente)
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
                            VALUES (?, ?, ?, NOW(), ?)
                        ");
                        $stmt->execute([
                            $factura_id,
                            $_FILES['archivo_adicional']['name'],
                            $rutaRelativa,
                            $_SESSION['admin_agrosistemas_user'] ?? ($_SESSION['user']['username'] ?? 'admin')
                        ]);
                        
                        $success = "Documento subido correctamente";
                        
                        // Recargar factura
                        $stmt = $this->pdo->prepare("
                            SELECT f.*, p.nombre as proveedor_nombre, p.cardcode, p.tipo_proveedor
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
        
        // Buscar factura por número (CON FILTRO POR TIPO DE SUPERVISOR)
        if (isset($_GET['buscar']) || isset($_POST['numero_factura'])) {
            $numero_factura = $_GET['buscar'] ?? $_POST['numero_factura'] ?? '';
            
            if (!empty($numero_factura)) {
                $sql = "
                    SELECT f.*, p.nombre as proveedor_nombre, p.cardcode, p.tipo_proveedor
                    FROM facturas f
                    JOIN proveedores p ON f.cardcode = p.cardcode
                    WHERE f.numero_factura = ?
                ";
                
                $params = [$numero_factura];
                
                // Filtrar por tipo de supervisor
                if ($esSupervisor && $tipoSupervisor) {
                    $sql .= " AND p.tipo_proveedor = ?";
                    $params[] = $tipoSupervisor;
                }
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
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
                    $error = "Factura no encontrada o no tiene permisos para verla: " . htmlspecialchars($numero_factura);
                }
            }
        }
        
        // Listar últimas facturas reportadas (FILTRADAS POR TIPO)
        $sql = "
            SELECT f.id, f.numero_factura, f.fecha_emision, f.monto, f.estado, 
                   f.contrasena_pago, f.fecha_inicio_credito, f.contrasena_cancelada,
                   p.nombre as proveedor_nombre, p.cardcode, p.tipo_proveedor
            FROM facturas f
            JOIN proveedores p ON f.cardcode = p.cardcode
            WHERE f.estado != 'pagada'
        ";
        
        $params = [];
        
        // Filtrar por tipo de supervisor
        if ($esSupervisor && $tipoSupervisor) {
            $sql .= " AND p.tipo_proveedor = ?";
            $params[] = $tipoSupervisor;
        }
        
        $sql .= " ORDER BY f.fecha_emision DESC LIMIT 50";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $ultimas_facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Información del supervisor para la vista
        $supervisor_info = [
            'es_supervisor' => $esSupervisor,
            'tipo' => $tipoSupervisor,
            'nombre' => $_SESSION['user']['username'] ?? ($esSupervisor ? 'Supervisor' : 'Admin'),
            'area' => $tipoSupervisor === 'transporte' ? 'Transporte' : ($tipoSupervisor === 'material_empaque' ? 'Material/Empaque' : 'Admin')
        ];
        
        require_once BASE_PATH . 'app/views/admin/gestionar-contrasenas.php';
    }
    
    // ==================== MÉTODOS PARA GESTIÓN DE FACTURAS ====================
    
    // Obtener órdenes de compra disponibles (con verificación de tipo)
    // Obtener órdenes de compra disponibles (con verificación de tipo) - VERSIÓN CORREGIDA
public function getOrdenesDisponibles() {
    if (!$this->verificarAcceso()) {
        echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
        exit;
    }
    
    $cardcode = $_GET['cardcode'] ?? '';
    if (empty($cardcode)) {
        echo json_encode(['success' => false, 'message' => 'CardCode no proporcionado']);
        exit;
    }
    
    // Verificar que el supervisor tenga permiso para este proveedor
    $esSupervisor = $this->isSupervisorCompras();
    $tipoSupervisor = $this->getTipoSupervisor();
    
    if ($esSupervisor && $tipoSupervisor) {
        $stmt = $this->pdo->prepare("
            SELECT tipo_proveedor FROM proveedores WHERE cardcode = ?
        ");
        $stmt->execute([$cardcode]);
        $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($proveedor && $proveedor['tipo_proveedor'] !== $tipoSupervisor) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para este proveedor']);
            exit;
        }
    }
    
    try {
        // Conectar a SAP
        $sap = new DatabaseSAP();
        $conexion = $sap->CONEXION_HANA('GT_AGROCENTRO_2016');
        
        $query = "
            SELECT 
                \"DocEntry\" as \"docentry\",
                \"DocNum\" as \"numero_oc\",
                \"DocDate\" as \"fecha\",
                \"DocTotal\" as \"monto\"
            FROM \"GT_AGROCENTRO_2016\".OPOR 
            WHERE \"CardCode\" = ?
              AND \"DocStatus\" = 'O'
            ORDER BY \"DocDate\" DESC
        ";
        
        $stmt = odbc_prepare($conexion, $query);
        if (!$stmt) {
            throw new Exception("Error preparando consulta: " . odbc_errormsg($conexion));
        }
        
        if (!odbc_execute($stmt, [$cardcode])) {
            throw new Exception("Error ejecutando consulta: " . odbc_errormsg($conexion));
        }
        
        $ordenes = [];
        
        // CORRECCIÓN: Usar odbc_fetch_array en lugar de odbc_fetch_object
        // y verificar los nombres de las columnas
        while ($row = odbc_fetch_array($stmt)) {
            // Depuración: Ver qué columnas vienen realmente
            error_log("DEBUG - Columnas disponibles: " . implode(', ', array_keys($row)));
            
            // Los nombres pueden venir en mayúsculas o minúsculas según la configuración ODBC
            $docentry = $row['docentry'] ?? $row['DOCENTRY'] ?? $row['DocEntry'] ?? null;
            $numero_oc = $row['numero_oc'] ?? $row['NUMERO_OC'] ?? $row['Numero_oc'] ?? null;
            $fecha = $row['fecha'] ?? $row['FECHA'] ?? $row['Fecha'] ?? null;
            $monto = $row['monto'] ?? $row['MONTO'] ?? $row['Monto'] ?? null;
            
            if ($docentry && $numero_oc) {
                $ordenes[] = [
                    'docentry' => $docentry,
                    'numero_oc' => $numero_oc,
                    'fecha' => $fecha,
                    'monto' => (float)$monto
                ];
            }
        }
        
        odbc_free_result($stmt);
        odbc_close($conexion);
        
        error_log("Órdenes encontradas para $cardcode: " . count($ordenes));
        
        echo json_encode(['success' => true, 'ordenes' => $ordenes]);
    } catch (Exception $e) {
        error_log("Error en getOrdenesDisponibles: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
    
    // Cambiar orden de compra
    public function cambiarOrdenCompra() {
        if (!$this->verificarAcceso()) {
            echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
            exit;
        }
        
        $factura_id = $_POST['factura_id'] ?? 0;
        $nueva_orden = $_POST['nueva_orden'] ?? '';
        $comentarios = $_POST['comentarios'] ?? '';
        $usuario = $_SESSION['admin_agrosistemas_user'] ?? ($_SESSION['user']['username'] ?? 'compras');
        
        if (!$factura_id || empty($nueva_orden)) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit;
        }
        
        try {
            $nuevasOrdenes = [$nueva_orden];
            $ordenesJson = json_encode($nuevasOrdenes);
            
            $stmt = $this->pdo->prepare("
                UPDATE facturas 
                SET ordenes_relacionadas = ?,
                    comentarios_compras = CONCAT(IFNULL(comentarios_compras, ''), '\n[', NOW(), '] ', ?, ' Cambio de OC a ', ?),
                    estado = 'revision_compras'
                WHERE id = ?
            ");
            
            $comentarioLog = $usuario . " cambió orden a $nueva_orden. " . $comentarios;
            
            if ($stmt->execute([$ordenesJson, $usuario, $nueva_orden, $factura_id])) {
                echo json_encode(['success' => true, 'message' => 'Orden cambiada exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
            }
        } catch (Exception $e) {
            error_log("Error en cambiarOrdenCompra: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // Aprobar factura (pasa a Finanzas)
    public function aprobarFacturaCompras() {
        if (!$this->verificarAcceso()) {
            echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
            exit;
        }
        
        $factura_id = $_POST['factura_id'] ?? 0;
        $comentarios = $_POST['comentarios'] ?? '';
        $usuario = $_SESSION['admin_agrosistemas_user'] ?? ($_SESSION['user']['username'] ?? 'compras');
        
        if (!$factura_id) {
            echo json_encode(['success' => false, 'message' => 'ID de factura no válido']);
            exit;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                UPDATE facturas 
                SET estado = 'aprobada_compras',
                    aprobado_por_compras = ?,
                    fecha_aprobacion_compras = NOW(),
                    comentarios_compras = CONCAT(IFNULL(comentarios_compras, ''), '\n[', NOW(), '] ', ?, ' Aprobada por Compras: ', ?)
                WHERE id = ?
            ");
            
            if ($stmt->execute([$usuario, $usuario, $comentarios, $factura_id])) {
                echo json_encode(['success' => true, 'message' => 'Factura aprobada correctamente. Pasa a Finanzas.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al aprobar']);
            }
        } catch (Exception $e) {
            error_log("Error en aprobarFacturaCompras: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // Rechazar factura (anula contraseña)
    public function rechazarFacturaCompras() {
    if (!$this->verificarAcceso()) {
        echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
        exit;
    }
    
    $factura_id = $_POST['factura_id'] ?? 0;
    $motivo = $_POST['motivo'] ?? '';
    $usuario = $_SESSION['admin_agrosistemas_user'] ?? ($_SESSION['user']['username'] ?? 'compras');
    
    if (!$factura_id || empty($motivo)) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }
    
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
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($factura) {
            // Liberar factura SAT principal - CAMBIAR DE 'Y' a 'X'
            $partes = explode(' ', trim($factura['numero_factura']), 2);
            $serie = trim($partes[0] ?? '');
            $numero_dte = trim($partes[1] ?? $factura['numero_factura']);
            
            if ($serie && $numero_dte && !empty($factura['nit'])) {
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
                    $stmtDte->execute([$factura['nit'], $serie, $numero_dte]);
                    
                    $affected = $stmtDte->rowCount();
                    error_log("DTE liberado - NIT: {$factura['nit']}, Serie: $serie, Número: $numero_dte, Filas afectadas: $affected");
                    
                    if ($affected == 0) {
                        // Intentar sin la condición usado = 'Y' por si acaso
                        $stmtDte = $dbCajas->prepare("
                            UPDATE dte 
                            SET usado = 'X'
                            WHERE nit_emisor = ? 
                              AND serie = ? 
                              AND numero_dte = ?
                        ");
                        $stmtDte->execute([$factura['nit'], $serie, $numero_dte]);
                        error_log("DTE liberado (segundo intento) - Filas afectadas: " . $stmtDte->rowCount());
                    }
                } catch (Exception $e) {
                    error_log("Error al liberar DTE principal: " . $e->getMessage());
                    throw new Exception("Error al liberar factura SAT: " . $e->getMessage());
                }
            }
        }
        
        // Liberar facturas adicionales - CORREGIDO: usar nit_proveedor en lugar de cardcode_proveedor
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
                    
                    error_log("DTE adicional liberado: {$adicional['numero_dte']}, NIT: {$adicional['nit_proveedor']}, Filas afectadas: " . $stmtDte->rowCount());
                } catch (Exception $e) {
                    error_log("Error al liberar DTE adicional: " . $e->getMessage());
                }
            }
            
            // Actualizar la factura adicional como liberada (ahora que la tabla tiene la columna)
            try {
                $stmtUpdAd = $this->pdo->prepare("
                    UPDATE facturas_adicionales 
                    SET liberada = 1, 
                        fecha_liberacion = NOW(),
                        motivo_liberacion = ?
                    WHERE id = ? AND liberada = 0
                ");
                $stmtUpdAd->execute([$motivo, $adicional['id']]);
            } catch (Exception $e) {
                error_log("Error al actualizar factura adicional como liberada: " . $e->getMessage());
                // No detenemos el proceso si falla, solo logueamos
            }
        }
        
        // Actualizar factura principal
        $stmt = $this->pdo->prepare("
            UPDATE facturas 
            SET estado = 'rechazada_compras',
                contrasena_pago = NULL,
                contrasena_cancelada = 1,
                motivo_cancelacion = ?,
                fecha_cancelacion = NOW(),
                rechazado_por = ?,
                fecha_rechazo = NOW(),
                motivo_rechazo = ?,
                comentarios_compras = CONCAT(IFNULL(comentarios_compras, ''), '\n[', NOW(), '] ', ?, ' Rechazada: ', ?)
            WHERE id = ?
        ");
        
        $stmt->execute([$motivo, $usuario, $motivo, $usuario, $motivo, $factura_id]);
        
        $this->pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Factura rechazada. La(s) factura(s) SAT ha(n) sido liberada(s) y puede(n) ser reutilizada(s).']);
        
    } catch (Exception $e) {
        $this->pdo->rollBack();
        error_log("Error en rechazarFacturaCompras: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
    
    // Descargar documento
    public function descargarDocumento() {
        if (!$this->verificarAcceso()) {
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
    
    // Generar PDF de contraseña
    public function pdfContraseña() {
        if (!$this->verificarAcceso()) {
            die("Acceso no autorizado");
        }
        
        $id = $_GET['id'] ?? 0;
        if (!$id) {
            die("ID de factura no válido");
        }
        
        $stmt = $this->pdo->prepare("
            SELECT f.*, p.nombre as proveedor_nombre, p.cardcode, p.nit, p.tipo_proveedor
            FROM facturas f
            JOIN proveedores p ON f.cardcode = p.cardcode
            WHERE f.id = ?
        ");
        $stmt->execute([$id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$factura) {
            die("Factura no encontrada");
        }
        
        // Verificar que el supervisor tenga permiso
        $tipoSupervisor = $this->getTipoSupervisor();
        if ($this->isSupervisorCompras() && $tipoSupervisor && $factura['tipo_proveedor'] !== $tipoSupervisor) {
            die("No tiene permisos para ver esta factura");
        }
        
        if (empty($factura['contrasena_pago'])) {
            die("Esta factura no tiene contraseña generada");
        }
        
        // Limpiar orden de compra
        $ordenCompra = $factura['ordenes_relacionadas'] ?? '';
        if (preg_match('/\[\"(\d+)\"\]/', $ordenCompra, $matches)) {
            $ordenCompra = $matches[1];
        } elseif (preg_match('/\[(\d+)\]/', $ordenCompra, $matches)) {
            $ordenCompra = $matches[1];
        }
        
        // Generar PDF (código existente)
        require_once BASE_PATH . 'vendor/tecnickcom/tcpdf/tcpdf.php';
        
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Agrocentro');
        $pdf->SetAuthor('Portal Proveedores - Compras');
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
        
        if (!empty($factura['contrasena_cancelada'])) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor(255, 0, 0);
            $pdf->Cell(0, 8, '*** CONTRASEÑA CANCELADA ***', 0, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, 'Motivo: ' . ($factura['motivo_cancelacion'] ?? 'No especificado'), 0, 1, 'C');
            $pdf->Ln(5);
        }
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(40, 7, 'CÓDIGO:', 0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 7, $factura['cardcode'], 0, 1);
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(40, 7, 'PROVEEDOR:', 0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 7, mb_substr($factura['proveedor_nombre'] ?? 'N/A', 0, 60), 0, 1);
        
        $pdf->Cell(40, 7, 'TIPO:', 0);
        $pdf->Cell(0, 7, $factura['tipo_proveedor'] === 'transporte' ? 'TRANSPORTE' : 'MATERIAL/EMPAQUE', 0, 1);
        
        $pdf->Ln(8);
        
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
        
        if (ob_get_length()) {
            ob_clean();
        }
        
        $filename = 'Contraseña_' . $factura['numero_factura'] . '.pdf';
        $pdf->Output($filename, 'I');
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
        session_destroy();
        header('Location: index.php?controller=admin&action=gestionarContraseñas');
        exit;
    }
}
?>