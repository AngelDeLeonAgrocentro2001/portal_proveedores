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
        $resultado = $facturaModel->reportarFactura($_POST, $_FILES, $cardcode);

        if ($resultado['success']) {
            $_SESSION['last_report'] = [
                'success'      => true,
                'contrasena'   => $resultado['contrasena'],
                'esLunes'      => $resultado['esLunes'],
                'proximoLunes' => $resultado['proximoLunes'],
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
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(60, 8, 'Orden de Compra', 1, 0, 'C');
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
    public function gestionarGastos() {
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
    public function descargarComprobanteGasto() {
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
    public function contacto() {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }
        
        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/contacto.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }
    
    public function enviarContacto() {
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

    public function buscarFacturaAdicional() {
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

public function getFacturasDisponiblesAdicionales() {
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

public function buscarDTEsPorNit() {
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
    
}
