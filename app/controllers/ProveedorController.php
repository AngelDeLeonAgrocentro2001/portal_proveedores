<?php
// app/controllers/ProveedorController.php
require_once BASE_PATH . 'app/models/ProveedorModel.php';
require_once BASE_PATH . 'app/models/FacturaModel.php';
require_once BASE_PATH . 'app/models/UsuarioModel.php';
class ProveedorController {

            public function dashboard() {
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
    }

        $cardcode = $_SESSION['user']['cardcode'];
        $rol      = $_SESSION['user']['rol'] ?? 'crear_contrasenas';

        $proveedorModel = new ProveedorModel();
        $facturaModel   = new FacturaModel();

        $proveedor = $proveedorModel->getProveedorByCardcode($cardcode);
        $resumen   = $proveedorModel->getResumenFacturas($cardcode);
        $facturas  = $proveedorModel->getUltimasFacturas($cardcode, 5);
        $pagos     = $facturaModel->getUltimosPagos($cardcode, 5);

        // Control según rol
        $mostrarPagos = in_array($rol, ['admin', 'consultas']);
        $esAdmin      = ($rol === 'admin');
        $puedeCrear   = true; // todos pueden crear contraseñas

        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/dashboard.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }

    // ====================== NUEVO: REPORTAR FACTURA ======================
                                public function reportarFactura() {
    if (!isset($_SESSION['user'])) {
        header('Location: index.php?controller=auth&action=login');
        exit;
    }

    $cardcode = $_SESSION['user']['cardcode'];
    $rol      = $_SESSION['user']['rol'] ?? 'crear_contrasenas';
    $error    = '';
    $success  = '';
    
    $preseleccion = trim($_GET['preseleccion'] ?? '');

    $proveedorModel = new ProveedorModel();
    $proveedor = $proveedorModel->getProveedorByCardcode($cardcode);
    $ordenesAbiertas = $proveedorModel->getOrdenesCompraByCardcode($cardcode, 'abierta');

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
        $resultado = $facturaModel->reportarFactura($_POST, $_FILES, $cardcode);

        if ($resultado['success']) {
            // Guardamos SOLO lo necesario para el modal
            $_SESSION['last_report'] = [
                'success'      => true,
                'contrasena'   => $resultado['contrasena'],
                'esLunes'      => $resultado['esLunes'],
                'proximoLunes' => $resultado['proximoLunes']
            ];
            
            $success = "Factura reportada correctamente con contraseña: " . $resultado['contrasena'];
            
            // Recargar lista de facturas SAT sin las usadas
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

    require_once BASE_PATH . 'app/views/layout/header.php';
    require_once BASE_PATH . 'app/views/proveedor/reportar-factura.php';
    require_once BASE_PATH . 'app/views/layout/footer.php';
}

    public function misFacturas() {
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
    public function descargar() {
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
            case 'factura':    $campo = 'pdf_factura'; break;
            case 'orden':      $campo = 'pdf_orden_compra'; break;
            case 'constancia': $campo = 'pdf_constancia'; break;
        }

        $rutaRelativa = $factura[$campo];
        if (empty($rutaRelativa)) {
            die("Archivo no disponible");
        }

        $rutaCompleta = BASE_PATH . 'public/' . $rutaRelativa;

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

        // Ver todos los pagos recibidos
    public function pagos() {
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

            public function ordenesCompra() {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        $cardcode = $_SESSION['user']['cardcode'];
        $model = new ProveedorModel();

        $estadoFiltro = $_GET['estado'] ?? 'abierta';

        $ordenes = $model->getOrdenesCompraByCardcode($cardcode, $estadoFiltro);

        $totalMonto = array_sum(array_column($ordenes, 'monto'));

        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/ordenes-compra.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }

      

    public function facturasSAT() {
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

        if (empty($nit)) {
            $errorSAT = "No se encontró NIT registrado para este proveedor.";
        } else {
            try {
                $dbCajas = DatabaseCajas::getInstance()->getPdo();
                
                $sql = "SELECT 
                            serie, 
                            numero_dte, 
                            fecha_emision, 
                            gran_total, 
                            iva, 
                            nombre_emisor,
                            usado
                        FROM dte 
                        WHERE nit_emisor = :nit
                        ORDER BY fecha_emision DESC";
                        
                $stmt = $dbCajas->prepare($sql);
                $stmt->execute(['nit' => $nit]);
                $facturasSAT = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } catch (Exception $e) {
                $errorSAT = "Error al consultar facturas del SAT: " . $e->getMessage();
                error_log("❌ " . $errorSAT);
            }
        }

        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/facturas-sat.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }

        // ====================== GESTIÓN DE USUARIOS (SOLO ADMIN) ======================
    public function gestionarUsuarios() {
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
                $resultado = $usuarioModel->crearUsuario($cardcode, $email, $username, $password, $rol);

                if ($resultado) {
                    $success = "Usuario creado correctamente con rol: " . ucfirst(str_replace('_', ' ', $rol));
                } else {
                    $error = "Error al crear el usuario. Puede que el username o email ya exista.";
                }
            }
        }

        // Obtener todos los usuarios del mismo cardcode
        $usuarios = $usuarioModel->getUsuariosByCardcode($cardcode);

        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/gestionar-usuarios.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }

        // ====================== DESCARGAR PDF DE CONTRASEÑA ======================
    public function pdfContraseña() {
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

        // Logo y encabezado (igual que tu ejemplo)
        $pdf->Image(BASE_PATH . 'public/assets/img/logo-agrocentro.png', 15, 15, 45); // Cambia la ruta si tu logo está en otro lugar

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
        $pdf->Cell(0, 7, $proveedor['nombre'] ?? 'N/A', 0, 1);

        $pdf->Cell(40, 7, 'TIPO DE DOCUMENTO:', 0);
        $pdf->Cell(0, 7, 'FACTURA', 0, 1);

        $pdf->Ln(8);

        // Tabla
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(60, 8, 'Orden de Compra', 1, 0, 'C');
        $pdf->Cell(60, 8, 'Documento', 1, 0, 'C');
        $pdf->Cell(35, 8, 'Fecha', 1, 0, 'C');
        $pdf->Cell(35, 8, 'Valor', 1, 1, 'C');

        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(60, 8, $factura['ordenes_relacionadas'] ?? '-', 1, 0, 'C');
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
        $pdf->Output($filename, 'I');   // 'I' = mostrar en navegador (descarga)
        exit;
    }

      // ====================== PDF ORDEN DE COMPRA CON DETALLE COMPLETO (SIN WARNINGS) ======================
public function pdfOrdenCompra() {
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
        $conexion = $sap->CONEXION_HANA('GT_AGROCENTRO_2016');

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
                T1.\"LineTotal\" as \"MontoLinea\"
            FROM \"GT_AGROCENTRO_2016\".OPOR T0
                INNER JOIN \"GT_AGROCENTRO_2016\".POR1 T1 ON T0.\"DocEntry\" = T1.\"DocEntry\" 
                INNER JOIN \"GT_AGROCENTRO_2016\".OACT T2 ON T1.\"AcctCode\" = T2.\"AcctCode\"
            WHERE T0.\"CardCode\" = ? 
                AND T0.\"DocEntry\" = ?
                AND T0.\"DocStatus\" = 'O'
            ORDER BY T1.\"LineNum\"
        ";

        $stmt = odbc_prepare($conexion, $query);
        if (!$stmt) {
            throw new Exception("Error preparando consulta");
        }
        
        if (!odbc_execute($stmt, [$cardcode, $docentry])) {
            throw new Exception("Error ejecutando consulta");
        }

        $lineasDetalle = [];
        $ordenData = null;
        
        while ($row = odbc_fetch_object($stmt)) {
            // Limpiar y sanitizar datos
            $centroCosto = trim($row->CentroCosto ?? '');
            $codigoCuenta = trim($row->CodigoCuenta ?? '');
            $nombreCuenta = trim($row->NombreCuenta ?? '');
            $descripcion = trim($row->DescripcionLinea ?? '');
            $montoLinea = (float)($row->MontoLinea ?? 0);
            
            // Solo agregar líneas con datos válidos
            if (!empty($descripcion) || $montoLinea > 0) {
                $lineasDetalle[] = [
                    'centro_costo'  => $centroCosto ?: '-',
                    'codigo_cuenta' => $codigoCuenta ?: '-',
                    'nombre_cuenta' => $nombreCuenta ?: '-',
                    'descripcion'   => $descripcion ?: 'Sin descripción',
                    'monto_linea'   => $montoLinea
                ];
            }
            
            // Tomar datos de la cabecera (del primer registro)
            if ($ordenData === null) {
                $ordenData = [
                    'numero_oc'     => trim($row->NoDocumento ?? ''),
                    'fecha'         => $row->FechaDocumento ?? date('Y-m-d'),
                    'total'         => (float)($row->TotalDocumento ?? 0),
                    'observaciones' => trim($row->Observaciones ?? ''),
                    'nombre_proveedor' => trim($row->NombreProveedor ?? '')
                ];
            }
        }
        
        odbc_free_result($stmt);
        odbc_close($conexion);
        
        // Validar que se encontraron datos
        if ($ordenData === null || empty($ordenData['numero_oc'])) {
            throw new Exception("No se encontró la orden de compra");
        }
        
        if (empty($lineasDetalle)) {
            // Si no hay líneas de detalle, crear una línea por defecto
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

    // Crear PDF con manejo de errores silencioso
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(12, 12, 12);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();
    
    // Usar fuente estándar para evitar problemas
    $pdf->SetFont('helvetica', '', 10);

    // Logo y encabezado (con verificación de archivo)
    $logoPath = BASE_PATH . 'public/assets/img/logo-agrocentro.png';
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, 12, 12, 45);
    }

    $pdf->SetY(15);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'ORDEN DE COMPRA', 0, 1, 'R');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'No. ' . htmlspecialchars($ordenData['numero_oc']), 0, 1, 'R');
    
    // Validar fecha
    $fechaValida = !empty($ordenData['fecha']) ? date('d/m/Y', strtotime($ordenData['fecha'])) : 'Fecha no disponible';
    $pdf->Cell(0, 5, 'Fecha: ' . $fechaValida, 0, 1, 'R');

    $pdf->Ln(8);

    // Datos del proveedor
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(35, 7, 'PROVEEDOR:', 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 7, htmlspecialchars($ordenData['nombre_proveedor']), 0, 1);

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(35, 7, 'CÓDIGO:', 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 7, htmlspecialchars($cardcode), 0, 1);

    $pdf->Ln(5);

    // ========== TABLA DE DETALLE ==========
    // Método más simple para evitar problemas con MultiCell
    $pdf->SetFont('helvetica', 'B', 8);
    
    $anchoCentro = 25;
    $anchoCuenta = 25;
    $anchoNombre = 50;
    $anchoDescrip = 50;
    $anchoMonto = 30;
    
    // Encabezados
    $pdf->Cell($anchoCentro, 7, 'CENTRO COSTO', 1, 0, 'C');
    $pdf->Cell($anchoCuenta, 7, 'CUENTA', 1, 0, 'C');
    $pdf->Cell($anchoNombre, 7, 'NOMBRE CUENTA', 1, 0, 'C');
    $pdf->Cell($anchoDescrip, 7, 'DESCRIPCIÓN', 1, 0, 'C');
    $pdf->Cell($anchoMonto, 7, 'TOTAL', 1, 1, 'C');
    
    // Datos - usando método simple sin MultiCell para evitar warnings
    $pdf->SetFont('helvetica', '', 8);
    foreach ($lineasDetalle as $linea) {
        // Truncar texto largo para evitar problemas
        $descripcion = htmlspecialchars(substr($linea['descripcion'], 0, 60));
        $nombreCuenta = htmlspecialchars(substr($linea['nombre_cuenta'], 0, 45));
        
        $pdf->Cell($anchoCentro, 6, htmlspecialchars($linea['centro_costo']), 1, 0, 'L');
        $pdf->Cell($anchoCuenta, 6, htmlspecialchars($linea['codigo_cuenta']), 1, 0, 'L');
        $pdf->Cell($anchoNombre, 6, $nombreCuenta, 1, 0, 'L');
        $pdf->Cell($anchoDescrip, 6, $descripcion, 1, 0, 'L');
        $pdf->Cell($anchoMonto, 6, 'Q ' . number_format($linea['monto_linea'], 2), 1, 1, 'R');
    }
    
    // TOTAL
    $pdf->SetFont('helvetica', 'B', 9);
    $totalAncho = $anchoCentro + $anchoCuenta + $anchoNombre + $anchoDescrip;
    $pdf->Cell($totalAncho, 7, 'TOTAL DOCUMENTO', 1, 0, 'R');
    $pdf->Cell($anchoMonto, 7, 'Q ' . number_format($ordenData['total'], 2), 1, 1, 'R');
    
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
    
    // Limpiar cualquier buffer de salida antes de enviar el PDF
    if (ob_get_length()) {
        ob_clean();
    }
    
    // Enviar PDF
    $filename = 'Orden_Compra_' . $ordenData['numero_oc'] . '.pdf';
    $pdf->Output($filename, 'I');
    exit;
}
    
}
