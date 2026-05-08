<?php
// app/models/ProveedorAutorizacionModel.php
require_once BASE_PATH . 'database/DatabasePortal.php';

class ProveedorAutorizacionModel {
    private $pdo;
    
    public function __construct() {
        $this->pdo = DatabasePortal::getInstance()->getPdo();
    }
    
    // Solicitar autorización como proveedor nuevo
    public function solicitarAutorizacion($data, $files) {
        $cardcode = $data['cardcode'] ?? '';
        $nit = $data['nit'] ?? '';
        $nombre = $data['nombre'] ?? '';
        $direccion = $data['direccion'] ?? '';
        $telefono = $data['telefono'] ?? '';
        $email = $data['email'] ?? '';
        $tipo_proveedor = $data['tipo_proveedor'] ?? 'normal';
        $limite_credito = floatval($data['limite_credito'] ?? 0);
        $observaciones = $data['observaciones'] ?? '';
        
        if (empty($cardcode) || empty($nit) || empty($nombre)) {
            return ['success' => false, 'message' => 'Código, NIT y nombre son obligatorios'];
        }
        
        // Verificar si ya existe
        $stmt = $this->pdo->prepare("SELECT id FROM proveedores WHERE cardcode = ? OR nit = ?");
        $stmt->execute([$cardcode, $nit]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Ya existe un proveedor con ese código o NIT'];
        }
        
        // Subir documentos
        $uploadDir = BASE_PATH . 'uploads/proveedores/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $pdf_rtu = $this->subirDocumento($files['pdf_rtu'] ?? null, $uploadDir, 'rtu_' . $cardcode);
        $pdf_patente = $this->subirDocumento($files['pdf_patente'] ?? null, $uploadDir, 'patente_' . $cardcode);
        $pdf_cedula = $this->subirDocumento($files['pdf_cedula'] ?? null, $uploadDir, 'cedula_' . $cardcode);
        $pdf_carta = $this->subirDocumento($files['pdf_carta_presentacion'] ?? null, $uploadDir, 'carta_' . $cardcode);
        
        // Determinar si requiere autorizaciones según tipo
        $requiere_compras = in_array($tipo_proveedor, ['estratégico', 'servicios']);
        $requiere_finanzas = $tipo_proveedor === 'estratégico'; // Solo estratégicos requieren finanzas
        
        $stmt = $this->pdo->prepare("
            INSERT INTO proveedores 
            (cardcode, nit, nombre, direccion, telefono, email, tipo_proveedor, 
             limite_credito, pdf_rtu, pdf_patente, pdf_cedula, pdf_carta_presentacion,
             observaciones_autorizacion, estatus_autorizacion, fecha_solicitud,
             requiere_autorizacion_compras, requiere_autorizacion_finanzas)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', NOW(), ?, ?)
        ");
        
        if ($stmt->execute([
            $cardcode, $nit, $nombre, $direccion, $telefono, $email, $tipo_proveedor,
            $limite_credito, $pdf_rtu, $pdf_patente, $pdf_cedula, $pdf_carta,
            $observaciones, $requiere_compras, $requiere_finanzas
        ])) {
            return ['success' => true, 'message' => 'Solicitud enviada correctamente', 'id' => $this->pdo->lastInsertId()];
        }
        
        return ['success' => false, 'message' => 'Error al guardar la solicitud'];
    }
    
    private function subirDocumento($file, $uploadDir, $prefix) {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) return null;
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') return null;
        
        $nuevoNombre = $prefix . '_' . uniqid() . '.pdf';
        $rutaFinal = $uploadDir . $nuevoNombre;
        
        if (move_uploaded_file($file['tmp_name'], $rutaFinal)) {
            return str_replace(BASE_PATH, '', $rutaFinal);
        }
        return null;
    }
    
    // Obtener solicitudes pendientes (para aprobadores)
    public function getSolicitudesPendientes($rol = 'compras') {
        $sql = "
            SELECT p.*, 
                   CASE 
                       WHEN p.requiere_autorizacion_compras AND p.aprobado_por IS NULL THEN 'compras'
                       WHEN p.requiere_autorizacion_finanzas AND p.aprobado_por_finanzas IS NULL THEN 'finanzas'
                       ELSE 'completado'
                   END as pendiente_con
            FROM proveedores p
            WHERE p.estatus_autorizacion = 'pendiente' OR p.estatus_autorizacion = 'en_revision'
            ORDER BY p.fecha_solicitud ASC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Filtrar según rol
        if ($rol === 'compras') {
            return array_filter($solicitudes, function($s) {
                return $s['requiere_autorizacion_compras'] && empty($s['aprobado_por']);
            });
        } elseif ($rol === 'finanzas') {
            return array_filter($solicitudes, function($s) {
                return $s['requiere_autorizacion_finanzas'] && empty($s['aprobado_por_finanzas']) && !empty($s['aprobado_por']);
            });
        }
        
        return $solicitudes;
    }
    
