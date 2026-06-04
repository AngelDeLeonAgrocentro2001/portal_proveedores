<?php
// app/controllers/FinanzasController.php
require_once BASE_PATH . 'app/models/FacturaModel.php';

class FinanzasController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = DatabasePortal::getInstance()->getPdo();

        // Verificar que el usuario tenga rol de finanzas o admin
        if (
            !isset($_SESSION['user']) ||
            !in_array($_SESSION['user']['rol'], ['supervisor_finanzas', 'admin'])
        ) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }
    }

    public function dashboard()
    {
        $error = '';
        $success = '';
        $factura = null;
        $filtro_semana = $_GET['filtro_semana'] ?? 'actual';

        // Procesar selección de fecha de pago (Finanzas)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aprobar_factura'])) {
            $factura_id = $_POST['factura_id'] ?? 0;
            $semana_pago = $_POST['semana_pago'] ?? '';
            $fecha_pago_custom = $_POST['fecha_pago_custom'] ?? '';
            $comentarios = $_POST['comentarios'] ?? '';
            $usuario = $_SESSION['user']['username'] ?? 'finanzas';

            if (!$factura_id || empty($semana_pago)) {
                $error = "Debe seleccionar una fecha de pago";
            } else {
                // Calcular fecha de pago según la opción seleccionada
                if ($semana_pago === 'custom' && !empty($fecha_pago_custom)) {
                    $fecha_pago = $fecha_pago_custom;
                    $semana_pago_db = 'fecha_personalizada';
                    $nuevo_estado = 'confirmacion_pago';
                    $mensaje = "📅 Fecha de pago confirmada para el " . date('d/m/Y', strtotime($fecha_pago)) . ". Pendiente de aprobación por Contabilidad.";
                } else {
                    $fecha_pago = $this->calcularFechaPago($semana_pago);
                    $semana_pago_db = $semana_pago;
                    $nuevo_estado = 'aprobado_para_pago';
                    $mensaje = "✅ Pago aprobado para el " . date('d/m/Y', strtotime($fecha_pago)) . ". Pendiente de registro por Contabilidad.";
                }

                // ========== GUARDAR LA FECHA ORIGINAL ANTES DE ACTUALIZAR ==========
                // Obtener la fecha actual de pago propuesta
                $stmtFecha = $this->pdo->prepare("SELECT fecha_pago_propuesta, fecha_pago_propuesta_original FROM facturas WHERE id = ?");
                $stmtFecha->execute([$factura_id]);
                $fecha_actual_data = $stmtFecha->fetch(PDO::FETCH_ASSOC);
                $fecha_actual = $fecha_actual_data['fecha_pago_propuesta'] ?? null;

                // Si hay una fecha actual Y es diferente a la nueva fecha, o si es la primera vez que se asigna fecha
                if ($fecha_actual && $fecha_actual != $fecha_pago) {
                    // Ya existe una fecha y es diferente - guardar el cambio
                    if (empty($fecha_actual_data['fecha_pago_propuesta_original'])) {
                        // Primera vez que se cambia: guardar fecha original y fecha anterior
                        $stmtOrig = $this->pdo->prepare("
                    UPDATE facturas 
                    SET fecha_pago_propuesta_original = ?,
                        fecha_pago_propuesta_anterior = ?
                    WHERE id = ?
                ");
                        $stmtOrig->execute([$fecha_actual, $fecha_actual, $factura_id]);
                        error_log("Primer cambio - Original: $fecha_actual, Anterior: $fecha_actual para factura $factura_id");
                    } else {
                        // Ya hay cambios previos: solo actualizar fecha anterior
                        $stmtAnt = $this->pdo->prepare("
                    UPDATE facturas 
                    SET fecha_pago_propuesta_anterior = ?
                    WHERE id = ?
                ");
                        $stmtAnt->execute([$fecha_actual, $factura_id]);
                        error_log("Cambio subsiguiente - Anterior: $fecha_actual para factura $factura_id");
                    }
                } elseif (!$fecha_actual && $fecha_pago) {
                    // No había fecha asignada previamente - es la primera asignación
                    // No guardamos como "cambio" porque es la fecha inicial
                    error_log("Primera asignación de fecha: $fecha_pago para factura $factura_id (no se registra como cambio)");
                }
                // ========== FIN DEL CÓDIGO ==========

                // Actualizar la factura (NO marcarla como pagada aún)
                $stmt = $this->pdo->prepare("
            UPDATE facturas 
            SET estado = ?,
                semana_pago = ?,
                fecha_pago_propuesta = ?,
                aprobado_por_finanzas = ?,
                fecha_aprobacion_finanzas = NOW(),
                comentarios_finanzas = CONCAT(IFNULL(comentarios_finanzas, ''), '\n[', NOW(), '] ', ?, ' Seleccionó fecha de pago: ', ?)
            WHERE id = ?
        ");

                if ($stmt->execute([$nuevo_estado, $semana_pago_db, $fecha_pago, $usuario, $usuario, $fecha_pago, $factura_id])) {
                    $success = $mensaje;
                    $factura = $this->getFacturaById($factura_id);
                } else {
                    $error = "Error al procesar la fecha de pago";
                }
            }
        }

        // Procesar rechazo de factura (Finanzas)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rechazar_factura'])) {
            $factura_id = $_POST['factura_id'] ?? 0;
            $motivo = $_POST['motivo_rechazo'] ?? '';
            $usuario = $_SESSION['user']['username'] ?? 'finanzas';

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
                            } catch (Exception $e) {
                                error_log("Finanzas - Error al liberar DTE principal: " . $e->getMessage());
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
                            } catch (Exception $e) {
                                error_log("Finanzas - Error al liberar DTE adicional: " . $e->getMessage());
                            }
                        }
                    }

                    // Actualizar factura principal como rechazada
                    $stmt = $this->pdo->prepare("
                        UPDATE facturas 
                        SET estado = 'rechazada_finanzas',
                            rechazado_por_finanzas = ?,
                            fecha_rechazo_finanzas = NOW(),
                            motivo_rechazo_finanzas = ?,
                            contrasena_pago = NULL,
                            contrasena_cancelada = 1,
                            motivo_cancelacion = ?,
                            fecha_cancelacion = NOW(),
                            comentarios_finanzas = CONCAT(IFNULL(comentarios_finanzas, ''), '\n[', NOW(), '] ', ?, ' Rechazada por Finanzas: ', ?)
                        WHERE id = ?
                    ");

                    $stmt->execute([$usuario, $motivo, $motivo, $usuario, $motivo, $factura_id]);

                    $this->pdo->commit();
                    $success = "Factura rechazada. La(s) factura(s) SAT ha(n) sido liberada(s).";
                    $factura = $this->getFacturaById($factura_id);
                } catch (Exception $e) {
                    $this->pdo->rollBack();
                    $error = "Error al rechazar la factura: " . $e->getMessage();
                    error_log("Finanzas - Error en rechazo: " . $e->getMessage());
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

        // Obtener facturas pendientes con filtro de semana (solo en_sap y confirmacion_pago)
        $facturas_pendientes = $this->getFacturasPendientes($filtro_semana);
        $facturas_aprobadas = $this->getFacturasAprobadas();

        // Calcular estadísticas de semanas
        $estadisticas_semanas = $this->getEstadisticasSemanas();

        require_once BASE_PATH . 'app/views/layout/header_finanzas.php';
        require_once BASE_PATH . 'app/views/finanzas/dashboard.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }

    /**
     * Obtener facturas pendientes filtradas por semana de pago
     */
    private function getFacturasPendientes($filtro_semana = 'actual')
    {
        $hoy = new DateTime();
        $inicio_semana = clone $hoy;
        $fin_semana = clone $hoy;

        $diaSemana = (int)$hoy->format('N');
        $inicio_semana->modify('-' . ($diaSemana - 1) . ' days');
        $fin_semana->modify('+' . (7 - $diaSemana) . ' days');

        $proxima_inicio = clone $inicio_semana;
        $proxima_inicio->modify('+7 days');
        $proxima_fin = clone $fin_semana;
        $proxima_fin->modify('+7 days');

        $sql = "
            SELECT f.*, p.nombre as proveedor_nombre, p.cardcode, p.tipo_proveedor
            FROM facturas f
            JOIN proveedores p ON f.cardcode = p.cardcode
            WHERE f.estado IN ('en_sap', 'confirmacion_pago')
        ";

        if ($filtro_semana === 'actual') {
            $sql .= " AND f.fecha_pago_propuesta BETWEEN :inicio AND :fin";
            $params = [
                ':inicio' => $inicio_semana->format('Y-m-d'),
                ':fin' => $fin_semana->format('Y-m-d')
            ];
        } elseif ($filtro_semana === 'proxima') {
            $sql .= " AND f.fecha_pago_propuesta BETWEEN :inicio AND :fin";
            $params = [
                ':inicio' => $proxima_inicio->format('Y-m-d'),
                ':fin' => $proxima_fin->format('Y-m-d')
            ];
        } else {
            $params = [];
        }

        $sql .= " ORDER BY f.fecha_pago_propuesta ASC, f.fecha_envio_sap DESC LIMIT 50";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($resultados as &$f) {
            $fecha_pago = $f['fecha_pago_propuesta'] ?? null;
            if ($fecha_pago) {
                $fecha_obj = new DateTime($fecha_pago);
                $f['esta_semana'] = ($fecha_obj >= $inicio_semana && $fecha_obj <= $fin_semana);
                $f['proxima_semana'] = ($fecha_obj >= $proxima_inicio && $fecha_obj <= $proxima_fin);
            } else {
                $f['esta_semana'] = false;
                $f['proxima_semana'] = false;
            }
        }

        return $resultados;
    }

    /**
     * Obtener estadísticas de semanas
     */
    private function getEstadisticasSemanas()
    {
        $hoy = new DateTime();
        $inicio_semana = clone $hoy;
        $fin_semana = clone $hoy;

        $diaSemana = (int)$hoy->format('N');
        $inicio_semana->modify('-' . ($diaSemana - 1) . ' days');
        $fin_semana->modify('+' . (7 - $diaSemana) . ' days');

        $proxima_inicio = clone $inicio_semana;
        $proxima_inicio->modify('+7 days');
        $proxima_fin = clone $fin_semana;
        $proxima_fin->modify('+7 days');

        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(CASE WHEN fecha_pago_propuesta BETWEEN :semana_actual_inicio AND :semana_actual_fin THEN 1 END) as esta_semana,
                COUNT(CASE WHEN fecha_pago_propuesta BETWEEN :proxima_semana_inicio AND :proxima_semana_fin THEN 1 END) as proxima_semana,
                COUNT(CASE WHEN fecha_pago_propuesta < :semana_actual_inicio AND estado IN ('en_sap', 'confirmacion_pago') THEN 1 END) as atrasadas,
                COUNT(CASE WHEN fecha_pago_propuesta > :proxima_semana_fin AND estado IN ('en_sap', 'confirmacion_pago') THEN 1 END) as futuras,
                COALESCE(SUM(CASE WHEN fecha_pago_propuesta BETWEEN :semana_actual_inicio AND :semana_actual_fin THEN monto ELSE 0 END), 0) as monto_esta_semana,
                COALESCE(SUM(CASE WHEN fecha_pago_propuesta BETWEEN :proxima_semana_inicio AND :proxima_semana_fin THEN monto ELSE 0 END), 0) as monto_proxima_semana
            FROM facturas
            WHERE estado IN ('en_sap', 'confirmacion_pago')
        ");

        $stmt->execute([
            ':semana_actual_inicio' => $inicio_semana->format('Y-m-d'),
            ':semana_actual_fin' => $fin_semana->format('Y-m-d'),
            ':proxima_semana_inicio' => $proxima_inicio->format('Y-m-d'),
            ':proxima_semana_fin' => $proxima_fin->format('Y-m-d')
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function calcularFechaPago($semana_pago)
    {
        $hoy = new DateTime();

        if ($semana_pago === 'este_viernes') {
            $diaSemana = (int)$hoy->format('N');
            if ($diaSemana <= 5) {
                $diasHastaViernes = 5 - $diaSemana;
                $hoy->modify("+{$diasHastaViernes} days");
            } else {
                $diasHastaViernes = (12 - $diaSemana) % 7;
                $hoy->modify("+{$diasHastaViernes} days");
            }
        } else {
            $diaSemana = (int)$hoy->format('N');
            $diasHastaViernes = (12 - $diaSemana) % 7;
            if ($diasHastaViernes === 0) $diasHastaViernes = 7;
            $hoy->modify("+{$diasHastaViernes} days");
        }

        return $hoy->format('Y-m-d');
    }

    private function getFacturaById($id)
    {
        $stmt = $this->pdo->prepare("
            SELECT f.*, p.nombre as proveedor_nombre, p.cardcode, p.nit, p.tipo_proveedor
            FROM facturas f
            JOIN proveedores p ON f.cardcode = p.cardcode
            WHERE f.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getFacturaByNumero($numero_factura)
    {
        $stmt = $this->pdo->prepare("
            SELECT f.*, p.nombre as proveedor_nombre, p.cardcode, p.nit, p.tipo_proveedor
            FROM facturas f
            JOIN proveedores p ON f.cardcode = p.cardcode
            WHERE f.numero_factura = ?
        ");
        $stmt->execute([$numero_factura]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getFacturasAprobadas()
    {
        $stmt = $this->pdo->prepare("
            SELECT f.*, p.nombre as proveedor_nombre, p.cardcode, p.tipo_proveedor
            FROM facturas f
            JOIN proveedores p ON f.cardcode = p.cardcode
            WHERE f.estado = 'aprobado_para_pago' AND f.aprobado_por_finanzas IS NOT NULL
            ORDER BY f.fecha_aprobacion_finanzas DESC
            LIMIT 20
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function descargarPDF()
    {
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
            case 'factura':
                $campo = 'pdf_factura';
                break;
            case 'orden':
                $campo = 'pdf_orden_compra';
                break;
            case 'constancia':
                $campo = 'pdf_constancia';
                break;
            default:
                die("Tipo no válido");
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

    public function pdfContraseña()
    {
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
        $pdf->SetAuthor('Portal Proveedores - Finanzas');
        $pdf->SetTitle('Contraseña de Pago - ' . $factura['numero_factura']);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();

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
        $pdf->Cell(0, 5, '11 calle 6-44 zona 10 Oficina 701 Edificio Airali Guatemala', 0, 1, 'R');
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
