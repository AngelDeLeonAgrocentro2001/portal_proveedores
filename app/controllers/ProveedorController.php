<?php
// app/controllers/ProveedorController.php
require_once BASE_PATH . 'app/models/ProveedorModel.php';
require_once BASE_PATH . 'app/models/FacturaModel.php';
require_once BASE_PATH . 'app/models/UsuarioModel.php';
require_once BASE_PATH . 'app/models/TransporteAPIModel.php';
class ProveedorController
{

    private $pdo;

    public function __construct()
    {
        $this->pdo = DatabasePortal::getInstance()->getPdo();
    }

    public function dashboard()
    {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        // Refrescar datos del usuario actual para asegurar consistencia
        $usuarioModel = new UsuarioModel();
        $userActualizado = $usuarioModel->getUserByCardcodeAndEmail(
            $_SESSION['user']['cardcode'],
            $_SESSION['user']['email']
        );

        if ($userActualizado) {
            // Actualizar la sesión con los datos más recientes
            $_SESSION['user']['username'] = $userActualizado['username'];
            $_SESSION['user']['rol'] = $userActualizado['rol'];
            $_SESSION['user']['nombre'] = $userActualizado['nombre'];
            $_SESSION['user']['nit'] = $userActualizado['nit'];
        } else {
            // getUserByCardcodeAndEmail exige p.estado = 'activo': si no encontró nada, el proveedor
            // fue desactivado (o el registro ya no existe) después de haber iniciado sesión.
            session_unset();
            session_destroy();
            header('Location: index.php?controller=auth&action=login&error=proveedor_inactivo');
            exit;
        }

        $cardcode = $_SESSION['user']['cardcode'];
        $rol      = $_SESSION['user']['rol'] ?? 'crear_contrasenas';

        $proveedorModel = new ProveedorModel();
        $facturaModel   = new FacturaModel();

        $proveedor = $proveedorModel->getProveedorByCardcode($cardcode);
        $resumen   = $proveedorModel->getResumenFacturas($cardcode);
        $facturas  = $proveedorModel->getUltimasFacturas($cardcode, 5);
        $pagos     = $facturaModel->getUltimosPagos($cardcode, 5);

        // Días de crédito reales desde SAP (no el valor fijo guardado en proveedores.dias_credito,
        // que puede quedar desactualizado). Si SAP no responde, se usa el valor local como respaldo.
        $diasCreditoSAP = $proveedorModel->getDiasCreditoSAP($cardcode);
        $diasCredito = $diasCreditoSAP ?? ($proveedor['dias_credito'] ?? 0);

        // Control según rol
        $mostrarPagos = in_array($rol, ['admin', 'consultas']);
        $esAdmin      = ($rol === 'admin');
        $puedeCrear   = true; // todos pueden crear contraseñas

        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/dashboard.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }

