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

    // Contraseña y fechas
    $hoy = new DateTime();
    $diaSemana = (int)$hoy->format('N'); // 1 = Lunes, 7 = Domingo

    $contrasena = '';
    $proximoLunesStr = '';
    $esLunes = false;
    $fechaInicioCredito = '';

    if ($diaSemana === 1) {
        // Es lunes hoy
        $fechaInicioCredito = $hoy->format('Y-m-d');
        $contrasena = 'AGRO-' . $hoy->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        $esLunes = true;
        $proximoLunesStr = $hoy->format('d/m/Y');
    } else {
        // No es lunes, calcular próximo lunes
        $proximoLunes = clone $hoy;
        $diasHastaLunes = (8 - $diaSemana) % 7;
        if ($diasHastaLunes === 0) $diasHastaLunes = 7;
        $proximoLunes->modify("+{$diasHastaLunes} days");
        
        $fechaInicioCredito = $proximoLunes->format('Y-m-d');
        $contrasena = 'AGRO-' . $proximoLunes->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        $proximoLunesStr = $proximoLunes->format('d/m/Y');
    }

    // Fecha de pago esperada (30 días después del inicio del crédito + viernes)
    $fecha_pago_esperada = $this->calcularFechaPago($fechaInicioCredito, 30);

    // Subida de archivos
    $uploadDir = BASE_PATH . 'public/assets/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $subdirs = ['facturas/', 'constancias/'];
    foreach ($subdirs as $dir) {
        if (!is_dir($uploadDir . $dir)) mkdir($uploadDir . $dir, 0755, true);
    }

    $pdf_factura = $this->subirArchivo($files['pdf_factura'] ?? null, $uploadDir . 'facturas/');
    $pdf_constancia = $this->subirArchivo($files['pdf_constancia'] ?? null, $uploadDir . 'constancias/');

    if (!$pdf_factura) {
        return ['success' => false, 'message' => 'Debes subir la Factura PDF'];
    }

    $ordenes_json = json_encode($ordenes_seleccionadas);

    // INSERT con fecha_inicio_credito
    $stmt = $this->pdo->prepare("
        INSERT INTO facturas 
        (cardcode, numero_factura, fecha_factura_sat, fecha_emision, monto, monto_retencion, 
         contrasena_pago, fecha_pago_esperada, fecha_inicio_credito, pdf_factura, pdf_constancia, 
         viajes, estado, ordenes_relacionadas)
        VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, 'reportada', ?)
    ");

    $stmt->execute([
        $cardcode,
        $numero_factura,
        $fecha_factura_sat,
        $monto,
        $retencion,
        $contrasena,
        $fecha_pago_esperada,
        $fechaInicioCredito,
        $pdf_factura,
        $pdf_constancia,
        $viajes,
        $ordenes_json
    ]);

    // Marcar como usada en DTE
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

    // Construir mensaje personalizado para el modal
    $mensajeModal = '';
    if ($esLunes) {
        $mensajeModal = "La contraseña fue generada correctamente <strong>hoy lunes</strong>.<br><br>
                        Se iniciarán los 30 días de crédito a partir de hoy.<br><br>
                        <strong>Contraseña:</strong> " . $contrasena;
    } else {
        $mensajeModal = "<strong>Importante:</strong><br><br>
                        Hoy no es lunes.<br><br>
                        La contraseña se tomará en cuenta el <strong>próximo lunes:</strong><br>
                        <strong style='font-size:1.3rem;'>" . $proximoLunesStr . "</strong><br><br>
                        A partir de ese lunes se contarán los 30 días de crédito para el pago.<br><br>
                        <strong>Contraseña generada:</strong> " . $contrasena;
    }

    return [
        'success' => true,
        'message' => $mensajeModal,
        'contrasena' => $contrasena,
        'esLunes' => $esLunes,
        'proximoLunes' => $proximoLunesStr
    ];
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
            return str_replace(BASE_PATH . 'public/', '', $rutaFinal);
        }
        return null;
    }

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
}