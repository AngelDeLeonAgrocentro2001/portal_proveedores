<?php
// app/models/FacturaModel.php
require_once BASE_PATH . 'database/DatabasePortal.php';

class FacturaModel {
    private $pdo;

    public function __construct() {
        $this->pdo = DatabasePortal::getInstance()->getPdo();
    }

                            public function reportarFactura($post, $files, $cardcode) {
    $numero_factura = trim($post['numero_factura'] ?? '');
    $fecha_factura_sat = $post['fecha_emision'] ?? date('Y-m-d');
    $monto          = floatval($post['monto'] ?? 0);
    $retencion      = floatval($post['retencion'] ?? 0);
    $ordenes_seleccionadas = $post['ordenes'] ?? [];
    $viajes         = trim($post['viajes'] ?? '');
    
    // Facturas adicionales (JSON)
    $facturas_adicionales = isset($post['facturas_adicionales']) ? json_decode($post['facturas_adicionales'], true) : [];

    if (empty($numero_factura) || $monto <= 0) {
        return ['success' => false, 'message' => 'Faltan datos obligatorios'];
    }

    if (empty($ordenes_seleccionadas)) {
        return ['success' => false, 'message' => 'Debes seleccionar al menos una Orden de Compra'];
    }

    // Verificar duplicado
    $stmt = $this->pdo->prepare("SELECT id FROM facturas WHERE cardcode = ? AND numero_factura = ?");
    $stmt->execute([$cardcode, $numero_factura]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Esta factura ya fue reportada anteriormente'];
    }

    $provModel = new ProveedorModel();
    $proveedorData = $provModel->getProveedorByCardcode($cardcode);

    // Verificar si el proveedor está en el grupo de doble factura
    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM proveedores_doble_factura WHERE cardcode = ? AND activo = 1");
    $stmt->execute([$cardcode]);
    $esGrupoDoble = $stmt->fetchColumn() > 0;

    // ====================== CONTRASEÑA Y FECHA DE INICIO DE CRÉDITO ======================
    $hoy = new DateTime();
    $diaSemana = (int)$hoy->format('N');

    $contrasena = '';
    $proximoLunesStr = '';
    $esLunes = false;
    $fecha_inicio_credito = '';

    if ($diaSemana === 1) {
        $fecha_inicio_credito = $hoy->format('Y-m-d');
        $contrasena = 'AGRO-' . $hoy->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        $esLunes = true;
        $proximoLunesStr = $hoy->format('d/m/Y');
    } else {
        $proximoLunes = clone $hoy;
        $diasHastaLunes = (8 - $diaSemana) % 7;
        if ($diasHastaLunes === 0) $diasHastaLunes = 7;
        $proximoLunes->modify("+{$diasHastaLunes} days");

        $fecha_inicio_credito = $proximoLunes->format('Y-m-d');
        $contrasena = 'AGRO-' . $proximoLunes->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        $proximoLunesStr = $proximoLunes->format('d/m/Y');
    }

    $fecha_pago_esperada = $this->calcularFechaPago($fecha_inicio_credito, 30);

    // Subida de archivos
    $uploadDir = BASE_PATH . 'uploads/';
    $subdirs = ['facturas/', 'constancias/', 'adicionales/'];
    foreach ($subdirs as $dir) {
        if (!is_dir($uploadDir . $dir)) mkdir($uploadDir . $dir, 0755, true);
    }

    $pdf_factura = $this->subirArchivo($files['pdf_factura'] ?? null, $uploadDir . 'facturas/');
    $pdf_constancia = $this->subirArchivo($files['pdf_constancia'] ?? null, $uploadDir . 'constancias/');

    if (!$pdf_factura) {
        return ['success' => false, 'message' => 'Debes subir la Factura PDF'];
    }

    $ordenes_json = json_encode($ordenes_seleccionadas);
    
    // Calcular monto total con todas las facturas adicionales
    $monto_adicional_total = array_sum(array_column($facturas_adicionales, 'monto'));
    $monto_total = $monto + $monto_adicional_total;
    $es_doble_factura = !empty($facturas_adicionales);

    // INSERT de factura principal
    $stmt = $this->pdo->prepare("
        INSERT INTO facturas 
        (cardcode, numero_factura, fecha_factura_sat, fecha_emision, monto, monto_retencion, 
         contrasena_pago, fecha_pago_esperada, fecha_inicio_credito, pdf_factura, pdf_constancia, 
         viajes, estado, ordenes_relacionadas, es_doble_factura)
        VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, 'reportada', ?, ?)
    ");

    $stmt->execute([
        $cardcode,
        $numero_factura,
        $fecha_factura_sat,
        $monto_total,
        $retencion,
        $contrasena,
        $fecha_pago_esperada,
        $fecha_inicio_credito,
        $pdf_factura,
        $pdf_constancia,
        $viajes,
        $ordenes_json,
        $es_doble_factura ? 1 : 0
    ]);

    $factura_id = $this->pdo->lastInsertId();

    // Insertar facturas adicionales
    foreach ($facturas_adicionales as $adicional) {
        // Subir PDF si existe
        $pdf_adicional = null;
        if (isset($files['pdf_adicional_' . $adicional['temp_id']]) && 
            $files['pdf_adicional_' . $adicional['temp_id']]['error'] === UPLOAD_ERR_OK) {
            $pdf_adicional = $this->subirArchivo($files['pdf_adicional_' . $adicional['temp_id']], $uploadDir . 'adicionales/');
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO facturas_adicionales 
            (factura_id, nit_proveedor, nombre_proveedor, serie, numero_dte, fecha_emision, monto, pdf_comprobante)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $factura_id,
            $adicional['nit'],
            $adicional['nombre'],
            $adicional['serie'],
            $adicional['numero_dte'],
            $adicional['fecha_emision'],
            $adicional['monto'],
            $pdf_adicional
        ]);
    }