    // ====================== NUEVO: REPORTAR FACTURA ======================
    public function reportarFactura()
    {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        $cardcode = $_SESSION['user']['cardcode'];
        $id_usuario = $_SESSION['user']['id'] ?? null;  // ← Obtener ID del usuario
        $rol      = $_SESSION['user']['rol'] ?? 'crear_contrasenas';
        $error    = '';
        $success  = '';

        $preseleccion = trim($_GET['preseleccion'] ?? '');

        $proveedorModel = new ProveedorModel();
        $proveedor = $proveedorModel->getProveedorByCardcode($cardcode);

        if (!$proveedor || ($proveedor['estado'] ?? 'activo') !== 'activo') {
            session_unset();
            session_destroy();
            header('Location: index.php?controller=auth&action=login&error=proveedor_inactivo');
            exit;
        }

        // Los proveedores de material de empaque seleccionan Entrada de Mercancía (SAP OPDN)
        // en vez de Orden de Compra (OPOR) al reportar su factura — misma forma de datos
        // (docentry/numero_oc/fecha/monto/moneda/estado), así que el modal de selección y el
        // envío a SAP (ContabilidadController::enviarSAP) ya saben distinguir cuál es cuál
        // según el tipo de proveedor de la factura.
        $esMaterialEmpaque = ($proveedor['tipo_proveedor'] ?? '') === 'material_empaque';
        $ordenesAbiertas = $esMaterialEmpaque
            ? $proveedorModel->getEntradasMercanciaFlatByCardcode($cardcode, 'abierta')
            : $proveedorModel->getOrdenesCompraByCardcode($cardcode, 'abierta');

        // Saldo pendiente real en SAP por orden (1 sola consulta para toda la lista), para que el
        // proveedor vea cuánto le queda disponible al elegir la orden a la que va a enlazar su
        // factura — igual que en "Mis Órdenes de Compra". No aplica a Entrada de Mercancía
        // (OPDN/PDN1, otra numeración de DocEntry).
        if (!$esMaterialEmpaque) {
            $saldosPendientes = $proveedorModel->getSaldosPendientesSAP(array_column($ordenesAbiertas, 'docentry'));
            foreach ($ordenesAbiertas as &$oc) {
                $oc['saldo_pendiente'] = $saldosPendientes[(int)$oc['docentry']] ?? 0.0;
            }
            unset($oc);
        }

        // Verificar si el proveedor está en el grupo de doble factura
        $pdo = DatabasePortal::getInstance()->getPdo();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM proveedores_doble_factura WHERE cardcode = ? AND activo = 1");
        $stmt->execute([$cardcode]);
        $esDobleFactura = $stmt->fetchColumn() > 0;

        $facturasSAT = [];
        try {
            $dbCajas = DatabaseCajas::getInstance()->getPdo();
            $nit = trim($proveedor['nit'] ?? '');

            if ($nit) {
                $stmt = $dbCajas->prepare("
                SELECT serie, numero_dte, fecha_emision, gran_total, iva, nombre_emisor, usado
                FROM dte 
                WHERE nit_emisor = ?
                  AND (usado IS NULL OR usado = 'X' OR usado = '')
                ORDER BY fecha_emision DESC
            ");
                $stmt->execute([$nit]);
                $facturasSAT = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            error_log("Error al cargar facturas SAT: " . $e->getMessage());
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $facturaModel = new FacturaModel();
            // Pasar el id_usuario al método reportarFactura
            $resultado = $facturaModel->reportarFactura($_POST, $_FILES, $cardcode, $id_usuario);

            if ($resultado['success']) {
                $_SESSION['last_report'] = [
                    'success'      => true,
                    'contrasena'   => $resultado['contrasena'],
                    'esLunes'      => $resultado['esLunes'],
                    'proximoLunes' => $resultado['proximoLunes'],
                    'diasCredito'  => $resultado['diasCredito'] ?? 30,
                    'mensaje_adicional' => $resultado['mensaje_adicional'] ?? ''
                ];

                $success = "Factura reportada correctamente con contraseña: " . $resultado['contrasena'];

                // Recargar lista de facturas SAT
                $facturasSAT = [];
                try {
                    $dbCajas = DatabaseCajas::getInstance()->getPdo();
                    $nit = trim($proveedor['nit'] ?? '');
                    if ($nit) {
                        $stmt = $dbCajas->prepare("
                        SELECT serie, numero_dte, fecha_emision, gran_total, iva, nombre_emisor, usado
                        FROM dte 
                        WHERE nit_emisor = ?
                          AND (usado IS NULL OR usado = 'X' OR usado = '')
                        ORDER BY fecha_emision DESC
                    ");
                        $stmt->execute([$nit]);
                        $facturasSAT = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                } catch (Exception $e) {
                    error_log("Error recargando facturas SAT: " . $e->getMessage());
                }
            } else {
                $error = $resultado['message'] ?? 'Error al reportar la factura';
            }
        }
$cardcode_js = $_SESSION['user']['cardcode'];
        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/reportar-factura.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }

    public function misFacturas()
    {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        $cardcode = $_SESSION['user']['cardcode'];
        $facturaModel = new FacturaModel();

        // Filtro por estado (opcional)
        $estado = $_GET['estado'] ?? '';

        $facturas = $facturaModel->getFacturasByProveedor($cardcode, $estado);

        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/mis-facturas.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }

    // Descargar archivo de forma segura
    public function descargar()
    {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        $id     = $_GET['id'] ?? 0;
        $tipo   = $_GET['tipo'] ?? ''; // factura, orden, constancia

        if (!$id || !in_array($tipo, ['factura', 'orden', 'constancia'])) {
            die("Acceso denegado");
        }

        $facturaModel = new FacturaModel();
        $factura = $facturaModel->getFacturaById($id, $_SESSION['user']['cardcode']);

        if (!$factura) {
            die("Factura no encontrada o no pertenece a tu cuenta");
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
        }

        $rutaRelativa = $factura[$campo];
        if (empty($rutaRelativa)) {
            die("Archivo no disponible");
        }

        $rutaCompleta = BASE_PATH . $rutaRelativa;

        if (!file_exists($rutaCompleta)) {
            die("El archivo no existe en el servidor");
        }

        // Forzar descarga
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($rutaCompleta) . '"');
        header('Content-Length: ' . filesize($rutaCompleta));
        readfile($rutaCompleta);
        exit;
    }

    // Ver/descargar la constancia de retención de IVA que Contabilidad subió para esta factura.
    // ?modo=ver la abre inline en el navegador; cualquier otro valor (o ausente) fuerza la descarga.
    public function descargarRetencionIVA()
    {
        $this->descargarRetencion('pdf_retencion_iva', 'retencion_iva');
    }

    // Igual que arriba, para la constancia de retención de ISR.
    public function descargarRetencionISR()
    {
        $this->descargarRetencion('pdf_retencion_isr', 'retencion_isr');
    }

    private function descargarRetencion($campo, $prefijoArchivo)
    {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        $id = $_GET['id'] ?? 0;
        $cardcode = $_SESSION['user']['cardcode'];

        $stmt = $this->pdo->prepare("SELECT $campo FROM facturas WHERE id = ? AND cardcode = ?");
        $stmt->execute([$id, $cardcode]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$factura || empty($factura[$campo])) {
            die("Documento no disponible");
        }

        $ruta = BASE_PATH . $factura[$campo];
        if (!file_exists($ruta)) {
            die("El archivo no existe en el servidor");
        }

        $disposition = (($_GET['modo'] ?? '') === 'ver') ? 'inline' : 'attachment';

        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposition . '; filename="' . $prefijoArchivo . '_' . $id . '.pdf"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }

    // Envía la constancia de retención (IVA o ISR) al correo del proveedor en sesión (a sí mismo).
    public function enviarRetencionPorCorreo()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
            exit;
        }

        $id = $_POST['id'] ?? 0;
        $tipo = $_POST['tipo'] ?? ''; // 'iva' o 'isr'
        $cardcode = $_SESSION['user']['cardcode'];
        $emailDestino = $_SESSION['user']['email'] ?? '';

        if (!$id || !in_array($tipo, ['iva', 'isr'], true) || empty($emailDestino)) {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
            exit;
        }

        $campo = $tipo === 'iva' ? 'pdf_retencion_iva' : 'pdf_retencion_isr';
        $etiqueta = $tipo === 'iva' ? 'IVA' : 'ISR';

        $stmt = $this->pdo->prepare("SELECT numero_factura, $campo AS ruta FROM facturas WHERE id = ? AND cardcode = ?");
        $stmt->execute([$id, $cardcode]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$factura || empty($factura['ruta'])) {
            echo json_encode(['success' => false, 'message' => 'Documento no disponible']);
            exit;
        }

        $ruta = BASE_PATH . $factura['ruta'];
        if (!file_exists($ruta)) {
            echo json_encode(['success' => false, 'message' => 'El archivo no existe en el servidor']);
            exit;
        }

        require_once BASE_PATH . 'app/models/MailerService.php';

        $nombreProveedor = $_SESSION['user']['nombre'] ?? $_SESSION['user']['username'] ?? 'Proveedor';
        $numeroFactura = htmlspecialchars($factura['numero_factura']);
        $asunto = "Constancia de Retención $etiqueta - Factura {$factura['numero_factura']}";
        $cuerpoHtml = $this->plantillaCorreoRetencion(htmlspecialchars($nombreProveedor), $etiqueta, $numeroFactura);
        $cuerpoTexto = "Hola $nombreProveedor,\n\nAdjunto la constancia de retención de $etiqueta correspondiente a la factura {$factura['numero_factura']}.\n\nPortal de Proveedores - Agrocentro";
        $nombreAdjunto = "retencion_{$tipo}_{$factura['numero_factura']}.pdf";

        $enviado = MailerService::enviarConAdjunto(
            $emailDestino,
            $nombreProveedor,
            $asunto,
            $cuerpoHtml,
            $cuerpoTexto,
            $ruta,
            $nombreAdjunto
        );

        if ($enviado) {
            echo json_encode(['success' => true, 'message' => "Correo enviado a $emailDestino"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se pudo enviar el correo. Intenta de nuevo más tarde.']);
        }
        exit;
    }

    // HTML del correo de constancia de retención IVA/ISR. $nombre, $etiqueta y $numeroFactura ya vienen escapados.
    private function plantillaCorreoRetencion($nombre, $etiqueta, $numeroFactura)
    {
        $fecha = date('d/m/Y');
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0; padding:0; background-color:#F2EEE7; font-family: 'Segoe UI', Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F2EEE7; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="background-color:#0E1E14; background-image:linear-gradient(135deg,#0E1E14 0%,#16301f 100%); padding:28px 32px; border-bottom:4px solid #4CAF50;" align="center">
                            <p style="margin:0; color:#7DA13D; font-size:12px; letter-spacing:2px; text-transform:uppercase; font-weight:600;">Portal de Proveedores</p>
                            <p style="margin:4px 0 0; color:#ffffff; font-size:22px; font-weight:700;">Agrocentro</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:36px 32px 8px;">
                            <p style="margin:0 0 16px; color:#282828; font-size:16px;">Hola <strong>{$nombre}</strong>,</p>
                            <p style="margin:0 0 24px; color:#4a4a4a; font-size:15px; line-height:1.6;">
                                Te compartimos la constancia de <strong>Retención de {$etiqueta}</strong> correspondiente a la factura que reportaste en el portal. Encontrarás el documento en <strong>PDF adjunto</strong> a este correo.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F2EEE7; border-left:4px solid #0D7C66; border-radius:8px;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding:6px 0; color:#669149; font-size:12px; text-transform:uppercase; letter-spacing:1px; font-weight:600; width:45%;">Tipo de retención</td>
                                                <td style="padding:6px 0; color:#0E1E14; font-size:15px; font-weight:700;">{$etiqueta}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#669149; font-size:12px; text-transform:uppercase; letter-spacing:1px; font-weight:600;">Factura</td>
                                                <td style="padding:6px 0; color:#0E1E14; font-size:15px; font-weight:700;">{$numeroFactura}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#669149; font-size:12px; text-transform:uppercase; letter-spacing:1px; font-weight:600;">Fecha de envío</td>
                                                <td style="padding:6px 0; color:#0E1E14; font-size:15px;">{$fecha}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 36px;">
                            <p style="margin:0; color:#8a8a8a; font-size:13px; line-height:1.6;">
                                Si tienes alguna duda sobre este documento, comunícate con el área de Contabilidad de Agrocentro.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#EBEBEB; padding:18px 32px; text-align:center;">
                            <p style="margin:0; color:#8a8a8a; font-size:12px;">Este es un mensaje automático del Portal de Proveedores &mdash; Agrocentro.<br>Por favor no respondas a este correo.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    // Ver todos los pagos recibidos
    public function pagos()
    {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        $rol = $_SESSION['user']['rol'] ?? 'crear_contrasenas';

        // Solo admin y consultas pueden ver pagos
        if (!in_array($rol, ['admin', 'consultas'])) {
            header('Location: index.php?controller=proveedor&action=dashboard');
            exit;
        }

        $cardcode = $_SESSION['user']['cardcode'];
        $facturaModel = new FacturaModel();
        $pagos = $facturaModel->getPagosByProveedor($cardcode);

        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/pagos.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }

    public function ordenesCompra()
    {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        $cardcode = $_SESSION['user']['cardcode'];
        $model = new ProveedorModel();

        $estadoFiltro = $_GET['estado'] ?? 'abierta';

        // Los proveedores de material de empaque ven Entrada de Mercancía (SAP OPDN/PDN1) en
        // vez de Órdenes de Compra (OPOR) — esos documentos no manejan orden de compra, sino
        // recepción directa de mercancía. Todo lo demás de esta página sigue igual para el
        // resto de tipos de proveedor.
        $proveedor = $model->getProveedorByCardcode($cardcode);
        $esMaterialEmpaque = ($proveedor['tipo_proveedor'] ?? '') === 'material_empaque';

        if ($esMaterialEmpaque) {
            $entradasMercancia = $model->getEntradasMercanciaByCardcode($cardcode, $estadoFiltro);
        } else {
            $ordenes = $model->getOrdenesCompraByCardcode($cardcode, $estadoFiltro);
            $totalMonto = array_sum(array_column($ordenes, 'monto'));

            // Saldo pendiente real en SAP por orden (1 sola consulta para toda la lista), para que
            // el proveedor vea cuánto le queda disponible antes de elegirla al reportar su factura.
            $saldosPendientes = $model->getSaldosPendientesSAP(array_column($ordenes, 'docentry'));
            foreach ($ordenes as &$oc) {
                $oc['saldo_pendiente'] = $saldosPendientes[(int)$oc['docentry']] ?? 0.0;
            }
            unset($oc);
            $totalSaldoPendiente = array_sum(array_column($ordenes, 'saldo_pendiente'));
        }

        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/ordenes-compra.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }



    public function facturasSAT()
    {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        $cardcode = $_SESSION['user']['cardcode'];
        $proveedorModel = new ProveedorModel();
        $proveedor = $proveedorModel->getProveedorByCardcode($cardcode);

        $nit = trim($proveedor['nit'] ?? '');
        $facturasSAT = [];
        $errorSAT = '';
        $totalFacturasSAT = 0;

        // Paginación (25 por página) y buscador (número de factura, monto o fecha) — la lista
        // completa se venía trayendo de una sola vez, sin límite, y con NIT como único filtro.
        $porPagina = 15;
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        $buscar = trim($_GET['buscar_sat'] ?? '');

        if (empty($nit)) {
            $errorSAT = "No se encontró NIT registrado para este proveedor.";
        } else {
            try {
                $dbCajas = DatabaseCajas::getInstance()->getPdo();

                $where = "WHERE nit_emisor = :nit";
                $params = ['nit' => $nit];

                if ($buscar !== '') {
                    // Una sola búsqueda cubre número de factura (serie + número DTE), monto y
                    // fecha (en formato dd/mm/aaaa, como se muestra en la tabla, o aaaa-mm-dd).
                    $where .= " AND (
                        CONCAT(COALESCE(serie, ''), ' ', COALESCE(numero_dte, '')) LIKE :buscar1
                        OR CAST(gran_total AS CHAR) LIKE :buscar2
                        OR DATE_FORMAT(fecha_emision, '%d/%m/%Y') LIKE :buscar3
                        OR CAST(fecha_emision AS CHAR) LIKE :buscar4
                    )";
                    $like = '%' . $buscar . '%';
                    $params['buscar1'] = $like;
                    $params['buscar2'] = $like;
                    $params['buscar3'] = $like;
                    $params['buscar4'] = $like;
                }

                $stmtCount = $dbCajas->prepare("SELECT COUNT(*) FROM dte $where");
                $stmtCount->execute($params);
                $totalFacturasSAT = (int)$stmtCount->fetchColumn();

                $totalPaginas = max(1, (int)ceil($totalFacturasSAT / $porPagina));
                $pagina = min($pagina, $totalPaginas);
                $offset = ($pagina - 1) * $porPagina;

                $sql = "SELECT
                            serie,
                            numero_dte,
                            fecha_emision,
                            gran_total,
                            iva,
                            nombre_emisor,
                            usado
                        FROM dte
                        $where
                        ORDER BY fecha_emision DESC
                        LIMIT $porPagina OFFSET $offset";

                $stmt = $dbCajas->prepare($sql);
                $stmt->execute($params);
                $facturasSAT = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $errorSAT = "Error al consultar facturas del SAT: " . $e->getMessage();
                error_log("❌ " . $errorSAT);
            }
        }

        $totalPaginas = max(1, (int)ceil($totalFacturasSAT / $porPagina));

        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/facturas-sat.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }

    // ====================== GESTIÓN DE USUARIOS (SOLO ADMIN) ======================
    public function gestionarUsuarios()
    {
        if (!isset($_SESSION['user']) || $_SESSION['user']['rol'] !== 'admin') {
            header('Location: index.php?controller=proveedor&action=dashboard');
            exit;
        }

        $cardcode = $_SESSION['user']['cardcode'];
        $error = '';
        $success = '';

        $usuarioModel = new UsuarioModel();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email     = trim($_POST['email'] ?? '');
            $username  = trim($_POST['username'] ?? '');
            $password  = $_POST['password'] ?? '';
            $rol       = $_POST['rol'] ?? 'crear_contrasenas';

            if (empty($email) || empty($username) || empty($password)) {
                $error = "Todos los campos son obligatorios";
            } else {
                $creadoPor = $_SESSION['user']['username'] ?? null;
                $resultado = $usuarioModel->crearUsuario($cardcode, $email, $username, $password, $rol, $creadoPor);

                if ($resultado) {
                    $success = "Usuario creado correctamente con rol: " . ucfirst(str_replace('_', ' ', $rol));
                } else {
                    $error = "Error al crear el usuario. Puede que el username o email ya exista.";
                }
            }
        }

        // Solo los usuarios que este admin creó desde aquí mismo — no todos los del cardcode
        // (los que ya existían o que dio de alta SuperAdmin no deben aparecerle).
        $usuarios = $usuarioModel->getUsuariosCreadosPor($cardcode, $_SESSION['user']['username'] ?? '');

        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/gestionar-usuarios.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }

    // ====================== DESCARGAR PDF DE CONTRASEÑA ======================
    public function pdfContraseña()
    {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        $id = $_GET['id'] ?? 0;
        if (!$id) {
            die("ID de factura no válido");
        }

        $facturaModel = new FacturaModel();
        $factura = $facturaModel->getFacturaById($id, $_SESSION['user']['cardcode']);

        if (!$factura || empty($factura['contrasena_pago'])) {
            die("Factura no encontrada o sin contraseña");
        }

        // Limpiar el número de orden de compra - si viene como JSON array, extraer solo el número
        $ordenCompra = $factura['ordenes_relacionadas'] ?? '';
        // Si parece un array JSON (ej: ["51147"]), extraer solo el número
        if (preg_match('/\[\"(\d+)\"\]/', $ordenCompra, $matches)) {
            $ordenCompra = $matches[1];
        } elseif (preg_match('/\[(\d+)\]/', $ordenCompra, $matches)) {
            $ordenCompra = $matches[1];
        }

        // Cargar datos del proveedor
        $proveedorModel = new ProveedorModel();
        $proveedor = $proveedorModel->getProveedorByCardcode($_SESSION['user']['cardcode']);

        // ====================== GENERAR PDF ======================
        require_once BASE_PATH . 'vendor/tecnickcom/tcpdf/tcpdf.php';

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Agrocentro');
        $pdf->SetAuthor('Portal Proveedores');
        $pdf->SetTitle('Contraseña de Pago - ' . $factura['numero_factura']);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();

        // Logo usando URL externa (desde internet)
        $logoUrl = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSkLb4zCuSBqdoqYloQhjlciiOINIhOwZrJIA&s';

        // Intentar descargar la imagen temporalmente
        $logoContent = @file_get_contents($logoUrl);
        if ($logoContent !== false) {
            // Crear archivo temporal
            $tempLogo = tempnam(sys_get_temp_dir(), 'logo_');
            file_put_contents($tempLogo, $logoContent);
            $pdf->Image($tempLogo, 15, 15, 45);
            unlink($tempLogo); // Eliminar archivo temporal
        } else {
            // Si no se puede descargar, mostrar texto en lugar del logo
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

        // Datos del proveedor
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(40, 7, 'CÓDIGO:', 0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 7, $proveedor['cardcode'] ?? $_SESSION['user']['cardcode'], 0, 1);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(40, 7, 'PROVEEDOR:', 0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 7, mb_substr($proveedor['nombre'] ?? 'N/A', 0, 60), 0, 1);

        $pdf->Cell(40, 7, 'TIPO DE DOCUMENTO:', 0);
        $pdf->Cell(0, 7, 'FACTURA', 0, 1);

        $pdf->Ln(8);

        // Tabla
        $etiquetaOrdenPdf = (($proveedor['tipo_proveedor'] ?? '') === 'material_empaque') ? 'Entrada de Mercancía' : 'Orden de Compra';
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(60, 8, $etiquetaOrdenPdf, 1, 0, 'C');
        $pdf->Cell(60, 8, 'Documento', 1, 0, 'C');
        $pdf->Cell(35, 8, 'Fecha', 1, 0, 'C');
        $pdf->Cell(35, 8, 'Valor', 1, 1, 'C');

        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(60, 8, $ordenCompra, 1, 0, 'C');  // Usar la variable limpiada
        $pdf->Cell(60, 8, $factura['numero_factura'], 1, 0, 'C');
        $pdf->Cell(35, 8, date('d/m/Y', strtotime($factura['fecha_emision'])), 1, 0, 'C');
        $pdf->Cell(35, 8, 'Q ' . number_format($factura['monto'], 2), 1, 1, 'C');

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(155, 8, 'TOTAL', 1, 0, 'R');
        $pdf->Cell(35, 8, 'Q ' . number_format($factura['monto'], 2), 1, 1, 'C');

        $pdf->Ln(10);

        // Información final
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(60, 7, 'FECHA DE PAGO:', 0);
        $pdf->Cell(0, 7, date('d/m/Y', strtotime($factura['fecha_pago_esperada'])), 0, 1);

        $pdf->Cell(60, 7, 'FECHA DE CREACIÓN:', 0);
        $pdf->Cell(0, 7, date('d/m/Y', strtotime($factura['fecha_emision'])), 0, 1);

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(60, 10, 'No. CONTRASEÑA:', 0);
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetTextColor(0, 100, 0);
        $pdf->Cell(0, 10, $factura['contrasena_pago'], 0, 1);

        $pdf->SetTextColor(0, 0, 0);

        // Salida
        $filename = 'Contraseña_' . $factura['numero_factura'] . '.pdf';
        $pdf->Output($filename, 'I');
        exit;
    }

    // ====================== PDF ORDEN DE COMPRA CON DETALLE COMPLETO (SIN WARNINGS) ======================
    public function pdfOrdenCompra()
    {
        // Deshabilitar temporalmente la salida de errores/warnings para el PDF
        error_reporting(0);
        ini_set('display_errors', 0);

        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        $docentry = $_GET['docentry'] ?? 0;
        if (!$docentry) {
            die("Número de orden no válido");
        }

        $cardcode = $_SESSION['user']['cardcode'];

        // Obtener el detalle COMPLETO de la orden desde SAP
        try {
            $sap = new DatabaseSAP();
            $conexion = $sap->CONEXION_HANA('T_GT_AGROCENTRO_2016');

            // Query mejorado para obtener TODOS los datos
            $query = "
            SELECT
                T0.\"DocNum\" as \"NoDocumento\", 
                T0.\"CardCode\" as \"CodigoProveedor\", 
                T0.\"CardName\" as \"NombreProveedor\",
                T0.\"DocDate\" as \"FechaDocumento\", 
                T1.\"OcrCode\" as \"CentroCosto\",
                T1.\"AcctCode\" as \"CodigoCuenta\",
                T2.\"AcctName\" as \"NombreCuenta\", 
                T1.\"Dscription\" as \"DescripcionLinea\",
                T0.\"DocTotal\" as \"TotalDocumento\",
                COALESCE(T0.\"Comments\", '') as \"Observaciones\",
                T1.\"LineTotal\" as \"MontoLinea\",
                T1.\"LineNum\" as \"NumeroLinea\"
            FROM \"T_GT_AGROCENTRO_2016\".OPOR T0
                INNER JOIN \"T_GT_AGROCENTRO_2016\".POR1 T1 ON T0.\"DocEntry\" = T1.\"DocEntry\"
                INNER JOIN \"T_GT_AGROCENTRO_2016\".OACT T2 ON T1.\"AcctCode\" = T2.\"AcctCode\"
            WHERE T0.\"CardCode\" = ? 
                AND T0.\"DocEntry\" = ?
                AND T0.\"DocStatus\" = 'O'
            ORDER BY T1.\"LineNum\"
        ";

            $stmt = odbc_prepare($conexion, $query);
            if (!$stmt) {
                throw new Exception("Error preparando consulta: " . odbc_errormsg($conexion));
            }

            if (!odbc_execute($stmt, [$cardcode, $docentry])) {
                throw new Exception("Error ejecutando consulta: " . odbc_errormsg($conexion));
            }

            $lineasDetalle = [];
            $ordenData = null;
            $sumaLineas = 0;

            while ($row = odbc_fetch_object($stmt)) {
                // Limpiar y sanitizar datos (convertir encoding)
                $centroCosto = mb_convert_encoding(trim($row->CentroCosto ?? ''), 'UTF-8', 'auto');
                $codigoCuenta = mb_convert_encoding(trim($row->CodigoCuenta ?? ''), 'UTF-8', 'auto');
                $nombreCuenta = mb_convert_encoding(trim($row->NombreCuenta ?? ''), 'UTF-8', 'auto');
                $descripcion = mb_convert_encoding(trim($row->DescripcionLinea ?? ''), 'UTF-8', 'auto');
                $montoLinea = (float)($row->MontoLinea ?? 0);
                $sumaLineas += $montoLinea;

                // Agregar todas las líneas
                $lineasDetalle[] = [
                    'centro_costo'  => $centroCosto ?: '-',
                    'codigo_cuenta' => $codigoCuenta ?: '-',
                    'nombre_cuenta' => $nombreCuenta ?: '-',
                    'descripcion'   => $descripcion ?: 'Sin descripción',
                    'monto_linea'   => $montoLinea
                ];

                // Tomar datos de la cabecera (del primer registro)
                if ($ordenData === null) {
                    $ordenData = [
                        'numero_oc'     => trim($row->NoDocumento ?? ''),
                        'fecha'         => $row->FechaDocumento ?? date('Y-m-d'),
                        'total'         => (float)($row->TotalDocumento ?? 0),
                        'observaciones' => mb_convert_encoding(trim($row->Observaciones ?? ''), 'UTF-8', 'auto'),
                        'nombre_proveedor' => mb_convert_encoding(trim($row->NombreProveedor ?? ''), 'UTF-8', 'auto')
                    ];
                }
            }

            odbc_free_result($stmt);
            odbc_close($conexion);

            // Validar que se encontraron datos
            if ($ordenData === null || empty($ordenData['numero_oc'])) {
                throw new Exception("No se encontró la orden de compra");
            }

            // Log para depuración
            error_log("Orden: {$ordenData['numero_oc']}, Total SAP: {$ordenData['total']}, Suma líneas: $sumaLineas, Líneas encontradas: " . count($lineasDetalle));

            // Si no hay líneas de detalle, crear una línea por defecto
            if (empty($lineasDetalle)) {
                $lineasDetalle[] = [
                    'centro_costo'  => '-',
                    'codigo_cuenta' => '-',
                    'nombre_cuenta' => '-',
                    'descripcion'   => 'Sin líneas de detalle',
                    'monto_linea'   => $ordenData['total']
                ];
            }
        } catch (Exception $e) {
            error_log("Error al obtener detalle de orden SAP: " . $e->getMessage());
            die("Error al cargar el detalle de la orden. Por favor, contacte al administrador.");
        }

        // ====================== GENERAR PDF ======================
        require_once BASE_PATH . 'vendor/tecnickcom/tcpdf/tcpdf.php';

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        // Logo usando URL externa
        $logoUrl = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSkLb4zCuSBqdoqYloQhjlciiOINIhOwZrJIA&s';
        $logoContent = @file_get_contents($logoUrl);
        if ($logoContent !== false) {
            $tempLogo = tempnam(sys_get_temp_dir(), 'logo_');
            file_put_contents($tempLogo, $logoContent);
            $pdf->Image($tempLogo, 12, 12, 40);
            unlink($tempLogo);
        } else {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetXY(12, 12);
            $pdf->Cell(40, 15, 'AGROCENTRO', 0, 0, 'C');
        }

        $pdf->SetY(15);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, 'ORDEN DE COMPRA', 0, 1, 'R');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'No. ' . htmlspecialchars($ordenData['numero_oc']), 0, 1, 'R');

        $fechaValida = !empty($ordenData['fecha']) ? date('d/m/Y', strtotime($ordenData['fecha'])) : 'Fecha no disponible';
        $pdf->Cell(0, 5, 'Fecha: ' . $fechaValida, 0, 1, 'R');

        $pdf->Ln(8);

        // Datos del proveedor
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(35, 7, 'PROVEEDOR:', 0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 7, mb_substr(htmlspecialchars($ordenData['nombre_proveedor']), 0, 60), 0, 1);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(35, 7, 'CÓDIGO:', 0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 7, htmlspecialchars($cardcode), 0, 1);

        $pdf->Ln(5);

        // ========== TABLA DE DETALLE ==========
        // Definir anchos de columna (en mm)
        $anchoCentro = 22;
        $anchoCuenta = 22;
        $anchoNombre = 45;
        $anchoDescrip = 55;
        $anchoMonto = 30;

        // Encabezados
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($anchoCentro, 8, 'CENTRO COSTO', 1, 0, 'C');
        $pdf->Cell($anchoCuenta, 8, 'CUENTA', 1, 0, 'C');
        $pdf->Cell($anchoNombre, 8, 'NOMBRE CUENTA', 1, 0, 'C');
        $pdf->Cell($anchoDescrip, 8, 'DESCRIPCIÓN', 1, 0, 'C');
        $pdf->Cell($anchoMonto, 8, 'TOTAL', 1, 1, 'C');

        // Datos
        $pdf->SetFont('helvetica', '', 7);

        // Calcular altura total de la tabla para verificar si cabe en la página
        $alturaTotalTabla = 0;
        foreach ($lineasDetalle as $linea) {
            $descripcion = htmlspecialchars($linea['descripcion']);
            $alturaDescrip = $pdf->getStringHeight($anchoDescrip, $descripcion);
            $alturaFila = max(6, $alturaDescrip);
            $alturaTotalTabla += $alturaFila;
        }

        // Si la tabla es muy alta, permitir salto de página
        if ($pdf->GetY() + $alturaTotalTabla + 50 > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
            $pdf->AddPage();
        }

        foreach ($lineasDetalle as $idx => $linea) {
            $centroCosto = htmlspecialchars($linea['centro_costo']);
            $codigoCuenta = htmlspecialchars($linea['codigo_cuenta']);
            $nombreCuenta = htmlspecialchars($linea['nombre_cuenta']);
            $descripcion = htmlspecialchars($linea['descripcion']);
            $monto = 'Q ' . number_format($linea['monto_linea'], 2);

            // Calcular altura necesaria para esta fila
            $alturaCentro = $pdf->getStringHeight($anchoCentro, $centroCosto);
            $alturaCuenta = $pdf->getStringHeight($anchoCuenta, $codigoCuenta);
            $alturaNombre = $pdf->getStringHeight($anchoNombre, $nombreCuenta);
            $alturaDescrip = $pdf->getStringHeight($anchoDescrip, $descripcion);
            $alturaMonto = $pdf->getStringHeight($anchoMonto, $monto);

            $alturaFila = max($alturaCentro, $alturaCuenta, $alturaNombre, $alturaDescrip, $alturaMonto, 6);

            // Guardar posición
            $x = $pdf->GetX();
            $y = $pdf->GetY();

            // Verificar si la fila cabe en la página actual
            if ($y + $alturaFila > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
                $pdf->AddPage();
                // Re-imprimir encabezados en la nueva página
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->Cell($anchoCentro, 8, 'CENTRO COSTO', 1, 0, 'C');
                $pdf->Cell($anchoCuenta, 8, 'CUENTA', 1, 0, 'C');
                $pdf->Cell($anchoNombre, 8, 'NOMBRE CUENTA', 1, 0, 'C');
                $pdf->Cell($anchoDescrip, 8, 'DESCRIPCIÓN', 1, 0, 'C');
                $pdf->Cell($anchoMonto, 8, 'TOTAL', 1, 1, 'C');
                $pdf->SetFont('helvetica', '', 7);
                $x = $pdf->GetX();
                $y = $pdf->GetY();
            }

            // Imprimir cada celda de la fila
            $pdf->MultiCell($anchoCentro, $alturaFila, $centroCosto, 1, 'L', 0, 0, $x, $y, true, 0, false, true, $alturaFila, true);
            $x += $anchoCentro;
            $pdf->MultiCell($anchoCuenta, $alturaFila, $codigoCuenta, 1, 'L', 0, 0, $x, $y, true, 0, false, true, $alturaFila, true);
            $x += $anchoCuenta;
            $pdf->MultiCell($anchoNombre, $alturaFila, $nombreCuenta, 1, 'L', 0, 0, $x, $y, true, 0, false, true, $alturaFila, true);
            $x += $anchoNombre;
            $pdf->MultiCell($anchoDescrip, $alturaFila, $descripcion, 1, 'L', 0, 0, $x, $y, true, 0, false, true, $alturaFila, true);
            $x += $anchoDescrip;
            $pdf->MultiCell($anchoMonto, $alturaFila, $monto, 1, 'R', 0, 1, $x, $y, true, 0, false, true, $alturaFila, true);
        }

        // TOTAL
        $pdf->SetFont('helvetica', 'B', 9);
        $totalAncho = $anchoCentro + $anchoCuenta + $anchoNombre + $anchoDescrip;
        $pdf->Cell($totalAncho, 8, 'TOTAL DOCUMENTO', 1, 0, 'R');
        $pdf->Cell($anchoMonto, 8, 'Q ' . number_format($ordenData['total'], 2), 1, 1, 'R');

        $pdf->Ln(8);

        // Observaciones
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 6, 'OBSERVACIONES', 0, 1);
        $pdf->SetFont('helvetica', '', 9);
        $observaciones = !empty($ordenData['observaciones']) ? htmlspecialchars($ordenData['observaciones']) : 'Sin observaciones';
        $pdf->MultiCell(0, 5, $observaciones, 0, 'L');

        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->MultiCell(0, 4, "Documento generado desde SAP - Portal de Proveedores Agrocentro.", 0, 'C');

        // Pie de página
        $pdf->SetY(-20);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(0, 5, 'AUTORIZADO POR', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Agrocentro - Departamento de Compras', 0, 1, 'C');

        // Limpiar buffer y enviar PDF
        if (ob_get_length()) {
            ob_clean();
        }

        $filename = 'Orden_Compra_' . $ordenData['numero_oc'] . '.pdf';
        $pdf->Output($filename, 'I');
        exit;
    }


    // ====================== GASTOS DE CUENTA AJENA ======================
    public function gestionarGastos()
    {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        $cardcode = $_SESSION['user']['cardcode'];
        $factura_id = $_GET['factura_id'] ?? 0;

        if (!$factura_id) {
            header('Location: index.php?controller=proveedor&action=misFacturas');
            exit;
        }

        $facturaModel = new FacturaModel();
        $factura = $facturaModel->getFacturaById($factura_id, $cardcode);

        if (!$factura) {
            die("Factura no encontrada");
        }

        $gastos = $facturaModel->getGastosByFactura($factura_id, $cardcode);
        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
                $gasto_id = $_POST['gasto_id'] ?? 0;
                if ($facturaModel->eliminarGastoCuentaAjena($gasto_id, $factura_id, $cardcode)) {
                    $success = "Gasto eliminado correctamente";
                } else {
                    $error = "Error al eliminar el gasto";
                }
            } else {
                $resultado = $facturaModel->agregarGastoCuentaAjena($_POST, $_FILES, $factura_id, $cardcode);
                if ($resultado['success']) {
                    $success = $resultado['message'];
                    // Recargar gastos
                    $gastos = $facturaModel->getGastosByFactura($factura_id, $cardcode);
                } else {
                    $error = $resultado['message'];
                }
            }
        }

        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/gastos-cuenta-ajena.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }

    // Descargar comprobante de gasto
    public function descargarComprobanteGasto()
    {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        $id = $_GET['id'] ?? 0;
        $factura_id = $_GET['factura_id'] ?? 0;
        $cardcode = $_SESSION['user']['cardcode'];

        $facturaModel = new FacturaModel();
        $gastos = $facturaModel->getGastosByFactura($factura_id, $cardcode);

        $gasto = null;
        foreach ($gastos as $g) {
            if ($g['id'] == $id) {
                $gasto = $g;
                break;
            }
        }

        if (!$gasto || empty($gasto['pdf_comprobante'])) {
            die("Comprobante no disponible");
        }

        $rutaCompleta = BASE_PATH . $gasto['pdf_comprobante'];

        if (!file_exists($rutaCompleta)) {
            die("El archivo no existe en el servidor");
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="comprobante_gasto_' . $id . '.pdf"');
        header('Content-Length: ' . filesize($rutaCompleta));
        readfile($rutaCompleta);
        exit;
    }

    // ====================== CONTACTO Y SOPORTE ======================
    public function contacto()
    {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/contacto.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }

    public function enviarContacto()
    {
        if (!isset($_SESSION['user'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
            exit;
        }

        $asunto = trim($_POST['asunto'] ?? '');
        $mensaje = trim($_POST['mensaje'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $cardcode = $_SESSION['user']['cardcode'];
        $nombre = $_SESSION['user']['nombre'] ?? 'Proveedor';
        $email = $_SESSION['user']['email'] ?? '';

        if (empty($asunto) || empty($mensaje)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Asunto y mensaje son obligatorios']);
            exit;
        }

        // Aquí puedes guardar en una tabla de contactos o enviar correo
        // Ejemplo: Guardar en base de datos

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO contactos_proveedores 
                (cardcode, nombre, email, telefono, asunto, mensaje, fecha)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$cardcode, $nombre, $email, $telefono, $asunto, $mensaje]);

            // Opcional: Enviar correo electrónico
            $to = "soporte.proveedores@agrocentro.com";
            $subject = "Contacto Proveedor: $asunto";
            $body = "Proveedor: $nombre ($cardcode)\nEmail: $email\nTeléfono: $telefono\n\nMensaje:\n$mensaje";
            // mail($to, $subject, $body, "From: $email");

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Mensaje enviado correctamente. Te responderemos a la brevedad.']);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error al enviar el mensaje: ' . $e->getMessage()]);
        }
        exit;
    }

    public function buscarFacturaAdicional()
    {
        if (!isset($_SESSION['user'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
            exit;
        }

        $nit = trim($_POST['nit'] ?? '');
        $numero_factura = trim($_POST['numero_factura'] ?? '');

        if (empty($nit) || empty($numero_factura)) {
            echo json_encode(['success' => false, 'message' => 'NIT y número de factura son requeridos']);
            exit;
        }

        $facturaModel = new FacturaModel();
        $resultado = $facturaModel->buscarFacturaSAT($nit, $numero_factura);

        header('Content-Type: application/json');
        echo json_encode($resultado);
        exit;
    }

    public function getFacturasDisponiblesAdicionales()
    {
        if (!isset($_SESSION['user'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
            exit;
        }

        try {
            $dbCajas = DatabaseCajas::getInstance()->getPdo();

            // Obtener todos los DTEs no usados (de todos los NITs, excepto el del proveedor actual)
            $cardcode = $_SESSION['user']['cardcode'];

            // Obtener el NIT del proveedor actual para excluirlo
            $pdo = DatabasePortal::getInstance()->getPdo();
            $stmt = $pdo->prepare("SELECT nit FROM proveedores WHERE cardcode = ?");
            $stmt->execute([$cardcode]);
            $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
            $nitActual = $proveedor['nit'] ?? '';

            $sql = "SELECT 
                    serie, 
                    numero_dte, 
                    fecha_emision, 
                    gran_total, 
                    iva, 
                    nombre_emisor,
                    usado,
                    nit_emisor
                FROM dte 
                WHERE (usado IS NULL OR usado = 'X' OR usado = '')
                AND nit_emisor != ?
                ORDER BY nit_emisor, fecha_emision DESC";

            $stmt = $dbCajas->prepare($sql);
            $stmt->execute([$nitActual]);
            $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Agrupar por NIT
            $agrupadas = [];
            foreach ($facturas as $factura) {
                $nit = $factura['nit_emisor'];
                if (!isset($agrupadas[$nit])) {
                    $agrupadas[$nit] = [];
                }
                $agrupadas[$nit][] = $factura;
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $agrupadas]);
        } catch (Exception $e) {
            error_log("Error al obtener facturas disponibles: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function buscarDTEsPorNit()
    {
        if (!isset($_SESSION['user'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
            exit;
        }

        $nit = trim($_GET['nit'] ?? '');
        $fecha_inicio = trim($_GET['fecha_inicio'] ?? '');
        $fecha_fin = trim($_GET['fecha_fin'] ?? '');

        if (empty($nit)) {
            echo json_encode(['error' => 'NIT es requerido']);
            exit;
        }

        try {
            $dbCajas = DatabaseCajas::getInstance()->getPdo();

            // Calcular fechas por defecto (últimos 3 meses)
            if (empty($fecha_inicio) || empty($fecha_fin)) {
                $hoy = new DateTime();
                $fecha_fin = $hoy->format('Y-m-d');
                $fecha_inicio = $hoy->modify('-3 months')->format('Y-m-d');
            }

            // Determinar si es búsqueda parcial (menos de 8 caracteres) o exacta
            $esParcial = strlen($nit) < 8;

            if ($esParcial) {
                // Búsqueda parcial: mostrar facturas de TODOS los NITs que comiencen con esos dígitos
                $sql = "SELECT 
                        serie, 
                        numero_dte, 
                        fecha_emision, 
                        gran_total as monto, 
                        iva, 
                        nombre_emisor,
                        usado,
                        nit_emisor
                    FROM dte 
                    WHERE nit_emisor LIKE ?
                      AND DATE(fecha_emision) BETWEEN ? AND ?
                      AND (usado IS NULL OR usado = 'X' OR usado = '')
                    ORDER BY nit_emisor, fecha_emision DESC";

                $stmt = $dbCajas->prepare($sql);
                $stmt->execute([$nit . '%', $fecha_inicio, $fecha_fin]);
            } else {
                // Búsqueda exacta: solo facturas del NIT completo
                $sql = "SELECT 
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
                      AND DATE(fecha_emision) BETWEEN ? AND ?
                      AND (usado IS NULL OR usado = 'X' OR usado = '')
                    ORDER BY fecha_emision DESC";

                $stmt = $dbCajas->prepare($sql);
                $stmt->execute([$nit, $fecha_inicio, $fecha_fin]);
            }

            $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode($facturas);
        } catch (Exception $e) {
            error_log("Error al buscar DTEs por NIT: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Error al consultar el SAT: ' . $e->getMessage()]);
        }
        exit;
    }

    public function timelineFactura()
    {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        $factura_id = $_GET['id'] ?? 0;
        $cardcode = $_SESSION['user']['cardcode'];

        if (!$factura_id) {
            header('Location: index.php?controller=proveedor&action=misFacturas');
            exit;
        }

        $facturaModel = new FacturaModel();
        $factura = $facturaModel->getFacturaById($factura_id, $cardcode);

        if (!$factura) {
            die("Factura no encontrada o no pertenece a este proveedor");
        }

        // Construir línea de tiempo con los datos reales
        $timeline = $this->construirTimeline($factura);

        // Obtener documentos adicionales
        $stmt = $this->pdo->prepare("
        SELECT * FROM documentos_admin 
        WHERE factura_id = ? 
        ORDER BY fecha_subida DESC
    ");
        $stmt->execute([$factura_id]);
        $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener comentarios/historial
        $comentarios = $this->obtenerHistorialComentarios($factura_id);

        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/timeline-factura.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }

    private function construirTimeline($factura) {
    $timeline = [];
    
    // Estado 1: Factura Reportada
    $timeline[] = [
        'estado' => 'reportada',
        'titulo' => '📄 Factura Reportada',
        'descripcion' => 'La factura fue reportada exitosamente en el portal.',
        'fecha' => $factura['fecha_emision'],
        'completado' => true,
        'icono' => 'fa-file-invoice',
        'color' => 'success'
    ];
    
    // Estado 2: Contraseña Generada
    $timeline[] = [
        'estado' => 'contrasena_generada',
        'titulo' => '🔑 Contraseña Generada',
        'descripcion' => 'Se generó la contraseña de pago para esta factura.',
        'fecha' => $factura['fecha_inicio_credito'],
        'completado' => !empty($factura['contrasena_pago']),
        'icono' => 'fa-key',
        'color' => 'info',
        'detalle' => $factura['contrasena_pago'] ?? null
    ];
    
    // Estado 3: Revisión Compras (ahora va primero)
    $estadoCompras = in_array($factura['estado'], ['revision_compras', 'aprobada_compras', 'en_sap', 'pagada']) ||
                     !empty($factura['aprobado_por_compras']) || !empty($factura['rechazado_por']);
    
    $timeline[] = [
        'estado' => 'compras',
        'titulo' => '🛒 Revisión por Compras',
        'descripcion' => 'El área de Compras valida la orden de compra y documentos.',
        'fecha' => $factura['fecha_aprobacion_compras'] ?? $factura['fecha_rechazo'] ?? null,
        'completado' => $estadoCompras,
        'icono' => 'fa-clipboard-list',
        'color' => $factura['estado'] === 'rechazada_compras' ? 'danger' : 'primary',
        'detalle' => $this->getDetalleAprobacion($factura, 'compras')
    ];
    
    // Estado 4: Contabilidad / Envío a SAP (NUEVO ORDEN - va después de Compras)
    $estadoContabilidad = in_array($factura['estado'], ['en_sap', 'pagada']) || $factura['enviado_sap'] == 1;
    
    $timeline[] = [
        'estado' => 'contabilidad_sap',
        'titulo' => '📤 Registro en SAP (Contabilidad)',
        'descripcion' => 'El área de Contabilidad registra la factura en el sistema SAP.',
        'fecha' => $factura['fecha_envio_sap'] ?? null,
        'completado' => $estadoContabilidad,
        'icono' => 'fa-chart-line',
        'color' => 'info',
        'detalle' => $factura['comprobante_sap'] ? "Comprobante SAP: {$factura['comprobante_sap']}" : null
    ];
    
    // Estado 5: Aprobación Finanzas (ahora va después de SAP)
    $estadoFinanzas = in_array($factura['estado'], ['pagada']) ||
                      !empty($factura['aprobado_por_finanzas']);
    
    $timeline[] = [
        'estado' => 'finanzas',
        'titulo' => '💰 Aprobación Finanzas',
        'descripcion' => 'Finanzas autoriza el pago según políticas de crédito.',
        'fecha' => $factura['fecha_aprobacion_finanzas'] ?? null,
        'completado' => $estadoFinanzas,
        'icono' => 'fa-calculator',
        'color' => 'warning',
        'detalle' => $this->getDetalleAprobacion($factura, 'finanzas')
    ];
    
    // Estado 6: Pago Realizado
    $estadoPagado = $factura['estado'] === 'pagada' || $factura['pagado'] == 1;
    
    $timeline[] = [
        'estado' => 'pagado',
        'titulo' => '✅ Pago Realizado',
        'descripcion' => 'El pago ha sido procesado y registrado.',
        'fecha' => $factura['fecha_pago_real'] ?? ($factura['fecha_pago_esperada'] ?? null),
        'completado' => $estadoPagado,
        'icono' => 'fa-check-circle',
        'color' => 'success',
        'detalle' => $factura['numero_comprobante_pago'] ? "Comprobante: {$factura['numero_comprobante_pago']}" : null
    ];
    
    // Si fue rechazada en algún punto
    if ($factura['contrasena_cancelada'] == 1 || $factura['estado'] === 'rechazada_compras' || $factura['estado'] === 'rechazada_contabilidad') {
        $timeline[] = [
            'estado' => 'rechazada',
            'titulo' => '❌ Factura Rechazada',
            'descripcion' => $factura['motivo_cancelacion'] ?? $factura['motivo_rechazo'] ?? 'La factura fue rechazada por el área correspondiente.',
            'fecha' => $factura['fecha_cancelacion'] ?? $factura['fecha_rechazo'] ?? null,
            'completado' => true,
            'es_rechazo' => true,
            'icono' => 'fa-times-circle',
            'color' => 'danger'
        ];
    }
    
    return $timeline;
}

    private function getDetalleAprobacion($factura, $area) {
    if ($area === 'compras') {
        if (!empty($factura['aprobado_por_compras'])) {
            return "Aprobado por: {$factura['aprobado_por_compras']}<br>" .
                   "Fecha: " . date('d/m/Y H:i', strtotime($factura['fecha_aprobacion_compras']));
        } elseif (!empty($factura['rechazado_por'])) {
            return "Rechazado por: {$factura['rechazado_por']}<br>" .
                   "Motivo: " . htmlspecialchars($factura['motivo_rechazo'] ?? 'No especificado');
        }
    } elseif ($area === 'finanzas') {
        if (!empty($factura['aprobado_por_finanzas'])) {
            $fechaPago = !empty($factura['fecha_pago_propuesta']) ? 
                         "<br>Fecha pago propuesta: " . date('d/m/Y', strtotime($factura['fecha_pago_propuesta'])) : '';
            return "Aprobado por: {$factura['aprobado_por_finanzas']}<br>" .
                   "Fecha: " . date('d/m/Y H:i', strtotime($factura['fecha_aprobacion_finanzas'])) .
                   $fechaPago;
        }
    }
    return null;
}

    private function obtenerHistorialComentarios($factura_id)
    {
        $comentarios = [];

        // Buscar en los diferentes campos de comentarios
        $stmt = $this->pdo->prepare("
        SELECT 
            comentarios_compras as compras,
            comentarios_finanzas as finanzas,
            observaciones_contabilidad as contabilidad
        FROM facturas 
        WHERE id = ?
    ");
        $stmt->execute([$factura_id]);
        $comentariosDB = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($comentariosDB) {
            if (!empty($comentariosDB['compras'])) {
                $comentarios['Compras'] = nl2br(htmlspecialchars($comentariosDB['compras']));
            }
            if (!empty($comentariosDB['finanzas'])) {
                $comentarios['Finanzas'] = nl2br(htmlspecialchars($comentariosDB['finanzas']));
            }
            if (!empty($comentariosDB['contabilidad'])) {
                $comentarios['Contabilidad'] = nl2br(htmlspecialchars($comentariosDB['contabilidad']));
            }
        }

        return $comentarios;
    }

    // Añadir este método para obtener viajes vía AJAX
public function getViajesPendientesTransporte() {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
        exit;
    }
    
    $cardcode = $_SESSION['user']['cardcode'];
    
    $transporteModel = new TransporteAPIModel();
    $resultado = $transporteModel->getViajesPendientes($cardcode);
    
    header('Content-Type: application/json');
    echo json_encode($resultado);
    exit;
}
}
