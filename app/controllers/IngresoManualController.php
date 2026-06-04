<?php
// app/controllers/IngresoManualController.php
require_once BASE_PATH . 'app/models/FacturaModel.php';
require_once BASE_PATH . 'app/models/ProveedorModel.php';

class IngresoManualController {
    private $pdo;
    
    public function __construct() {
        $this->pdo = DatabasePortal::getInstance()->getPdo();
    }
    
    /**
     * Pantalla principal para ingresar CardCode del proveedor
     */
    public function index() {
        $error = '';
        $proveedor = null;
        $facturasSAT = [];
        $ordenesAbiertas = [];
        $cardcode_busqueda = '';
        
        // Procesar búsqueda de proveedor
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buscar_proveedor'])) {
            $cardcode_busqueda = trim($_POST['cardcode'] ?? '');
            
            if (empty($cardcode_busqueda)) {
                $error = "Debe ingresar un código de proveedor";
            } else {
                $proveedorModel = new ProveedorModel();
                $proveedor = $proveedorModel->getProveedorByCardcode($cardcode_busqueda);
                
                if (!$proveedor) {
                    $error = "Proveedor no encontrado con código: " . htmlspecialchars($cardcode_busqueda);
                } else {
                    // Cargar facturas SAT disponibles del proveedor
                    $facturasSAT = $this->getFacturasSATDisponibles($proveedor['nit']);
                    
                    // Cargar órdenes de compra abiertas
                    $ordenesAbiertas = $proveedorModel->getOrdenesCompraByCardcode($cardcode_busqueda, 'abierta');
                }
            }
        }
        
        // Procesar reporte de factura manual
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reportar_factura'])) {
            $cardcode = trim($_POST['cardcode'] ?? '');
            $numero_factura = trim($_POST['numero_factura'] ?? '');
            $serie = trim($_POST['serie'] ?? '');
            $numero_dte = trim($_POST['numero_dte'] ?? '');
            $fecha_emision = $_POST['fecha_emision'] ?? date('Y-m-d');
            $monto = floatval($_POST['monto'] ?? 0);
            $ordenes_seleccionadas = $_POST['ordenes'] ?? [];
            
            error_log("=== REPORTE MANUAL ===");
            error_log("CardCode: $cardcode");
            error_log("Factura: $numero_factura");
            error_log("Serie: $serie, Número DTE: $numero_dte");
            error_log("Monto: $monto");
            error_log("Órdenes: " . print_r($ordenes_seleccionadas, true));
            
            if (empty($cardcode) || empty($numero_factura) || $monto <= 0) {
                $error = "Faltan datos obligatorios";
                error_log("ERROR: Datos incompletos");
            } elseif (empty($ordenes_seleccionadas)) {
                $error = "Debe seleccionar al menos una Orden de Compra";
                error_log("ERROR: Sin órdenes seleccionadas");
            } else {
                // Obtener el proveedor nuevamente para tener sus datos actualizados
                $proveedorModel = new ProveedorModel();
                $proveedorData = $proveedorModel->getProveedorByCardcode($cardcode);
                
                if (!$proveedorData) {
                    $error = "Proveedor no encontrado";
                    error_log("ERROR: Proveedor no encontrado con CardCode: $cardcode");
                } else {
                    error_log("Proveedor encontrado: " . $proveedorData['nombre'] . " - NIT: " . $proveedorData['nit']);
                    
                    // Verificar que la factura no esté ya usada
                    try {
                        $dbCajas = DatabaseCajas::getInstance()->getPdo();
                        $stmt = $dbCajas->prepare("
                            SELECT usado FROM dte 
                            WHERE nit_emisor = ? AND serie = ? AND numero_dte = ?
                        ");
                        $stmt->execute([$proveedorData['nit'], $serie, $numero_dte]);
                        $dte = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        error_log("DTE encontrado - usado: " . ($dte['usado'] ?? 'NULL'));
                        
                        if ($dte && $dte['usado'] === 'Y') {
                            $error = "Esta factura ya ha sido reportada anteriormente";
                            error_log("ERROR: DTE ya usado");
                        } else {
                            // Crear la factura manualmente
                            $resultado = $this->crearFacturaManual(
                                $cardcode, 
                                $numero_factura, 
                                $serie, 
                                $numero_dte, 
                                $fecha_emision, 
                                $monto, 
                                $ordenes_seleccionadas,
                                $proveedorData['nit']
                            );
                            
                            error_log("Resultado de crearFacturaManual: " . ($resultado['success'] ? 'EXITO' : 'ERROR: ' . $resultado['message']));
                            
                            if ($resultado['success']) {
                                $_SESSION['ingreso_manual_success'] = [
                                    'contrasena' => $resultado['contrasena'],
                                    'numero_factura' => $numero_factura,
                                    'proveedor' => $proveedorData['nombre']
                                ];
                                header('Location: index.php?controller=ingresoManual&action=exito');
                                exit;
                            } else {
                                $error = $resultado['message'];
                            }
                        }
                    } catch (Exception $e) {
                        $error = "Error al verificar la factura: " . $e->getMessage();
                        error_log("EXCEPCIÓN: " . $e->getMessage());
                    }
                }
            }
            
            // Recargar datos para mostrar nuevamente el formulario
            if (!empty($cardcode)) {
                $proveedorModel = new ProveedorModel();
                $proveedor = $proveedorModel->getProveedorByCardcode($cardcode);
                if ($proveedor) {
                    $facturasSAT = $this->getFacturasSATDisponibles($proveedor['nit']);
                    $ordenesAbiertas = $proveedorModel->getOrdenesCompraByCardcode($cardcode, 'abierta');
                    $cardcode_busqueda = $cardcode;
                }
            }
        }
        