    // Marcar DTEs como usados (principal)
    if (!empty($numero_factura) && !empty($proveedorData['nit'])) {
        try {
            $dbCajas = DatabaseCajas::getInstance()->getPdo();
            $partes = explode(' ', trim($numero_factura), 2);
            $serie = trim($partes[0] ?? '');
            $numero_dte = trim($partes[1] ?? $numero_factura);

            if ($serie && $numero_dte) {
                $stmtDte = $dbCajas->prepare("
                    UPDATE dte 
                    SET usado = 'Y' 
                    WHERE nit_emisor = ? 
                      AND serie = ? 
                      AND numero_dte = ?
                ");
                $stmtDte->execute([$proveedorData['nit'], $serie, $numero_dte]);
            }
        } catch (Exception $e) {
            error_log("Error al actualizar DTE usado='Y': " . $e->getMessage());
        }
    }

    // Marcar DTEs adicionales como usados
    foreach ($facturas_adicionales as $adicional) {
        try {
            $dbCajas = DatabaseCajas::getInstance()->getPdo();
            $stmtDte = $dbCajas->prepare("
                UPDATE dte 
                SET usado = 'Y' 
                WHERE nit_emisor = ? 
                  AND serie = ? 
                  AND numero_dte = ?
            ");
            $stmtDte->execute([$adicional['nit'], $adicional['serie'], $adicional['numero_dte']]);
        } catch (Exception $e) {
            error_log("Error al actualizar DTE adicional usado='Y': " . $e->getMessage());
        }
    }

    $mensaje_adicional = '';
    if ($es_doble_factura && count($facturas_adicionales) > 0) {
        $mensaje_adicional = " Incluye " . count($facturas_adicionales) . " factura(s) adicional(es) por Q " . number_format($monto_adicional_total, 2);
    }

    return [
        'success' => true,
        'contrasena' => $contrasena,
        'esLunes' => $esLunes,
        'proximoLunes' => $proximoLunesStr,
        'mensaje_adicional' => $mensaje_adicional
    ];
}

public function buscarFacturaSAT($nit, $numero_factura) {
    try {
        $dbCajas = DatabaseCajas::getInstance()->getPdo();
        
        // Parsear serie y número
        $partes = explode(' ', trim($numero_factura), 2);
        $serie = trim($partes[0] ?? '');
        $numero_dte = trim($partes[1] ?? $numero_factura);
        
        $stmt = $dbCajas->prepare("
            SELECT 
                serie, 
                numero_dte, 
                fecha_emision, 
                gran_total as monto, 
                iva, 
                nombre_emisor,
                usado,
                nit_emisor
            FROM dte 
            WHERE nit_emisor = ? 
              AND serie = ? 
              AND numero_dte = ?
              AND (usado IS NULL OR usado = 'X' OR usado = '')
        ");
        $stmt->execute([$nit, $serie, $numero_dte]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return [
                'success' => true,
                'data' => $result
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Factura no encontrada o ya ha sido usada'
            ];
        }
    } catch (Exception $e) {
        error_log("Error al buscar factura SAT: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error al consultar el SAT: ' . $e->getMessage()
        ];
    }
}

private function calcularFechaPago($fecha_base, $dias_credito) {
        $fecha = new DateTime($fecha_base);
        $fecha->modify("+{$dias_credito} days");   // +30 días

        // Avanzar hasta el siguiente viernes (5 = Friday)
        $diaSemana = (int)$fecha->format('N');
        if ($diaSemana !== 5) {
            $diasHastaViernes = (5 - $diaSemana + 7) % 7;
            if ($diasHastaViernes === 0) $diasHastaViernes = 7;
            $fecha->modify("+{$diasHastaViernes} days");
        }

        return $fecha->format('Y-m-d');
    }

private function subirArchivo($file, $destinoDir) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        return null;
    }

    $nuevoNombre = uniqid('doc_') . '.pdf';
    $rutaFinal = $destinoDir . $nuevoNombre;

    if (move_uploaded_file($file['tmp_name'], $rutaFinal)) {
        return str_replace(BASE_PATH, '', $rutaFinal);
    }
    return null;
}