    // Aprobar proveedor (por Compras o Finanzas)
    public function aprobarProveedor($id, $rol_aprobador, $usuario) {
        $campo_aprobador = $rol_aprobador === 'compras' ? 'aprobado_por' : 'aprobado_por_finanzas';
        $campo_fecha = $rol_aprobador === 'compras' ? 'fecha_aprobacion_compras' : 'fecha_aprobacion_finanzas';
        
        // Verificar si existe la columna, si no, agregarla
        try {
            $check = $this->pdo->query("SHOW COLUMNS FROM proveedores LIKE '{$campo_fecha}'");
            if ($check->rowCount() == 0) {
                $this->pdo->exec("ALTER TABLE proveedores ADD COLUMN {$campo_fecha} DATETIME NULL");
            }
        } catch (Exception $e) {
            error_log("Error al verificar columna: " . $e->getMessage());
        }
        
        $stmt = $this->pdo->prepare("
            UPDATE proveedores 
            SET {$campo_aprobador} = ?, 
                {$campo_fecha} = NOW()
            WHERE id = ?
        ");
        
        if ($stmt->execute([$usuario, $id])) {
            // Verificar si ya está completamente aprobado
            $stmtCheck = $this->pdo->prepare("
                SELECT requiere_autorizacion_compras, requiere_autorizacion_finanzas,
                       aprobado_por, aprobado_por_finanzas
                FROM proveedores WHERE id = ?
            ");
            $stmtCheck->execute([$id]);
            $proveedor = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            $completado = true;
            if ($proveedor['requiere_autorizacion_compras'] && empty($proveedor['aprobado_por'])) {
                $completado = false;
            }
            if ($proveedor['requiere_autorizacion_finanzas'] && empty($proveedor['aprobado_por_finanzas'])) {
                $completado = false;
            }
            
            if ($completado) {
                $stmtFinal = $this->pdo->prepare("
                    UPDATE proveedores 
                    SET estatus_autorizacion = 'aprobado', 
                        estado = 'activo',
                        fecha_aprobacion = NOW()
                    WHERE id = ?
                ");
                $stmtFinal->execute([$id]);
                return ['success' => true, 'message' => 'Proveedor aprobado completamente'];
            }
            
            return ['success' => true, 'message' => 'Aprobación registrada. Pendiente la otra área.'];
        }
        
        return ['success' => false, 'message' => 'Error al aprobar'];
    }
    
    // Rechazar proveedor
    public function rechazarProveedor($id, $motivo, $usuario) {
        $stmt = $this->pdo->prepare("
            UPDATE proveedores 
            SET estatus_autorizacion = 'rechazado',
                estado = 'inactivo',
                rechazado_por = ?,
                motivo_rechazo = ?,
                fecha_rechazo = NOW()
            WHERE id = ?
        ");
        
        if ($stmt->execute([$usuario, $motivo, $id])) {
            return ['success' => true, 'message' => 'Proveedor rechazado'];
        }
        return ['success' => false, 'message' => 'Error al rechazar'];
    }
    
    // Obtener detalle de proveedor para revisión
    public function getProveedorById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM proveedores WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Crear usuario inicial para el proveedor aprobado
    public function crearUsuarioInicial($proveedor_id, $email, $username, $password, $rol = 'crear_contrasenas') {
        $stmtProveedor = $this->pdo->prepare("SELECT cardcode FROM proveedores WHERE id = ?");
        $stmtProveedor->execute([$proveedor_id]);
        $proveedor = $stmtProveedor->fetch(PDO::FETCH_ASSOC);
        
        if (!$proveedor) return false;
        
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $this->pdo->prepare("
            INSERT INTO usuarios (cardcode, email, username, password, rol)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([$proveedor['cardcode'], $email, $username, $hashed, $rol]);
    }
}