        require_once BASE_PATH . 'app/views/ingreso_manual/index.php';
    }
    
    /**
     * Página de éxito después de crear la contraseña
     */
    public function exito() {
        $success = $_SESSION['ingreso_manual_success'] ?? null;
        unset($_SESSION['ingreso_manual_success']);
        
        if (!$success) {
            header('Location: index.php?controller=ingresoManual&action=index');
            exit;
        }
        
        require_once BASE_PATH . 'app/views/ingreso_manual/exito.php';
    }
    
    /**
     * Obtener facturas SAT disponibles de un proveedor
     */
    private function getFacturasSATDisponibles($nit) {
        if (empty($nit)) {
            return [];
        }
        
        try {
            $dbCajas = DatabaseCajas::getInstance()->getPdo();
            
            $stmt = $dbCajas->prepare("
                SELECT 
                    serie, 
                    numero_dte, 
                    fecha_emision, 
                    gran_total as monto, 
                    iva, 
                    nombre_emisor,
                    usado
                FROM dte 
                WHERE nit_emisor = ?
                  AND (usado IS NULL OR usado = 'X' OR usado = '')
                ORDER BY fecha_emision DESC
                LIMIT 100
            ");
            $stmt->execute([$nit]);
            $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Procesar cada factura
            foreach ($facturas as &$factura) {
                $factura['numero_completo'] = trim($factura['serie'] . ' ' . $factura['numero_dte']);
            }
            
            error_log("Facturas SAT disponibles para NIT $nit: " . count($facturas));
            
            return $facturas;
        } catch (Exception $e) {
            error_log("Error al cargar facturas SAT: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Crear factura manualmente (sin usuario proveedor logueado)
     */
    private function crearFacturaManual($cardcode, $numero_factura, $serie, $numero_dte, $fecha_emision, $monto, $ordenes_seleccionadas, $nit_proveedor) {
        $this->pdo->beginTransaction();
        
        try {
            error_log("=== INICIO crearFacturaManual ===");
            error_log("CardCode: $cardcode");
            error_log("NIT proveedor: $nit_proveedor");
            error_log("Serie: $serie, Número DTE: $numero_dte");
            
            // Calcular contraseña y fechas
            $hoy = new DateTime();
            $diaSemana = (int)$hoy->format('N');
            
            if ($diaSemana === 1) {
                $fecha_inicio_credito = $hoy->format('Y-m-d');
                $contrasena = 'AGRO-' . $hoy->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            } else {
                $proximoLunes = clone $hoy;
                $diasHastaLunes = (8 - $diaSemana) % 7;
                if ($diasHastaLunes === 0) $diasHastaLunes = 7;
                $proximoLunes->modify("+{$diasHastaLunes} days");
                $fecha_inicio_credito = $proximoLunes->format('Y-m-d');
                $contrasena = 'AGRO-' . $proximoLunes->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            }
            
            error_log("Contraseña generada: $contrasena");
            
            // Calcular fecha de pago esperada (viernes +30 días)
            $fecha_pago_esperada = $this->calcularFechaPago($fecha_inicio_credito);
            
            $ordenes_json = json_encode($ordenes_seleccionadas);
            
            // Insertar factura
            $stmt = $this->pdo->prepare("
                INSERT INTO facturas 
                (cardcode, id_usuario, numero_factura, fecha_factura_sat, fecha_emision, monto, 
                 contrasena_pago, fecha_pago_esperada, fecha_inicio_credito, 
                 estado, ordenes_relacionadas, es_ingreso_manual)
                VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, 'reportada', ?, 1)
            ");
            
            $stmt->execute([
                $cardcode,
                $numero_factura,
                $fecha_emision,
                $fecha_emision,
                $monto,
                $contrasena,
                $fecha_pago_esperada,
                $fecha_inicio_credito,
                $ordenes_json
            ]);
            
            $factura_id = $this->pdo->lastInsertId();
            error_log("Factura insertada con ID: $factura_id");
            
            // Marcar DTE como usado - CONEXIÓN DIRECTA A LA BASE DE DATOS
            try {
                $dbCajas = DatabaseCajas::getInstance()->getPdo();
                
                // Primero verificar el estado actual
                $stmtCheck = $dbCajas->prepare("
                    SELECT usado FROM dte 
                    WHERE nit_emisor = ? AND serie = ? AND numero_dte = ?
                ");
                $stmtCheck->execute([$nit_proveedor, $serie, $numero_dte]);
                $estadoActual = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                error_log("Estado actual del DTE: " . print_r($estadoActual, true));
                
                // Actualizar a 'Y'
                $stmtDte = $dbCajas->prepare("
                    UPDATE dte 
                    SET usado = 'Y' 
                    WHERE nit_emisor = ? 
                      AND serie = ? 
                      AND numero_dte = ?
                ");
                $stmtDte->execute([$nit_proveedor, $serie, $numero_dte]);
                $filasAfectadas = $stmtDte->rowCount();
                error_log("Filas afectadas en UPDATE dte: $filasAfectadas");
                
                if ($filasAfectadas == 0) {
                    // Intentar actualizar también con espacio en blanco
                    $stmtDte2 = $dbCajas->prepare("
                        UPDATE dte 
                        SET usado = 'Y' 
                        WHERE nit_emisor = ? 
                          AND serie = ? 
                          AND CAST(numero_dte AS CHAR) = ?
                    ");
                    $stmtDte2->execute([$nit_proveedor, $serie, $numero_dte]);
                    error_log("Segundo intento - Filas afectadas: " . $stmtDte2->rowCount());
                }
                
            } catch (Exception $e) {
                error_log("Error al marcar DTE como usado: " . $e->getMessage());
                // No lanzamos excepción para no revertir la transacción
            }
            
            $this->pdo->commit();
            error_log("=== TRANSACCIÓN COMPLETADA EXITOSAMENTE ===");
            
            return [
                'success' => true,
                'contrasena' => $contrasena,
                'factura_id' => $factura_id
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("ERROR en crearFacturaManual: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Error al crear la factura: ' . $e->getMessage()
            ];
        }
    }
    
    private function calcularFechaPago($fecha_base) {
        $fecha = new DateTime($fecha_base);
        $fecha->modify("+30 days");
        
        $diaSemana = (int)$fecha->format('N');
        if ($diaSemana !== 5) {
            $diasHastaViernes = (5 - $diaSemana + 7) % 7;
            if ($diasHastaViernes === 0) $diasHastaViernes = 7;
            $fecha->modify("+{$diasHastaViernes} days");
        }
        
        return $fecha->format('Y-m-d');
    }
    
    /**
     * Buscar proveedor por CardCode (AJAX)
     */
    public function buscarProveedor() {
        $cardcode = $_GET['cardcode'] ?? '';
        
        if (empty($cardcode)) {
            echo json_encode(['success' => false, 'message' => 'CardCode requerido']);
            exit;
        }
        
        $proveedorModel = new ProveedorModel();
        $proveedor = $proveedorModel->getProveedorByCardcode($cardcode);
        
        if (!$proveedor) {
            echo json_encode(['success' => false, 'message' => 'Proveedor no encontrado']);
            exit;
        }
        
        // Obtener facturas SAT disponibles
        $facturasSAT = $this->getFacturasSATDisponibles($proveedor['nit']);
        
        // Obtener órdenes de compra abiertas
        $ordenesAbiertas = $proveedorModel->getOrdenesCompraByCardcode($cardcode, 'abierta');
        
        echo json_encode([
            'success' => true,
            'proveedor' => [
                'cardcode' => $proveedor['cardcode'],
                'nombre' => $proveedor['nombre'],
                'nit' => $proveedor['nit']
            ],
            'facturas' => $facturasSAT,
            'ordenes' => $ordenesAbiertas
        ]);
        exit;
    }
}
?>