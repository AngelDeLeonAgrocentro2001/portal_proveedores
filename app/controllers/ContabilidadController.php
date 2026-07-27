<?php
// app/controllers/ContabilidadController.php
require_once BASE_PATH . 'app/models/FacturaModel.php';
require_once BASE_PATH . 'app/models/ProveedorModel.php';  // ← AGREGAR ESTA LÍNEA
require_once BASE_PATH . 'app/models/SAPServiceLayer.php';
class ContabilidadController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = DatabasePortal::getInstance()->getPdo();

        // Verificar que el usuario tenga rol de contabilidad o admin
        if (
            !isset($_SESSION['user']) ||
            !in_array($_SESSION['user']['rol'], ['contabilidad', 'admin'])
        ) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }
    }

    /**
     * Login a SAP Service Layer (igual que en exportar)
     */
    private function login_sap($db)
    {
        $usuario = 'manager';
        $contrasena = 'Team64110';
        $sociedad = $db;

        $curl = curl_init();

        $urlServer = 'https://192.168.1.9:50000/b1s/v1/';
        $sboObjType = 'Login';

        curl_setopt_array($curl, [
            CURLOPT_PORT => 50000,
            CURLOPT_URL => $urlServer . $sboObjType,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_COOKIEJAR => BASE_PATH . "temp/sap_cookie.txt",
            CURLOPT_COOKIEFILE => BASE_PATH . "temp/sap_cookie.txt",
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode([
                "UserName" => $usuario,
                "Password" => $contrasena,
                "CompanyDB" => $sociedad
            ], JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Cache-Control: no-cache"
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($response === false || $curlError) {
            $errorMsg = $curlError ? "cURL Error: $curlError" : "No response received";
            error_log("SAP Login Failed: $errorMsg");
            return ['success' => false, 'error' => $errorMsg];
        }

        if ($httpCode !== 200) {
            error_log("SAP Login Failed: HTTP $httpCode - $response");
            return ['success' => false, 'error' => "HTTP $httpCode - $response"];
        }

        $sessionData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($sessionData['SessionId'])) {
            error_log("SAP Login Failed: Invalid JSON or no SessionId - $response");
            return ['success' => false, 'error' => 'Invalid JSON or no SessionId returned'];
        }

        return [
            'success' => true,
            'sessionId' => $sessionData['SessionId'],
            'routeId' => $sessionData['RouteId'] ?? '.guid',
            'response' => $sessionData
        ];
    }

    /**
     * Logout de SAP Service Layer
     */
    private function logout_sap()
    {
        $curl = curl_init();

        $urlServer = 'https://192.168.1.9:50000/b1s/v1/';
        $sboObjType = 'Logout';

        curl_setopt_array($curl, [
            CURLOPT_PORT => 50000,
            CURLOPT_URL => $urlServer . $sboObjType,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_COOKIEFILE => BASE_PATH . "temp/sap_cookie.txt",
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Cache-Control: no-cache"
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        $cookieFile = BASE_PATH . 'temp/sap_cookie.txt';
        if (file_exists($cookieFile) && is_writable($cookieFile)) {
            unlink($cookieFile);
        }

        if ($response === false || $curlError) {
            $errorMsg = $curlError ? "cURL Error: $curlError" : "No response received";
            error_log("SAP Logout Failed: $errorMsg");
            return ['success' => false, 'error' => $errorMsg];
        }

        if ($httpCode !== 204) {
            error_log("SAP Logout Failed: HTTP $httpCode - $response");
            return ['success' => false, 'error' => "HTTP $httpCode - $response"];
        }

        return ['success' => true];
    }

    public function dashboard()
    {
        $error = '';
        $success = '';
        $factura = null;

        // Procesar subida de retención de IVA
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_retencion_iva'])) {
            $factura_id = $_POST['factura_id'] ?? 0;
            $usuario = $_SESSION['user']['username'] ?? 'contabilidad';

            if (!$factura_id) {
                $error = "ID de factura no válido";
            } elseif (isset($_FILES['pdf_retencion_iva']) && $_FILES['pdf_retencion_iva']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = BASE_PATH . 'uploads/retenciones/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                $ext = strtolower(pathinfo($_FILES['pdf_retencion_iva']['name'], PATHINFO_EXTENSION));
                if ($ext !== 'pdf') {
                    $error = "Solo se permiten archivos PDF para la retención de IVA";
                } else {
                    $nuevoNombre = 'retencion_iva_' . $factura_id . '_' . uniqid() . '.pdf';
                    $rutaFinal = $uploadDir . $nuevoNombre;

                    if (move_uploaded_file($_FILES['pdf_retencion_iva']['tmp_name'], $rutaFinal)) {
                        $rutaRelativa = 'uploads/retenciones/' . $nuevoNombre;

                        $stmt = $this->pdo->prepare("
                            UPDATE facturas 
                            SET pdf_retencion_iva = ?,
                                fecha_subida_retenciones = NOW(),
                                usuario_retenciones = ?
                            WHERE id = ?
                        ");

                        if ($stmt->execute([$rutaRelativa, $usuario, $factura_id])) {
                            $success = "Retención de IVA subida correctamente";
                            $factura = $this->getFacturaById($factura_id);
                        } else {
                            $error = "Error al guardar la retención de IVA";
                        }
                    } else {
                        $error = "Error al subir el archivo";
                    }
                }
            } else {
                $error = "Debe seleccionar un archivo PDF para la retención de IVA";
            }
        }

        // Procesar subida de retención de ISR
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_retencion_isr'])) {
            $factura_id = $_POST['factura_id'] ?? 0;
            $usuario = $_SESSION['user']['username'] ?? 'contabilidad';

            if (!$factura_id) {
                $error = "ID de factura no válido";
            } elseif (isset($_FILES['pdf_retencion_isr']) && $_FILES['pdf_retencion_isr']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = BASE_PATH . 'uploads/retenciones/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                $ext = strtolower(pathinfo($_FILES['pdf_retencion_isr']['name'], PATHINFO_EXTENSION));
                if ($ext !== 'pdf') {
                    $error = "Solo se permiten archivos PDF para la retención de ISR";
                } else {
                    $nuevoNombre = 'retencion_isr_' . $factura_id . '_' . uniqid() . '.pdf';
                    $rutaFinal = $uploadDir . $nuevoNombre;

                    if (move_uploaded_file($_FILES['pdf_retencion_isr']['tmp_name'], $rutaFinal)) {
                        $rutaRelativa = 'uploads/retenciones/' . $nuevoNombre;

                        $stmt = $this->pdo->prepare("
                            UPDATE facturas 
                            SET pdf_retencion_isr = ?,
                                fecha_subida_retenciones = NOW(),
                                usuario_retenciones = ?
                            WHERE id = ?
                        ");

                        if ($stmt->execute([$rutaRelativa, $usuario, $factura_id])) {
                            $success = "Retención de ISR subida correctamente";
                            $factura = $this->getFacturaById($factura_id);
                        } else {
                            $error = "Error al guardar la retención de ISR";
                        }
                    } else {
                        $error = "Error al subir el archivo";
                    }
                }
            } else {
                $error = "Debe seleccionar un archivo PDF para la retención de ISR";
            }
        }

        // Guardar tipo de factura y retenciones seleccionadas
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_tipo_retenciones'])) {
            $factura_id = $_POST['factura_id'] ?? 0;
            $tipo_factura = $_POST['tipo_factura'] ?? '';
            $retenciones = isset($_POST['retenciones']) ? (array)$_POST['retenciones'] : [];

            $tipos_validos = ['contribuyente_normal', 'pequeno_contribuyente'];
            if (!$factura_id || !in_array($tipo_factura, $tipos_validos)) {
                $error = "Datos de tipo de factura inválidos";
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE facturas
                    SET tipo_factura = ?, retenciones_seleccionadas = ?
                    WHERE id = ?
                ");
                if ($stmt->execute([$tipo_factura, json_encode($retenciones), $factura_id])) {
                    $success = "Tipo de factura y retenciones guardados";
                    $factura = $this->getFacturaById($factura_id);
                } else {
                    $error = "Error al guardar tipo de factura";
                }
            }
        }

        // Procesar envío a SAP
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_sap'])) {
            $factura_id = $_POST['factura_id'] ?? 0;
            $comprobante_sap = trim($_POST['comprobante_sap'] ?? '');
            $observaciones = $_POST['observaciones'] ?? '';
            $usuario = $_SESSION['user']['username'] ?? 'contabilidad';

            if (!$factura_id) {
                $error = "ID de factura no válido";
            } else {
                // Cambiar estado a 'en_sap' (factura en SAP, pendiente de autorización de Finanzas)
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
                    $success = "Factura enviada a SAP correctamente. Pasa a autorización de Finanzas.";
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
                        // Liberar factura SAT principal
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
                                ");
                                $stmtDte->execute([$factura_data['nit'], $serie, $numero_dte]);

                                error_log("Contabilidad: DTE liberado - NIT: {$factura_data['nit']}, Serie: $serie, Número: $numero_dte");
                            } catch (Exception $e) {
                                error_log("Contabilidad - Error al liberar DTE principal: " . $e->getMessage());
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
                                error_log("Contabilidad - Error al liberar DTE adicional: " . $e->getMessage());
                            }
                        }
                    }

                    // Actualizar factura principal
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

        // Retenciones habilitadas en SAP para el proveedor de la factura mostrada
        $retencionesDisponibles = [];
        if ($factura) {
            $retencionesDisponibles = $this->getRetencionesDisponibles($factura['cardcode']);
        }

        // Listar facturas pendientes de envío a SAP (aprobadas por Compras)
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

    private function getFacturasPendientesSAP()
    {
        $stmt = $this->pdo->prepare("
            SELECT f.*, p.nombre as proveedor_nombre, p.cardcode, p.tipo_proveedor
            FROM facturas f
            JOIN proveedores p ON f.cardcode = p.cardcode
            WHERE f.estado = 'aprobada_compras'
            ORDER BY f.fecha_aprobacion_compras ASC
            LIMIT 50
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getFacturasEnSAP()
    {
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

    private function getFacturasPagadas()
    {
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

    private function getEstadisticas()
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(CASE WHEN estado = 'aprobada_compras' THEN 1 END) as pendientes_sap,
                COUNT(CASE WHEN estado = 'en_sap' THEN 1 END) as en_sap,
                COUNT(CASE WHEN estado = 'pagada' AND fecha_pago_real >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as pagadas_mes,
                COALESCE(SUM(CASE WHEN estado = 'aprobada_compras' THEN monto ELSE 0 END), 0) as monto_pendiente
            FROM facturas
        ");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Descargar retención de IVA
    public function descargarRetencionIVA()
    {
        $id = $_GET['id'] ?? 0;

        $stmt = $this->pdo->prepare("SELECT pdf_retencion_iva FROM facturas WHERE id = ?");
        $stmt->execute([$id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$factura || empty($factura['pdf_retencion_iva'])) {
            die("Documento no disponible");
        }

        $ruta = BASE_PATH . $factura['pdf_retencion_iva'];
        if (!file_exists($ruta)) {
            die("El archivo no existe en el servidor");
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="retencion_iva_' . $id . '.pdf"');
        readfile($ruta);
        exit;
    }

    // Descargar retención de ISR
    public function descargarRetencionISR()
    {
        $id = $_GET['id'] ?? 0;

        $stmt = $this->pdo->prepare("SELECT pdf_retencion_isr FROM facturas WHERE id = ?");
        $stmt->execute([$id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$factura || empty($factura['pdf_retencion_isr'])) {
            die("Documento no disponible");
        }

        $ruta = BASE_PATH . $factura['pdf_retencion_isr'];
        if (!file_exists($ruta)) {
            die("El archivo no existe en el servidor");
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="retencion_isr_' . $id . '.pdf"');
        readfile($ruta);
        exit;
    }

    // Descargar PDF
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

    // Generar PDF de contraseña
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

        if (!empty($factura['fecha_pago_esperada'])) {
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

    public function enviarSAP()
    {
        // Verificar acceso
        if (
            !isset($_SESSION['user']) ||
            !in_array($_SESSION['user']['rol'], ['contabilidad', 'admin'])
        ) {
            echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
            exit;
        }

        $factura_id = $_POST['factura_id'] ?? 0;
        $comprobante_sap = trim($_POST['comprobante_sap'] ?? '');
        $observaciones = $_POST['observaciones'] ?? '';
        $usuario = $_SESSION['user']['username'] ?? 'contabilidad';

        if (!$factura_id) {
            echo json_encode(['success' => false, 'message' => 'ID de factura no válido']);
            exit;
        }

        // Obtener datos de la factura
        $factura = $this->getFacturaById($factura_id);
        if (!$factura) {
            echo json_encode(['success' => false, 'message' => 'Factura no encontrada']);
            exit;
        }

        // Verificar que esté en estado aprobada_compras
        if ($factura['estado'] !== 'aprobada_compras') {
            echo json_encode(['success' => false, 'message' => 'La factura no está en estado pendiente de envío a SAP']);
            exit;
        }

        // ========== NUEVO: OBTENER nombre_emisor DESDE DTES (cajas_chicas) ==========
        $nombreEmisor = '';
        $numeroFactura = $factura['numero_factura'];

        try {
            $dbCajas = DatabaseCajas::getInstance()->getPdo();

            // Parsear serie y número de la factura
            $partes = explode(' ', trim($numeroFactura), 2);
            $serie = trim($partes[0] ?? '');
            $numero_dte = trim($partes[1] ?? $numeroFactura);

            // Obtener el NIT del proveedor desde la tabla facturas
            $nitFactura = $factura['nit'] ?? '';

            if (!empty($nitFactura) && !empty($serie) && !empty($numero_dte)) {
                $stmtDte = $dbCajas->prepare("
            SELECT nombre_emisor 
            FROM dte 
            WHERE nit_emisor = ? 
              AND serie = ? 
              AND numero_dte = ?
            LIMIT 1
        ");
                $stmtDte->execute([$nitFactura, $serie, $numero_dte]);
                $dteInfo = $stmtDte->fetch(PDO::FETCH_ASSOC);

                if ($dteInfo && !empty($dteInfo['nombre_emisor'])) {
                    $nombreEmisor = $dteInfo['nombre_emisor'];
                    error_log("Nombre emisor obtenido desde dte: $nombreEmisor");
                }
            }
        } catch (Exception $e) {
            error_log("Error al obtener nombre_emisor desde dte: " . $e->getMessage());
        }

        // Si no se encontró en dte, usar el nombre del proveedor de la tabla proveedores
        if (empty($nombreEmisor)) {
            $proveedorModel = new ProveedorModel();
            $proveedor = $proveedorModel->getProveedorByCardcode($factura['cardcode']);
            $nombreEmisor = $proveedor['nombre'] ?? '';
            error_log("Nombre emisor no encontrado en dte, usando nombre de proveedores: $nombreEmisor");
        } else {
            // Cargar proveedor para tener acceso a la dirección
            $proveedorModel = new ProveedorModel();
            $proveedor = $proveedorModel->getProveedorByCardcode($factura['cardcode']);
            error_log("Nombre emisor encontrado en dte: $nombreEmisor");
        }

        // Obtener órdenes de compra asociadas
        $ordenes = json_decode($factura['ordenes_relacionadas'] ?? '[]', true);

        if (empty($ordenes)) {
            echo json_encode(['success' => false, 'message' => 'La factura no tiene órdenes de compra asociadas']);
            exit;
        }

        // Obtener el docentry de la primera orden
        $docentry = $ordenes[0];

        // Obtener detalles completos de la orden de compra desde SAP HANA
        $ordenDetalles = $this->getOrdenCompraDetalles($docentry, $factura['cardcode']);

        if (!$ordenDetalles['success']) {
            echo json_encode(['success' => false, 'message' => 'Error al obtener detalles de la orden: ' . ($ordenDetalles['error'] ?? 'Desconocido')]);
            exit;
        }

        $orden = $ordenDetalles['orden'];
        $lineasOrden = $ordenDetalles['lines'];

        if (empty($lineasOrden)) {
            echo json_encode(['success' => false, 'message' => 'La orden de compra no tiene líneas de detalle']);
            exit;
        }

        // ========== LOGIN A SAP ==========
        error_log("Intentando login en SAP para enviar factura");
        $loginResult = $this->login_sap('T_GT_AGROCENTRO_2016');
        if (!$loginResult['success']) {
            error_log("Login SAP Failed: {$loginResult['error']}");
            echo json_encode(['success' => false, 'message' => 'No es posible conectar a SAP, intente más tarde']);
            exit;
        }
        $cookie = "B1SESSION={$loginResult['sessionId']}; ROUTEID={$loginResult['routeId']}";
        error_log("Cookie creada: " . substr($cookie, 0, 50) . "...");

        // ========== VERIFICAR Y CREAR BUSINESS PARTNER ==========
        $cardCode = $factura['cardcode'];
        error_log("Verificando Business Partner para CardCode: '$cardCode'");

        $encodedCardCode = urlencode($cardCode);
        $businessPartnerUrl = "https://192.168.1.9:50000/b1s/v1/BusinessPartners('$encodedCardCode')";

        $ch = curl_init($businessPartnerUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Cookie: ' . $cookie
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $bpResponse = curl_exec($ch);
        $bpHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($bpHttpCode == 404) {
            error_log("Business Partner $cardCode NO existe. Creándolo...");

            $bpData = [
                "CardCode" => $cardCode,

                "CardType" => "cSupplier",
                "GroupCode" => 101,
                "Currency" => "GTQ",
                "U_NIT" => $factura['nit'] ?? ''
            ];

            $createBpUrl = "https://192.168.1.9:50000/b1s/v1/BusinessPartners";
            $ch = curl_init($createBpUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Cookie: ' . $cookie
                ],
                CURLOPT_POSTFIELDS => json_encode($bpData),
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);

            $createResponse = curl_exec($ch);
            $createHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($createHttpCode != 201) {
                $this->logout_sap();
                echo json_encode(['success' => false, 'message' => "Error al crear proveedor en SAP. Código: $cardCode"]);
                exit;
            }

            error_log("Business Partner $cardCode creado exitosamente");
        } elseif ($bpHttpCode != 200) {
            error_log("Error verificando BP: HTTP $bpHttpCode - $bpResponse");
            $this->logout_sap();
            echo json_encode(['success' => false, 'message' => "Error verificando proveedor en SAP"]);
            exit;
        } else {
            error_log("Business Partner $cardCode YA existe en SAP");
        }

        // ========== CONSTRUIR EL PAYLOAD (ADAPTADO A TU JSON) ==========
        $fechaActual = date('Y-m-d');
        $fechaVencimiento = date('Y-m-d', strtotime('+30 days'));

        // Usar la fecha de la factura si existe
        $docDate = !empty($factura['fecha_factura']) ? $factura['fecha_factura'] : $fechaActual;
        $taxDate = !empty($factura['fecha_factura_sat']) ? $factura['fecha_factura_sat'] : $docDate;
        $docDueDate = date('Y-m-d', strtotime($docDate . ' +30 days'));

        $nitProveedor = $factura['nit'] ?? '';
        // Usar tipo_factura guardado explícitamente; fallback a detección por NIT
        $esPequeñoContribuyente = ($factura['tipo_factura'] ?? '') === 'pequeno_contribuyente';
        if (($factura['tipo_factura'] ?? '') === '') {
            $esPequeñoContribuyente = (strlen($nitProveedor) <= 8 || substr($nitProveedor, 0, 1) == '1');
        }
        $retencionesSeleccionadas = json_decode($factura['retenciones_seleccionadas'] ?? '[]', true) ?: [];
        error_log("Tipo factura: " . ($factura['tipo_factura'] ?? 'no definido') . ", Pequeño contribuyente: " . ($esPequeñoContribuyente ? 'SI' : 'NO'));
        error_log("Retenciones seleccionadas: " . implode(', ', $retencionesSeleccionadas));

        // Retro-compatibilidad: facturas guardadas antes de que las retenciones se cargaran
        // directamente desde SAP (CRD4/OWHT) usaban estas claves internas en vez del WTCode real.
        $mapaWTCodeLegacy = [
            'retencion_iva_65' => '65%',
            'retencion_iva_15' => '15%',
            'retencion_isr_5'  => 'ISR5',
            'retencion_isr_7'  => 'ISR7',
        ];

        // Desde el dashboard de Contabilidad, el valor guardado ya ES el WTCode real de SAP
        // (viene de getRetencionesDisponibles), por lo que SAP calcula el monto automáticamente.
        $withholdingTaxDataCollection = [];
        foreach ($retencionesSeleccionadas as $retencion) {
            if ($retencion === 'sin_retencion' || $retencion === '') {
                continue;
            }
            $wtCode = $mapaWTCodeLegacy[$retencion] ?? $retencion;
            $withholdingTaxDataCollection[] = ['WTCode' => $wtCode];
        }
        error_log("WithholdingTaxDataCollection a enviar: " . json_encode($withholdingTaxDataCollection));

        // ========== IMPORTANTE: Usar el monto de la factura, no el de la orden ==========
        $docTotal = (float)$factura['monto'];  // Monto real de la factura
        error_log("Monto de la factura: $docTotal");

        // Calcular cantidad total de las órdenes
        $totalQuantity = 0;
        foreach ($lineasOrden as $linea) {
            $totalQuantity += (float)$linea['Quantity'];
        }

        // Si no hay cantidad, usar 1 por defecto
        if ($totalQuantity <= 0) {
            $totalQuantity = 1;
        }

        // Precio unitario basado en el total de la factura
        $pricePerUnit = $docTotal / $totalQuantity;

        $documentLines = [];
        foreach ($lineasOrden as $index => $linea) {
            $quantity = (float)$linea['Quantity'];
            if ($quantity <= 0) $quantity = 1;

            $taxCode = $esPequeñoContribuyente ? 'EXE' : ($linea['TaxCode'] ?? 'IVA');

            
            $lineData = [
                "LineNum" => $index,
                "ItemDescription" => $linea['Description'] ?? $linea['ItemDescription'] ?? 'Servicio',
                "Quantity" => $quantity,
                "PriceAfterVAT" => $pricePerUnit,  
                "TaxCode" => $taxCode,
                "U_TipoA" => "S",
                "AccountCode" => $linea['AccountCode'] ?? '640901001',
                "CostingCode" => $linea['CostingCode'] ?? 'D08',
                "CostingCode2" => $linea['CostingCode2'] ?? '',
                "CostingCode3" => $linea['CostingCode3'] ?? '',
                "BaseEntry" => (int)$docentry,
                "BaseLine" => (int)($linea['BaseLine'] ?? $index),
                "BaseType" => 22,
                "DiscountPercent" => 0  // Añadido como en tu ejemplo
            ];

            $documentLines[] = $lineData;
        }

        
        $purchaseInvoice = [
            "DocType" => "dDocument_Service",
            "CardCode" => $cardCode,
            "U_CODIGO" => $cardCode, 
            "DocDate" => $docDate,
            "TaxDate" => $taxDate,
            "DocDueDate" => $docDueDate,
            "Comments" => implode(' | ', array_filter(array_column($documentLines, 'ItemDescription'))),
            "JournalMemo" => "Factura {$factura['numero_factura']}",
            "U_NIT" => $nitProveedor,
            "U_NOMBRE" => $nombreEmisor,
            "U_DIRECCI" => $proveedor['direccion'] ?? 'Ciudad de Guatemala',  // Dirección por defecto
            "Series" => 82,  // ← CAMBIADO a 653 según tu ejemplo (antes era 82)
            "NumAtCard" => $factura['numero_factura'] . '-' . $factura_id,  // Formato como en ejemplo
            "DocCurrency" => "QTZ",
            "DocRate" => 1,
            "DocumentLines" => $documentLines
        ];

        if (!empty($withholdingTaxDataCollection)) {
            $purchaseInvoice['WithholdingTaxDataCollection'] = $withholdingTaxDataCollection;
        }

        // NOTA: NO incluyas "DocTotal" en el payload para dDocument_Service
        // SAP lo calcula automáticamente desde las líneas

        // ========== MOSTRAR JSON EN CONSOLA SIN AFECTAR EL FLUJO ==========
        error_log("=== JSON ENVIADO A SAP (EXACTO) ===");
        error_log(json_encode($purchaseInvoice, JSON_PRETTY_PRINT));
        error_log("====================================");

        // Guardar en archivo de log para depuración
        $logDir = '/tmp/sap_logs/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        file_put_contents(
            $logDir . 'sap_payload_' . date('Y-m-d_H-i-s') . '_' . $factura_id . '.json',
            json_encode($purchaseInvoice, JSON_PRETTY_PRINT)
        );

        // ========== ENVIAR A SAP ==========
        $sapUrl = "https://192.168.1.9:50000/b1s/v1/PurchaseInvoices";
        $ch = curl_init($sapUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Cookie: ' . $cookie
            ],
            CURLOPT_POSTFIELDS => json_encode($purchaseInvoice),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        error_log("=== SAP RESPONSE FULL ===");
        error_log("HTTP Code: $httpCode");
        error_log("Response: " . $response);
        if ($curlError) {
            error_log("CURL Error: $curlError");
        }
        error_log("========================");

        // ========== LOGOUT ==========
        $this->logout_sap();

        if ($response === false || $curlError) {
            error_log("Error enviando a SAP: $curlError");
            echo json_encode(['success' => false, 'message' => 'Error de conexión con SAP: ' . $curlError]);
            exit;
        }

        $sapResponse = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorCode = $sapResponse['error']['code'] ?? 0;
            $errorMsg = $sapResponse['error']['message']['value'] ??
                ($sapResponse['error']['message'] ?? 'Error desconocido');

            error_log("Error SAP: HTTP $httpCode, Code: $errorCode, Message: $errorMsg");
            echo json_encode(['success' => false, 'message' => "Error SAP: $errorMsg"]);
            exit;
        }

        // ========== ACTUALIZAR FACTURA EN BASE DE DATOS ==========
        $docEntry = $sapResponse['DocEntry'] ?? null;
        $docNum = $sapResponse['DocNum'] ?? null;

        $stmt = $this->pdo->prepare("
    UPDATE facturas 
    SET estado = 'en_sap',
        enviado_sap = 1,
        fecha_envio_sap = NOW(),
        fecha_envio_sap_confirmacion = NOW(),
        enviado_por = ?,
        comprobante_sap = ?,
        doc_entry_sap = ?,
        doc_num_sap = ?,
        sap_response = ?,
        observaciones_contabilidad = CONCAT(IFNULL(observaciones_contabilidad, ''), '\n[', NOW(), '] ', ?, ' Enviado a SAP: ', ?)
    WHERE id = ?
");

        $sapResponseJson = json_encode($sapResponse);

        if ($stmt->execute([
            $usuario,
            $comprobante_sap,
            $docEntry,
            $docNum,
            $sapResponseJson,
            $usuario,
            $observaciones,
            $factura_id
        ])) {
            echo json_encode([
                'success' => true,
                'message' => 'Factura enviada a SAP correctamente. Documento SAP #' . $docNum,
                'docEntry' => $docEntry,
                'docNum' => $docNum,
                'payload' => $purchaseInvoice
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Factura creada en SAP pero hubo error al actualizar el registro local',
                'payload' => $purchaseInvoice
            ]);
        }
        exit;
    }

    private function manejarErroresSap($errorCode, $errorMessage, $nitProveedor, $nombreProveedor, $noFactura, $invoicePayload, $sapService)
    {
        error_log("Manejando error SAP: $errorCode - $errorMessage para NIT: $nitProveedor, Factura: $noFactura");

        // Si el código es -1116, intentar extraer el código real del mensaje
        if ($errorCode == -1116) {
            if (strpos($errorMessage, '2021032504') !== false) {
                $errorCode = 2021032504;
                error_log("Código real extraído del mensaje: 2021032504 (Pequeño Contribuyente)");
            } elseif (strpos($errorMessage, '18000018') !== false) {
                $errorCode = 18000018;
                error_log("Código real extraído del mensaje: 18000018 (NIT no existe)");
            } elseif (strpos($errorMessage, '20170505') !== false) {
                $errorCode = 20170505;
                error_log("Código real extraído del mensaje: 20170505 (Descuentos no permitidos)");
            }
        }

        switch ($errorCode) {
            case 18000018: // NIT no existe en catálogo
                error_log("Error 18000018: NIT $nitProveedor no existe en catálogo. Creando en @NIT_PN...");

                // Crear el NIT en la tabla @NIT_PN de HANA
                $creado = $this->crearNITEnHANA($nitProveedor, $nombreProveedor);

                if ($creado) {
                    error_log("NIT $nitProveedor creado exitosamente en @NIT_PN. Reintentando envío...");

                    // Reintentar el envío después de crear el NIT
                    sleep(1); // Pequeña pausa para que SAP reconozca el cambio
                    $resultadoReintento = $sapService->createPurchaseInvoice($invoicePayload);

                    if ($resultadoReintento['success']) {
                        return [
                            'success' => true,
                            'message' => 'Factura enviada exitosamente después de crear el NIT',
                            'docEntry' => $resultadoReintento['docEntry'],
                            'docNum' => $resultadoReintento['docNum'],
                            'response' => $resultadoReintento['response']
                        ];
                    } else {
                        return [
                            'success' => false,
                            'error' => $resultadoReintento['error'] ?? 'Error después de crear NIT',
                            'manejado' => true
                        ];
                    }
                } else {
                    error_log("No se pudo crear el NIT $nitProveedor en @NIT_PN");
                    return [
                        'success' => false,
                        'error' => "No se pudo registrar el NIT $nitProveedor en SAP. Contacte al administrador.",
                        'manejado' => true
                    ];
                }
                break;

            case 20170505: // No se permiten descuentos
                error_log("Error 20170505: Descuentos no permitidos. Eliminando descuentos del payload...");

                // Eliminar descuentos del payload
                if (isset($invoicePayload['DiscountPercent'])) {
                    unset($invoicePayload['DiscountPercent']);
                }
                if (isset($invoicePayload['DiscountAmount'])) {
                    unset($invoicePayload['DiscountAmount']);
                }
                if (isset($invoicePayload['DocumentLines'][0]['DiscountPercent'])) {
                    unset($invoicePayload['DocumentLines'][0]['DiscountPercent']);
                }
                if (isset($invoicePayload['DocumentLines'][0]['DiscountAmount'])) {
                    unset($invoicePayload['DocumentLines'][0]['DiscountAmount']);
                }

                error_log("Payload modificado sin descuentos. Reintentando envío...");
                $resultadoReintento = $sapService->createPurchaseInvoice($invoicePayload);

                if ($resultadoReintento['success']) {
                    return [
                        'success' => true,
                        'message' => 'Factura enviada exitosamente después de eliminar descuentos',
                        'docEntry' => $resultadoReintento['docEntry'],
                        'docNum' => $resultadoReintento['docNum'],
                        'response' => $resultadoReintento['response']
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => $resultadoReintento['error'] ?? 'Error después de eliminar descuentos',
                        'manejado' => true
                    ];
                }
                break;

            case 2021032504: // NIT Pequeño Contribuyente
                error_log("Error 2021032504: NIT Pequeño Contribuyente. Ajustando TaxCode a EXE...");

                // Cambiar TaxCode a EXE para pequeño contribuyente
                if (isset($invoicePayload['DocumentLines'][0])) {
                    $invoicePayload['DocumentLines'][0]['TaxCode'] = 'EXE';
                    $invoicePayload['DocumentLines'][0]['ItemDescription'] = "PEQUEÑO CONTRIBUYENTE - " . ($invoicePayload['DocumentLines'][0]['ItemDescription'] ?? '');
                }

                error_log("Payload modificado con TaxCode EXE. Reintentando envío...");
                $resultadoReintento = $sapService->createPurchaseInvoice($invoicePayload);

                if ($resultadoReintento['success']) {
                    return [
                        'success' => true,
                        'message' => 'Factura enviada exitosamente como Pequeño Contribuyente',
                        'docEntry' => $resultadoReintento['docEntry'],
                        'docNum' => $resultadoReintento['docNum'],
                        'response' => $resultadoReintento['response']
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => $resultadoReintento['error'] ?? 'Error después de ajustar para Pequeño Contribuyente',
                        'manejado' => true
                    ];
                }
                break;

            default:
                error_log("Error no manejable: $errorCode - $errorMessage");
                return [
                    'success' => false,
                    'error' => $errorMessage,
                    'manejado' => false
                ];
        }
    }

    private function createBusinessPartner($sapService, $cardCode, $name, $nit)
    {
        $bpData = [
            "CardCode" => $cardCode,
            "CardName" => $name,
            "CardType" => "cSupplier",
            "U_NIT" => $nit,
            "Currency" => "GTQ"
        ];

        return $sapService->createBusinessPartner($bpData);
    }

    private function findBusinessPartnerByNIT($sapService, $nit)
    {
        try {
            $result = $sapService->getBusinessPartnerByNIT($nit);
            return $result;
        } catch (Exception $e) {
            error_log("findBusinessPartnerByNIT - Error: " . $e->getMessage());
            return null;
        }
    }

    private function findBusinessPartner($sapService, $cardCode)
    {
        try {
            $result = $sapService->getBusinessPartner($cardCode);
            return $result;
        } catch (Exception $e) {
            error_log("findBusinessPartner - Error: " . $e->getMessage());
            return null;
        }
    }

    private function verificarNITEnCatalogoSAP($nit)
    {
        try {
            $sap = new DatabaseSAP();
            $conn = $sap->CONEXION_HANA('T_GT_AGROCENTRO_2016');

            if (!$conn) {
                error_log("verificarNITEnCatalogoSAP - No se pudo conectar a HANA");
                return false;
            }

            // Probar diferentes nombres de tabla y esquemas
            $queries = [
                // Tabla @NIT_PN (existente)
                'SELECT "U_NIT" FROM "T_GT_AGROCENTRO_2016"."@NIT_PN" WHERE "U_NIT" = ?',
                'SELECT "U_NIT" FROM "@NIT_PN" WHERE "U_NIT" = ?',

                // Posibles nombres alternativos
                'SELECT "U_NIT" FROM "T_GT_AGROCENTRO_2016"."@NIT_PROVEEDORES" WHERE "U_NIT" = ?',
                'SELECT "U_NIT" FROM "@NIT_PROVEEDORES" WHERE "U_NIT" = ?',
                'SELECT "U_NIT" FROM "T_GT_AGROCENTRO_2016"."@NIT_PN_PROVEEDORES" WHERE "U_NIT" = ?',
                'SELECT "U_NIT" FROM "@NIT_PN_PROVEEDORES" WHERE "U_NIT" = ?',

                // Tabla OCRD (Business Partners) - algunos sistemas validan directamente aquí
                'SELECT "FederalTaxID" FROM "T_GT_AGROCENTRO_2016".OCRD WHERE "CardType" = \'S\' AND "FederalTaxID" = ?',
                'SELECT "U_NIT" FROM "T_GT_AGROCENTRO_2016".OCRD WHERE "CardType" = \'S\' AND "U_NIT" = ?'
            ];

            foreach ($queries as $sql) {
                $stmt = odbc_prepare($conn, $sql);
                if ($stmt) {
                    $exec = odbc_execute($stmt, [$nit]);
                    if ($exec && odbc_fetch_array($stmt)) {
                        odbc_free_result($stmt);
                        odbc_close($conn);
                        error_log("verificarNITEnCatalogoSAP - NIT $nit SI existe en tabla usando consulta: $sql");
                        return true;
                    }
                    if ($stmt) odbc_free_result($stmt);
                }
            }

            odbc_close($conn);
            error_log("verificarNITEnCatalogoSAP - NIT $nit NO existe en ninguna tabla");
            return false;
        } catch (Exception $e) {
            error_log("verificarNITEnCatalogoSAP - Error: " . $e->getMessage());
            return false;
        }
    }

    private function crearNITEnCatalogoSAP($nit, $nombreProveedor, $cardCode)
    {
        try {
            $sap = new DatabaseSAP();
            $conn = $sap->CONEXION_HANA('T_GT_AGROCENTRO_2016');

            if (!$conn) {
                error_log("crearNITEnCatalogoSAP - No se pudo conectar a HANA");
                return false;
            }

            // Obtener el siguiente código disponible
            $codigo = $this->obtenerSiguienteCodigoNITCatalogo($conn);

            if (!$codigo) {
                odbc_close($conn);
                error_log("crearNITEnCatalogoSAP - No se pudo obtener código disponible");
                return false;
            }

            $codeStr = str_pad((string)$codigo, 6, '0', STR_PAD_LEFT);
            $nameStr = $codeStr;
            $uRazon = substr($nombreProveedor, 0, 100);
            $uValidador = 'N';
            $uCardCode = $cardCode;

            // Intentar insertar en diferentes tablas
            $queries = [
                // Tabla @NIT_PN
                [
                    'sql' => 'INSERT INTO "T_GT_AGROCENTRO_2016"."@NIT_PN" ("Code", "Name", "U_NIT", "U_Razon", "U_Validador", "U_CardCode") VALUES (?, ?, ?, ?, ?, ?)',
                    'params' => [$codeStr, $nameStr, $nit, $uRazon, $uValidador, $uCardCode]
                ],
                // Tabla @NIT_PROVEEDORES
                [
                    'sql' => 'INSERT INTO "T_GT_AGROCENTRO_2016"."@NIT_PROVEEDORES" ("Code", "Name", "U_NIT", "U_Razon", "U_Validador", "U_CardCode") VALUES (?, ?, ?, ?, ?, ?)',
                    'params' => [$codeStr, $nameStr, $nit, $uRazon, $uValidador, $uCardCode]
                ],
                // Solo NIT básico
                [
                    'sql' => 'INSERT INTO "T_GT_AGROCENTRO_2016"."@NIT_PN" ("Code", "Name", "U_NIT", "U_Razon", "U_Validador") VALUES (?, ?, ?, ?, ?)',
                    'params' => [$codeStr, $nameStr, $nit, $uRazon, $uValidador]
                ]
            ];

            $success = false;
            foreach ($queries as $query) {
                $stmt = odbc_prepare($conn, $query['sql']);
                if ($stmt) {
                    $exec = odbc_execute($stmt, $query['params']);
                    if ($exec) {
                        $success = true;
                        odbc_free_result($stmt);
                        error_log("crearNITEnCatalogoSAP - NIT $nit creado exitosamente en tabla usando: " . $query['sql']);
                        break;
                    }
                    odbc_free_result($stmt);
                }
            }

            odbc_close($conn);
            return $success;
        } catch (Exception $e) {
            error_log("crearNITEnCatalogoSAP - Error: " . $e->getMessage());
            return false;
        }
    }

    private function obtenerSiguienteCodigoNITCatalogo($conn)
    {
        $queries = [
            'SELECT MAX(CAST("Code" AS INT)) as max_code FROM "T_GT_AGROCENTRO_2016"."@NIT_PN" WHERE "Code" IS NOT NULL AND "Code" != \'\'',
            'SELECT MAX(CAST("Code" AS INT)) as max_code FROM "T_GT_AGROCENTRO_2016"."@NIT_PROVEEDORES" WHERE "Code" IS NOT NULL AND "Code" != \'\''
        ];

        $maxCode = 13333;

        foreach ($queries as $sql) {
            $stmt = odbc_prepare($conn, $sql);
            if ($stmt && odbc_execute($stmt)) {
                $row = odbc_fetch_array($stmt);
                if ($row && isset($row['max_code']) && $row['max_code'] > 0) {
                    $maxCode = $row['max_code'] + 1;
                    odbc_free_result($stmt);
                    return $maxCode;
                }
                odbc_free_result($stmt);
            }
        }

        return $maxCode;
    }

    /**
     * Verificar si un NIT existe en la tabla @NIT_PN de SAP HANA
     */
    private function verificarNITEnHANA($nit)
    {
        try {
            $sap = new DatabaseSAP();
            $conn = $sap->CONEXION_HANA('T_GT_AGROCENTRO_2016');

            if (!$conn) {
                error_log("verificarNITEnHANA - No se pudo conectar a HANA");
                return false;
            }

            // Primero, encontrar en qué esquema está la tabla
            $schemaQueries = [
                'SELECT SCHEMA_NAME FROM SYS.SCHEMAS WHERE SCHEMA_NAME LIKE \'%GT%\' OR SCHEMA_NAME LIKE \'%SAP%\'',
                'SELECT CURRENT_SCHEMA FROM DUMMY'
            ];

            $schema = 'SAPDBA'; // default

            foreach ($schemaQueries as $sql) {
                $stmt = odbc_prepare($conn, $sql);
                if ($stmt && odbc_execute($stmt)) {
                    while ($row = odbc_fetch_array($stmt)) {
                        error_log("verificarNITEnHANA - Esquema encontrado: " . ($row['SCHEMA_NAME'] ?? $row['CURRENT_SCHEMA'] ?? 'unknown'));
                    }
                    odbc_free_result($stmt);
                }
            }

            // Probar diferentes combinaciones de esquema
            $queries = [
                'SELECT "U_NIT" FROM "@NIT_PN" WHERE "U_NIT" = ?',
                'SELECT "U_NIT" FROM "SAPDBA"."@NIT_PN" WHERE "U_NIT" = ?',
                'SELECT "U_NIT" FROM "T_GT_AGROCENTRO_2016"."@NIT_PN" WHERE "U_NIT" = ?',
                'SELECT "U_NIT" FROM "T_GT_AGROCENTRO_2016"."@NIT_PN" WHERE "U_NIT" = ?',
                'SELECT "U_NIT" FROM "AGROCENTRO"."@NIT_PN" WHERE "U_NIT" = ?'
            ];

            foreach ($queries as $sql) {
                $stmt = odbc_prepare($conn, $sql);
                if ($stmt) {
                    $exec = odbc_execute($stmt, [$nit]);
                    if ($exec && odbc_fetch_array($stmt)) {
                        odbc_free_result($stmt);
                        odbc_close($conn);
                        error_log("verificarNITEnHANA - NIT $nit SI existe (consulta: $sql)");
                        return true;
                    }
                    if ($stmt) odbc_free_result($stmt);
                }
            }

            odbc_close($conn);
            error_log("verificarNITEnHANA - NIT $nit NO existe");
            return false;
        } catch (Exception $e) {
            error_log("verificarNITEnHANA - Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crear un nuevo NIT en la tabla @NIT_PN de SAP HANA
     */
    private function crearNITEnHANA($nit, $nombreProveedor)
    {
        try {
            $sap = new DatabaseSAP();
            $conn = $sap->CONEXION_HANA('T_GT_AGROCENTRO_2016');

            if (!$conn) {
                error_log("crearNITEnHANA - No se pudo conectar a HANA");
                return false;
            }

            // Obtener el siguiente código disponible
            $codigo = $this->obtenerSiguienteCodigoNIT($conn);

            if (!$codigo) {
                odbc_close($conn);
                error_log("crearNITEnHANA - No se pudo obtener código disponible");
                return false;
            }

            $codeStr = str_pad((string)$codigo, 6, '0', STR_PAD_LEFT); // Formato de 6 dígitos
            $nameStr = $codeStr;
            $uRazon = substr($nombreProveedor, 0, 100);
            $uValidador = 'N';

            // Probar diferentes formas del INSERT
            $queries = [
                'INSERT INTO "@NIT_PN" ("Code", "Name", "U_NIT", "U_Razon", "U_Validador") VALUES (?, ?, ?, ?, ?)',
                'INSERT INTO "T_GT_AGROCENTRO_2016"."@NIT_PN" ("Code", "Name", "U_NIT", "U_Razon", "U_Validador") VALUES (?, ?, ?, ?, ?)'
            ];

            $success = false;
            foreach ($queries as $sql) {
                $stmt = odbc_prepare($conn, $sql);
                if ($stmt) {
                    $exec = odbc_execute($stmt, [$codeStr, $nameStr, $nit, $uRazon, $uValidador]);
                    if ($exec) {
                        $success = true;
                        odbc_free_result($stmt);
                        break;
                    }
                    odbc_free_result($stmt);
                }
            }

            odbc_close($conn);

            if ($success) {
                error_log("crearNITEnHANA - NIT $nit creado exitosamente con código $codeStr");
                return true;
            }

            error_log("crearNITEnHANA - Error ejecutando INSERT");
            return false;
        } catch (Exception $e) {
            error_log("crearNITEnHANA - Error: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Obtener el siguiente código disponible para @NIT_PN
     */
    private function obtenerSiguienteCodigoNIT($conn)
    {
        $queries = [
            'SELECT MAX(CAST("Code" AS INT)) as max_code FROM "@NIT_PN" WHERE "Code" IS NOT NULL AND "Code" != \'\'',
            'SELECT MAX(CAST("Code" AS INT)) as max_code FROM "T_GT_AGROCENTRO_2016"."@NIT_PN" WHERE "Code" IS NOT NULL AND "Code" != \'\''
        ];

        $maxCode = 13333; // Código inicial por defecto

        foreach ($queries as $sql) {
            $stmt = odbc_prepare($conn, $sql);
            if ($stmt && odbc_execute($stmt)) {
                $row = odbc_fetch_array($stmt);
                if ($row && isset($row['max_code']) && $row['max_code'] > 0) {
                    $maxCode = $row['max_code'] + 1;
                    error_log("obtenerSiguienteCodigoNIT - Código encontrado: {$row['max_code']}, siguiente: $maxCode");
                    odbc_free_result($stmt);
                    return $maxCode;
                }
                odbc_free_result($stmt);
            }
        }

        error_log("obtenerSiguienteCodigoNIT - Usando código por defecto: $maxCode");
        return $maxCode;
    }
    /**
     * Obtener código de centro de costo desde las órdenes de compra
     */
    private function getCostingCodeFromOrdenes($ordenes, $cardcode)
    {
        // Si no hay órdenes, retornar centro de costo por defecto
        if (empty($ordenes)) {
            return 'C001';
        }

        // Obtener el primer docentry
        $docentry = $ordenes[0];

        // Validar que docentry sea un número válido
        if (empty($docentry) || !is_numeric($docentry)) {
            error_log("getCostingCodeFromOrdenes: docentry inválido: " . print_r($docentry, true));
            return 'C001';
        }

        try {
            $sap = new DatabaseSAP();
            $conexion = $sap->CONEXION_HANA('T_GT_AGROCENTRO_2016');

            // Query para obtener centro de costo
            $query = "
            SELECT DISTINCT T1.\"OcrCode\" 
            FROM \"T_GT_AGROCENTRO_2016\".OPOR T0
            INNER JOIN \"T_GT_AGROCENTRO_2016\".POR1 T1 ON T0.\"DocEntry\" = T1.\"DocEntry\"
            WHERE T0.\"DocEntry\" = ? AND T0.\"CardCode\" = ?
            LIMIT 1
        ";

            $stmt = odbc_prepare($conexion, $query);
            if (!$stmt) {
                error_log("getCostingCodeFromOrdenes: Error preparando consulta - " . odbc_errormsg($conexion));
                odbc_close($conexion);
                return 'C001';
            }

            // Asegurar que docentry se pase como entero y cardcode como string
            $docentryInt = (int)$docentry;
            $cardcodeStr = (string)$cardcode;

            if (!odbc_execute($stmt, [$docentryInt, $cardcodeStr])) {
                error_log("getCostingCodeFromOrdenes: Error ejecutando consulta - " . odbc_errormsg($conexion));
                odbc_close($conexion);
                return 'C001';
            }

            $resultado = null;
            while ($row = odbc_fetch_object($stmt)) {
                if (!empty($row->OcrCode)) {
                    $resultado = $row->OcrCode;
                    break;
                }
            }

            odbc_free_result($stmt);
            odbc_close($conexion);

            if ($resultado) {
                error_log("getCostingCodeFromOrdenes: Centro de costo encontrado: " . $resultado);
                return $resultado;
            }

            error_log("getCostingCodeFromOrdenes: No se encontró centro de costo para docentry: $docentryInt, cardcode: $cardcodeStr");
            return 'C001';
        } catch (Exception $e) {
            error_log("getCostingCodeFromOrdenes: Excepción - " . $e->getMessage());
            return 'C001';
        }
    }


    // Retenciones (WTCode) habilitadas en SAP para un proveedor específico (tabla CRD4 = BP Withholding Tax)
    private function getRetencionesDisponibles($cardCode)
    {
        try {
            $sap = new DatabaseSAP();
            $conn = $sap->CONEXION_HANA('T_GT_AGROCENTRO_2016');

            if (!$conn) {
                error_log("getRetencionesDisponibles - No se pudo conectar a HANA");
                return [];
            }

            $query = '
                SELECT T1."WTCode" as "wtcode", T2."WTName" as "wtname"
                FROM "T_GT_AGROCENTRO_2016".OCRD T0
                LEFT OUTER JOIN "T_GT_AGROCENTRO_2016".CRD4 T1 ON T0."CardCode" = T1."CardCode"
                INNER JOIN "T_GT_AGROCENTRO_2016".OWHT T2 ON T1."WTCode" = T2."WTCode"
                WHERE T0."CardCode" = ?
            ';

            $stmt = odbc_prepare($conn, $query);
            if (!$stmt || !odbc_execute($stmt, [$cardCode])) {
                error_log("getRetencionesDisponibles - Error ejecutando consulta: " . odbc_errormsg($conn));
                odbc_close($conn);
                return [];
            }

            $retenciones = [];
            while ($row = odbc_fetch_array($stmt)) {
                $wtCode = trim($row['wtcode'] ?? '');
                if ($wtCode === '') continue;

                $wtNameRaw = trim($row['wtname'] ?? '');
                // 'auto' falla con tildes/ñ de HANA (las sustituye por '?'); si ya viene en UTF-8 válido
                // se deja tal cual, si no, se asume Windows-1252 (codepage típico del driver ODBC).
                $wtName = mb_check_encoding($wtNameRaw, 'UTF-8')
                    ? $wtNameRaw
                    : mb_convert_encoding($wtNameRaw, 'UTF-8', 'Windows-1252');

                $retenciones[] = [
                    'WTCode' => $wtCode,
                    'WTName' => $wtName
                ];
            }

            odbc_free_result($stmt);
            odbc_close($conn);
            return $retenciones;
        } catch (Exception $e) {
            error_log("getRetencionesDisponibles - Error: " . $e->getMessage());
            return [];
        }
    }

    private function getOrdenCompraDetalles($docentry, $cardcode)
    {
        try {
            $sap = new DatabaseSAP();
            $conexion = $sap->CONEXION_HANA('GT_AGROCENTRO_2016');

            // Query para obtener cabecera y líneas de la orden de compra
            $query = "
            SELECT 
                T0.\"DocEntry\" as \"docentry\", 
                T0.\"DocNum\" as \"docnum\",
                T0.\"CardCode\" as \"cardcode\", 
                T0.\"CardName\" as \"cardname\",
                T0.\"DocDate\" as \"docdate\",
                T0.\"DocTotal\" as \"doctotal\",
                T0.\"Series\" as \"series\",
                T1.\"LineNum\" as \"linenum\", 
                T1.\"ItemCode\" as \"itemcode\",
                T1.\"Dscription\" as \"description\", 
                T1.\"Quantity\" as \"quantity\",
                T1.\"Price\" as \"price\",
                T1.\"LineTotal\" as \"linetotal\",
                T1.\"TaxCode\" as \"taxcode\",
                T1.\"AcctCode\" as \"acctcode\",
                T1.\"OcrCode\" as \"costingcode\",
                T1.\"OcrCode2\" as \"costingcode2\",
                T1.\"OcrCode3\" as \"costingcode3\"
            FROM \"T_GT_AGROCENTRO_2016\".OPOR T0
            INNER JOIN \"T_GT_AGROCENTRO_2016\".POR1 T1 ON T0.\"DocEntry\" = T1.\"DocEntry\"
            WHERE T0.\"DocEntry\" = ? AND T0.\"CardCode\" = ?
            ORDER BY T1.\"LineNum\"
        ";

            $stmt = odbc_prepare($conexion, $query);
            if (!$stmt) {
                throw new Exception("Error preparando consulta: " . odbc_errormsg($conexion));
            }

            if (!odbc_execute($stmt, [$docentry, $cardcode])) {
                throw new Exception("Error ejecutando consulta: " . odbc_errormsg($conexion));
            }

            $ordenData = null;
            $documentLines = [];
            $lineNum = 0;

            while ($row = odbc_fetch_array($stmt)) {
                // Limpiar y convertir encoding
                $itemCode = trim($row['itemcode'] ?? '');
                $description = mb_convert_encoding(trim($row['description'] ?? ''), 'UTF-8', 'auto');
                $quantity = (float)($row['quantity'] ?? 1);
                $price = (float)($row['price'] ?? 0);
                $lineTotal = (float)($row['linetotal'] ?? 0);
                $taxCode = trim($row['taxcode'] ?? 'IVA');
                $acctCode = trim($row['acctcode'] ?? '611001001');
                $costingCode = trim($row['costingcode'] ?? '');
                $costingCode2 = trim($row['costingcode2'] ?? '');
                $costingCode3 = trim($row['costingcode3'] ?? '');

                // Si ItemCode está vacío, usar un código basado en la cuenta o descripción
                if (empty($itemCode)) {
                    $itemCode = 'SERV-' . ($lineNum + 1);
                    error_log("getOrdenCompraDetalles - ItemCode vacío, usando: $itemCode");
                }

                // Si quantity es 0, usar 1 para evitar errores
                if ($quantity <= 0) {
                    $quantity = 1;
                    error_log("getOrdenCompraDetalles - Quantity 0, cambiando a 1 para línea $lineNum");
                }

                // Datos de cabecera (solo del primer registro)
                if ($ordenData === null) {
                    $ordenData = [
                        'docentry' => $row['docentry'] ?? null,
                        'docnum' => $row['docnum'] ?? null,
                        'cardcode' => $row['cardcode'] ?? null,
                        'cardname' => mb_convert_encoding($row['cardname'] ?? '', 'UTF-8', 'auto'),
                        'docdate' => $row['docdate'] ?? date('Y-m-d'),
                        'doctotal' => (float)($row['doctotal'] ?? 0),
                        'series' => $row['series'] ?? null
                    ];
                }

                // Líneas del documento
                $documentLines[] = [
                    'LineNum' => $lineNum,
                    'ItemCode' => $itemCode,
                    'Description' => $description ?: 'Sin descripción',
                    'Quantity' => $quantity,
                    'Price' => $price,
                    'LineTotal' => $lineTotal > 0 ? $lineTotal : ($price * $quantity),
                    'TaxCode' => $taxCode,
                    'AccountCode' => $acctCode,
                    'CostingCode' => $costingCode,
                    'CostingCode2' => $costingCode2,
                    'CostingCode3' => $costingCode3,
                    'BaseEntry' => (int)$docentry,
                    'BaseLine' => (int)($row['linenum'] ?? 0),
                    'BaseType' => 22
                ];
                $lineNum++;
            }

            odbc_free_result($stmt);
            odbc_close($conexion);

            if ($ordenData === null) {
                return [
                    'success' => false,
                    'error' => "No se encontró la orden de compra con DocEntry: $docentry"
                ];
            }

            error_log("getOrdenCompraDetalles - Éxito: " . count($documentLines) . " líneas obtenidas");

            return [
                'success' => true,
                'orden' => $ordenData,
                'lines' => $documentLines
            ];
        } catch (Exception $e) {
            error_log("Error en getOrdenCompraDetalles: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

// app/controllers/ContabilidadController.php - Agregar este método

    /**
     * Reporte de Respuesta de Pago
     * Muestra facturas autorizadas y pospuestas por semana
     */
    public function reporteRespuestaPago()
    {
        // Verificar acceso
        if (
            !isset($_SESSION['user']) ||
            !in_array($_SESSION['user']['rol'], ['contabilidad', 'admin'])
        ) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        $error = '';
        $success = '';
        $fecha_inicio = $_GET['fecha_inicio'] ?? null;
        $fecha_fin = $_GET['fecha_fin'] ?? null;
        $semana_seleccionada = $_GET['semana'] ?? 'actual';

        // Si no hay fechas seleccionadas, usar la semana actual (Lunes a Domingo)
        if (!$fecha_inicio || !$fecha_fin) {
            $hoy = new DateTime();
            $diaSemana = (int)$hoy->format('N');
            $inicio_semana = clone $hoy;
            $fin_semana = clone $hoy;
            $inicio_semana->modify('-' . ($diaSemana - 1) . ' days');
            $fin_semana->modify('+' . (7 - $diaSemana) . ' days');

            $fecha_inicio = $inicio_semana->format('Y-m-d');
            $fecha_fin = $fin_semana->format('Y-m-d');
            $semana_seleccionada = 'actual';
        }

        // Obtener facturas aprobadas (autorizadas) en el rango de fechas
        $facturas_autorizadas = $this->getFacturasAutorizadasPorFecha($fecha_inicio, $fecha_fin);

        // Obtener facturas pospuestas (que cambiaron su fecha de pago)
        $facturas_pospuestas = $this->getFacturasPosPuestas($fecha_inicio, $fecha_fin);

        // Calcular resumen
        $resumen = [
            'total_autorizadas' => count($facturas_autorizadas),
            'total_monto_autorizado' => array_sum(array_column($facturas_autorizadas, 'monto')),
            'total_pospuestas' => count($facturas_pospuestas),
            'total_monto_pospuesto' => array_sum(array_column($facturas_pospuestas, 'monto')),
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin
        ];

        // Opciones de semanas predefinidas
        $semanas_disponibles = $this->getSemanasDisponibles();

        require_once BASE_PATH . 'app/views/layout/header_contabilidad.php';
        require_once BASE_PATH . 'app/views/contabilidad/reporte_respuesta_pago.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }

    /**
     * Obtener facturas autorizadas (aprobadas por Finanzas) en un rango de fechas
     * Incluye facturas en estado: aprobado_para_pago, confirmacion_pago y pagada
     */
    private function getFacturasAutorizadasPorFecha($fecha_inicio, $fecha_fin)
    {
        $stmt = $this->pdo->prepare("
        SELECT 
            f.id,
            f.numero_factura,
            f.monto,
            f.fecha_pago_esperada,
            f.fecha_pago_propuesta,
            f.fecha_pago_real,
            f.fecha_aprobacion_finanzas,
            f.aprobado_por_finanzas,
            f.numero_comprobante_pago,
            f.semana_pago,
            f.estado,
            p.nombre as proveedor_nombre,
            p.cardcode,
            p.tipo_proveedor,
            p.nit
        FROM facturas f
        JOIN proveedores p ON f.cardcode = p.cardcode
        WHERE f.estado IN ('aprobado_para_pago', 'confirmacion_pago', 'pagada')
            AND f.fecha_aprobacion_finanzas IS NOT NULL
            AND f.fecha_pago_esperada BETWEEN ? AND ?
        ORDER BY f.fecha_aprobacion_finanzas DESC
    ");

        $stmt->execute([$fecha_inicio, $fecha_fin]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agregar etiqueta de estado para identificar el tipo de aprobación
        foreach ($resultados as &$factura) {
            switch ($factura['estado']) {
                case 'aprobado_para_pago':
                    $factura['tipo_aprobacion'] = '✅ Aprobado para Pago (Viernes)';
                    break;
                case 'confirmacion_pago':
                    $factura['tipo_aprobacion'] = '📅 Confirmación de Fecha';
                    break;
                case 'pagada':
                    $factura['tipo_aprobacion'] = '💰 Pagada';
                    break;
                default:
                    $factura['tipo_aprobacion'] = $factura['estado'];
            }
        }

        return $resultados;
    }

    /**
     * Obtener facturas pospuestas (que cambiaron su fecha de pago)
     * Busca en comentarios_finanzas los cambios de fecha
     */
    private function getFacturasPosPuestas($fecha_inicio, $fecha_fin)
    {
        $stmt = $this->pdo->prepare("
        SELECT 
            f.id,
            f.numero_factura,
            f.monto,
            f.fecha_pago_esperada as fecha_original_esperada,
            f.fecha_pago_propuesta as fecha_actual,
            f.fecha_pago_propuesta_original,
            f.fecha_pago_propuesta_anterior,
            f.comentarios_finanzas,
            f.fecha_aprobacion_finanzas,
            f.estado,
            f.semana_pago,
            p.nombre as proveedor_nombre,
            p.cardcode,
            p.tipo_proveedor,
            p.nit
        FROM facturas f
        JOIN proveedores p ON f.cardcode = p.cardcode
        WHERE f.estado IN ('aprobado_para_pago', 'confirmacion_pago', 'pagada')
            AND (
                f.comentarios_finanzas LIKE '%Cambió fecha de pago%'
                OR f.semana_pago = 'fecha_personalizada'
            )
            AND f.fecha_pago_esperada BETWEEN ? AND ?
        ORDER BY f.fecha_aprobacion_finanzas DESC
    ");

        $stmt->execute([$fecha_inicio, $fecha_fin]);
        $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Procesar cada factura para extraer la fecha original y nueva
        foreach ($facturas as &$factura) {
            $comentarios = $factura['comentarios_finanzas'] ?? '';

            $factura['nueva_fecha'] = null;
            $factura['fecha_anterior'] = null;
            $factura['motivo'] = 'No especificado';

            // La fecha original siempre es fecha_pago_esperada
            $fecha_esperada = $factura['fecha_original_esperada'] ?? null;

            if ($factura['semana_pago'] === 'fecha_personalizada' || strpos($comentarios, 'Cambió fecha de pago') !== false) {
                // Buscar nueva fecha en comentarios
                $patron = '/Cambió fecha de pago a (\d{4}-\d{2}-\d{2}) Motivo: (.*?)(?=\n|$)/';
                preg_match_all($patron, $comentarios, $matches, PREG_SET_ORDER);

                if (!empty($matches)) {
                    $ultimo_cambio = end($matches);
                    $factura['nueva_fecha'] = $ultimo_cambio[1];
                    $factura['motivo'] = trim($ultimo_cambio[2]);
                } else {
                    $factura['nueva_fecha'] = $factura['fecha_actual'];
                    $factura['motivo'] = 'Fecha personalizada';
                }

                // Fecha anterior siempre es la fecha_pago_esperada original
                $factura['fecha_anterior'] = $fecha_esperada;
            }

            switch ($factura['estado']) {
                case 'aprobado_para_pago':
                    $factura['tipo_aprobacion'] = '✅ Aprobado para Pago';
                    break;
                case 'confirmacion_pago':
                    $factura['tipo_aprobacion'] = '📅 Confirmación de Fecha';
                    break;
                case 'pagada':
                    $factura['tipo_aprobacion'] = '💰 Pagada';
                    break;
                default:
                    $factura['tipo_aprobacion'] = $factura['estado'];
            }
        }

        return $facturas;
    }

    /**
     * Obtener semanas disponibles para el reporte
     */
    private function getSemanasDisponibles()
    {
        $semanas = [];

        // Semana actual
        $hoy = new DateTime();
        $diaSemana = (int)$hoy->format('N');
        $inicio = clone $hoy;
        $fin = clone $hoy;
        $inicio->modify('-' . ($diaSemana - 1) . ' days');
        $fin->modify('+' . (7 - $diaSemana) . ' days');

        $semanas[] = [
            'label' => 'Semana Actual (' . $inicio->format('d/m/Y') . ' - ' . $fin->format('d/m/Y') . ')',
            'inicio' => $inicio->format('Y-m-d'),
            'fin' => $fin->format('Y-m-d'),
            'key' => 'actual'
        ];

        // Semana pasada
        $semana_pasada_inicio = clone $inicio;
        $semana_pasada_fin = clone $fin;
        $semana_pasada_inicio->modify('-7 days');
        $semana_pasada_fin->modify('-7 days');

        $semanas[] = [
            'label' => 'Semana Pasada (' . $semana_pasada_inicio->format('d/m/Y') . ' - ' . $semana_pasada_fin->format('d/m/Y') . ')',
            'inicio' => $semana_pasada_inicio->format('Y-m-d'),
            'fin' => $semana_pasada_fin->format('Y-m-d'),
            'key' => 'pasada'
        ];

        // Semana hace 2 semanas
        $semana_2_inicio = clone $semana_pasada_inicio;
        $semana_2_fin = clone $semana_pasada_fin;
        $semana_2_inicio->modify('-7 days');
        $semana_2_fin->modify('-7 days');

        $semanas[] = [
            'label' => 'Hace 2 Semanas (' . $semana_2_inicio->format('d/m/Y') . ' - ' . $semana_2_fin->format('d/m/Y') . ')',
            'inicio' => $semana_2_inicio->format('Y-m-d'),
            'fin' => $semana_2_fin->format('Y-m-d'),
            'key' => 'dos_semanas'
        ];

        return $semanas;
    }

    /**
     * Aprobar el pago de una factura (Contabilidad)
     * Cambia el estado de 'aprobado_para_pago' a 'pagada'
     */
    public function aprobarPago()
    {
        // Verificar acceso
        if (
            !isset($_SESSION['user']) ||
            !in_array($_SESSION['user']['rol'], ['contabilidad', 'admin'])
        ) {
            echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
            exit;
        }

        $factura_id = $_POST['factura_id'] ?? 0;
        $numero_comprobante = trim($_POST['numero_comprobante'] ?? '');
        $fecha_pago = $_POST['fecha_pago'] ?? date('Y-m-d');
        $monto_pagado = floatval($_POST['monto_pagado'] ?? 0);
        $observaciones = $_POST['observaciones'] ?? '';
        $usuario = $_SESSION['user']['username'] ?? 'contabilidad';

        if (!$factura_id || empty($numero_comprobante) || $monto_pagado <= 0) {
            echo json_encode(['success' => false, 'message' => 'Datos de pago incompletos']);
            exit;
        }

        // Verificar que la factura esté en estado aprobado_para_pago
        $stmtCheck = $this->pdo->prepare("SELECT estado, monto FROM facturas WHERE id = ?");
        $stmtCheck->execute([$factura_id]);
        $factura = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$factura) {
            echo json_encode(['success' => false, 'message' => 'Factura no encontrada']);
            exit;
        }

        if (!in_array($factura['estado'], ['aprobado_para_pago', 'confirmacion_pago'])) {
            echo json_encode(['success' => false, 'message' => 'La factura no está en estado aprobado para pago']);
            exit;
        }

        $this->pdo->beginTransaction();

        try {
            // Actualizar factura como pagada
            $stmt = $this->pdo->prepare("
    UPDATE facturas 
    SET estado = 'pagada',
        pagado = 1,
        fecha_pago_real = ?,
        numero_comprobante_pago = ?,
        observaciones_contabilidad = CONCAT(IFNULL(observaciones_contabilidad, ''), '\n[', NOW(), '] ', ?, ' PAGADO - Comprobante: ', ?, ' Monto: ', ?)
    WHERE id = ?
");
            $stmt->execute([$fecha_pago, $numero_comprobante, $usuario, $numero_comprobante, $monto_pagado, $factura_id]);

            // Registrar en tabla de pagos
            $stmtPago = $this->pdo->prepare("
    INSERT INTO pagos (factura_id, fecha_pago, monto_pagado, detalle)
    VALUES (?, ?, ?, ?)
");
            $stmtPago->execute([$factura_id, $fecha_pago, $monto_pagado, $observaciones]);

            $this->pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Pago registrado correctamente',
                'factura_id' => $factura_id
            ]);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error al registrar pago: ' . $e->getMessage()]);
        }
        exit;
    }

    private function getFacturasAprobadasPago()
    {
        $stmt = $this->pdo->prepare("
        SELECT f.*, p.nombre as proveedor_nombre, p.cardcode, p.tipo_proveedor
        FROM facturas f
        JOIN proveedores p ON f.cardcode = p.cardcode
        WHERE f.estado IN ('aprobado_para_pago', 'confirmacion_pago')
        ORDER BY f.fecha_aprobacion_finanzas ASC
        LIMIT 50
    ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