        // Obtener todas las facturas del proveedor (con filtro opcional)
                    // Obtener todas las facturas del proveedor (con filtro opcional)
        public function getFacturasByProveedor($cardcode, $estado = '') {
            $sql = "
                SELECT 
                    id, 
                    numero_factura, 
                    fecha_factura_sat, 
                    fecha_emision, 
                    monto, 
                    monto_retencion, 
                    estado, 
                    contrasena_pago, 
                    fecha_pago_esperada, 
                    fecha_inicio_credito,     -- ← AÑADIDO
                    pdf_factura, 
                    pdf_constancia, 
                    viajes
                FROM facturas 
                WHERE cardcode = ?
            ";

            $params = [$cardcode];

            if (!empty($estado)) {
                $sql .= " AND estado = ?";
                $params[] = $estado;
            }

            $sql .= " ORDER BY fecha_emision DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

    // Obtener una factura específica por ID (para detalle futuro)
    public function getFacturaById($id, $cardcode) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM facturas 
            WHERE id = ? AND cardcode = ?
        ");
        $stmt->execute([$id, $cardcode]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

            public function getPagosByProveedor($cardcode) {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.fecha_pago,
                f.numero_factura,
                p.monto_pagado,
                p.detalle,
                f.monto as monto_factura,
                f.monto_retencion,
                f.fecha_emision as fecha_reporte
            FROM pagos p
            JOIN facturas f ON p.factura_id = f.id
            WHERE f.cardcode = ?
            ORDER BY p.fecha_pago DESC
        ");
        $stmt->execute([$cardcode]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

               public function getUltimosPagos($cardcode, $limit = 5) {
        $limit = (int)$limit;
        if ($limit < 1 || $limit > 50) $limit = 5;

        $stmt = $this->pdo->prepare("
            SELECT p.fecha_pago, f.numero_factura, p.monto_pagado, p.detalle,
                   f.monto as monto_factura
            FROM pagos p
            JOIN facturas f ON p.factura_id = f.id
            WHERE f.cardcode = ?
            ORDER BY p.fecha_pago DESC 
            LIMIT " . $limit
        );
        
        $stmt->execute([$cardcode]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener gastos de cuenta ajena por factura
    public function getGastosByFactura($factura_id, $cardcode) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM gastos_cuenta_ajena 
            WHERE factura_id = ? AND cardcode = ?
            ORDER BY fecha_registro DESC
        ");
        $stmt->execute([$factura_id, $cardcode]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Agregar gasto de cuenta ajena
    public function agregarGastoCuentaAjena($data, $files, $factura_id, $cardcode) {
        $concepto = trim($data['concepto'] ?? '');
        $monto = floatval($data['monto'] ?? 0);
        $numero_factura = trim($data['numero_factura'] ?? '');

        if (empty($concepto) || $monto <= 0) {
            return ['success' => false, 'message' => 'Concepto y monto son obligatorios'];
        }

        // Subir comprobante PDF si existe
        $pdf_comprobante = null;
        if (isset($files['pdf_comprobante']) && $files['pdf_comprobante']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = BASE_PATH . 'uploads/gastos/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $ext = strtolower(pathinfo($files['pdf_comprobante']['name'], PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $nuevoNombre = 'gasto_' . uniqid() . '.pdf';
                if (move_uploaded_file($files['pdf_comprobante']['tmp_name'], $uploadDir . $nuevoNombre)) {
                    $pdf_comprobante = 'uploads/gastos/' . $nuevoNombre;
                }
            }
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO gastos_cuenta_ajena 
            (factura_id, cardcode, concepto, monto, numero_factura, pdf_comprobante)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        if ($stmt->execute([$factura_id, $cardcode, $concepto, $monto, $numero_factura, $pdf_comprobante])) {
            return ['success' => true, 'message' => 'Gasto registrado correctamente'];
        }
        
        return ['success' => false, 'message' => 'Error al registrar el gasto'];
    }

    // Eliminar gasto de cuenta ajena
    public function eliminarGastoCuentaAjena($id, $factura_id, $cardcode) {
        $stmt = $this->pdo->prepare("
            DELETE FROM gastos_cuenta_ajena 
            WHERE id = ? AND factura_id = ? AND cardcode = ?
        ");
        return $stmt->execute([$id, $factura_id, $cardcode]);
    }
}