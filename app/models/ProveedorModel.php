<?php
// app/models/ProveedorModel.php
require_once BASE_PATH . 'database/DatabasePortal.php';


class ProveedorModel {
    private $pdo;

    public function __construct() {
        $this->pdo = DatabasePortal::getInstance()->getPdo();
    }

    public function getProveedorByCardcode($cardcode) {
        $stmt = $this->pdo->prepare("SELECT * FROM proveedores WHERE cardcode = ?");
        $stmt->execute([$cardcode]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Días de crédito reales desde SAP (OCRD.GroupNum -> OCTG.ExtraDays), igual que
    // SuperAdminModel::getDiasCreditoSAP() — se usa para mostrarlos en el dashboard del
    // proveedor en vez del valor fijo guardado en proveedores.dias_credito (que puede quedar
    // desactualizado si el Grupo de Proveedores cambia en SAP). Devuelve null si SAP no
    // responde o el CardCode no existe ahí; el llamador debe usar el valor local como respaldo.
    public function getDiasCreditoSAP($cardcode) {
        if (empty($cardcode)) {
            return null;
        }

        try {
            $sap = new DatabaseSAP();
            $conexion = $sap->CONEXION_HANA('T_GT_AGROCENTRO_2016');

            $query = "
                SELECT T1.\"ExtraDays\" AS \"extradays\"
                FROM \"T_GT_AGROCENTRO_2016\".OCRD T0
                INNER JOIN \"T_GT_AGROCENTRO_2016\".OCTG T1 ON T0.\"GroupNum\" = T1.\"GroupNum\"
                WHERE T0.\"CardCode\" = ?
            ";

            $stmt = odbc_prepare($conexion, $query);
            if (!$stmt || !odbc_execute($stmt, [$cardcode])) {
                throw new Exception("Error ejecutando consulta: " . odbc_errormsg($conexion));
            }

            $row = odbc_fetch_object($stmt);
            odbc_free_result($stmt);
            odbc_close($conexion);

            return $row ? (int)$row->extradays : null;
        } catch (Exception $e) {
            error_log("Error al consultar días de crédito en SAP: " . $e->getMessage());
            return null;
        }
    }

    public function getResumenFacturas($cardcode) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'reportada' THEN 1 ELSE 0 END) as reportadas,
                SUM(CASE WHEN estado = 'pagada' THEN 1 ELSE 0 END) as pagadas,
                COALESCE(SUM(CASE WHEN estado IN ('reportada','validada','en_sap') THEN monto ELSE 0 END), 0) as monto_pendiente
            FROM facturas 
            WHERE cardcode = ?
        ");
        $stmt->execute([$cardcode]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

        public function getUltimasFacturas($cardcode, $limit = 5) {
        $stmt = $this->pdo->prepare("
            SELECT 
                id, 
                numero_factura, 
                fecha_factura_sat, 
                fecha_emision, 
                monto, 
                estado, 
                contrasena_pago, 
                fecha_pago_esperada 
            FROM facturas 
            WHERE cardcode = ? 
            ORDER BY fecha_emision DESC 
            LIMIT " . (int)$limit
        );
        $stmt->execute([$cardcode]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // public function getUltimosPagos($cardcode, $limit = 5) {
    //     $stmt = $this->pdo->prepare("
    //         SELECT p.fecha_pago, f.numero_factura, p.monto_pagado, p.detalle
    //         FROM pagos p
    //         JOIN facturas f ON p.factura_id = f.id
    //         WHERE f.cardcode = ?
    //         ORDER BY p.fecha_pago DESC 
    //         LIMIT " . (int)$limit
    //     );
    //     $stmt->execute([$cardcode]);
    //     return $stmt->fetchAll(PDO::FETCH_ASSOC);
    // }

          // Obtener órdenes de compra directamente desde SAP HANA - Solo del año actual
    public function getOrdenesCompraByCardcode($cardcode, $estado = 'abierta') {
        try {
            $sap = new DatabaseSAP();
            $conexion = $sap->CONEXION_HANA('T_GT_AGROCENTRO_2016');

            $añoActual = date('Y');   // Toma el año actual automáticamente (2026)

            $query = "
                SELECT 
                    T0.\"DocEntry\" AS \"docentry\",
                    T0.\"DocNum\"   AS \"numero_oc\",
                    T0.\"DocDate\"  AS \"fecha\",
                    T0.\"DocTotal\" AS \"monto\",
                    COALESCE(T0.\"DocCur\", 'GTQ') AS \"moneda\",
                    CASE 
                        WHEN T0.\"DocStatus\" = 'O' THEN 'abierta' 
                        ELSE 'cerrada' 
                    END AS \"estado\"
                FROM \"T_GT_AGROCENTRO_2016\".OPOR T0 
                WHERE T0.\"CardCode\" = ?
                  AND YEAR(T0.\"DocDate\") = ?
            ";

            if ($estado === 'abierta') {
                $query .= " AND T0.\"DocStatus\" = 'O'";
            } elseif ($estado === 'cerrada') {
                $query .= " AND T0.\"DocStatus\" = 'C'";
            }

            $query .= " ORDER BY T0.\"DocDate\" DESC";

            error_log("Ejecutando consulta SAP OPOR para CardCode: " . $cardcode . " | Año: " . $añoActual);

            $stmt = odbc_prepare($conexion, $query);
            if (!$stmt || !odbc_execute($stmt, [$cardcode, $añoActual])) {
                throw new Exception("Error ejecutando consulta: " . odbc_errormsg($conexion));
            }

            $ordenes = [];
            while ($row = odbc_fetch_object($stmt)) {
                $ordenes[] = [
                    'docentry'  => $row->docentry ?? '',
                    'numero_oc' => $row->numero_oc ?? '',
                    'fecha'     => $row->fecha ?? '',
                    'monto'     => (float)($row->monto ?? 0),
                    'moneda'    => $row->moneda ?? 'GTQ',
                    'estado'    => $row->estado ?? 'abierta'
                ];
            }

            odbc_free_result($stmt);
            odbc_close($conexion);

            error_log("Órdenes encontradas desde SAP este año: " . count($ordenes));
            return $ordenes;

        } catch (Exception $e) {
            error_log("Error al consultar órdenes desde SAP: " . $e->getMessage());
            // Fallback a tabla local
            return $this->getOrdenesCompraLocal($cardcode, $estado);
        }
    }

    // Entradas de Mercancía (SAP Goods Receipt PO, OPDN/PDN1) de un proveedor, con su detalle
    // de líneas. Usado en lugar de getOrdenesCompraByCardcode() para proveedores tipo
    // 'material_empaque' en la página "Mis Órdenes de Compra" — a diferencia de las órdenes de
    // compra, este documento no tiene monto/moneda (es un recibo de mercancía, no una orden de
    // pago), por eso devuelve encabezado + detalle en vez de una fila plana con monto.
    // Devuelve un array vacío si SAP no responde (la vista simplemente muestra "sin resultados").
    public function getEntradasMercanciaByCardcode($cardcode, $estado = 'abierta') {
        try {
            $sap = new DatabaseSAP();
            $conexion = $sap->CONEXION_HANA('T_GT_AGROCENTRO_2016');

            $query = "
                SELECT
                    T0.\"DocEntry\"  AS \"docentry\",
                    T0.\"CardCode\"  AS \"cardcode\",
                    T0.\"CardName\"  AS \"cardname\",
                    T0.\"DocNum\"    AS \"docnum\",
                    T0.\"DocDate\"   AS \"docdate\",
                    CASE
                        WHEN T0.\"DocStatus\" = 'O' THEN 'abierta'
                        ELSE 'cerrada'
                    END AS \"estado\",
                    T1.\"LineNum\"   AS \"linenum\",
                    T1.\"ItemCode\"  AS \"itemcode\",
                    T1.\"Dscription\" AS \"descripcion\",
                    T1.\"Quantity\"  AS \"cantidad\"
                FROM \"T_GT_AGROCENTRO_2016\".OPDN T0
                INNER JOIN \"T_GT_AGROCENTRO_2016\".PDN1 T1 ON T0.\"DocEntry\" = T1.\"DocEntry\"
                WHERE T0.\"CardCode\" = ?
                  AND T0.\"CANCELED\" <> 'Y'
            ";

            if ($estado === 'abierta') {
                $query .= " AND T0.\"DocStatus\" = 'O'";
            } elseif ($estado === 'cerrada') {
                $query .= " AND T0.\"DocStatus\" = 'C'";
            }

            $query .= " ORDER BY T0.\"DocDate\" DESC, T0.\"DocEntry\" DESC, T1.\"LineNum\" ASC";

            error_log("Ejecutando consulta SAP OPDN (Entrada de Mercancía) para CardCode: " . $cardcode);

            $stmt = odbc_prepare($conexion, $query);
            if (!$stmt || !odbc_execute($stmt, [$cardcode])) {
                throw new Exception("Error ejecutando consulta: " . odbc_errormsg($conexion));
            }

            // Agrupar filas (una por línea) en encabezados con su detalle de líneas
            $entradas = [];
            while ($row = odbc_fetch_object($stmt)) {
                $docentry = $row->docentry ?? '';
                if (!isset($entradas[$docentry])) {
                    $entradas[$docentry] = [
                        'docentry' => $docentry,
                        'cardcode' => $row->cardcode ?? '',
                        'cardname' => $row->cardname ?? '',
                        'docnum'   => $row->docnum ?? '',
                        'docdate'  => $row->docdate ?? '',
                        'estado'   => $row->estado ?? 'abierta',
                        'lineas'   => []
                    ];
                }
                $entradas[$docentry]['lineas'][] = [
                    'linenum'     => $row->linenum ?? '',
                    'itemcode'    => $row->itemcode ?? '',
                    'descripcion' => $row->descripcion ?? '',
                    'cantidad'    => (float)($row->cantidad ?? 0)
                ];
            }

            odbc_free_result($stmt);
            odbc_close($conexion);

            $entradas = array_values($entradas);
            error_log("Entradas de Mercancía encontradas desde SAP: " . count($entradas));
            return $entradas;

        } catch (Exception $e) {
            error_log("Error al consultar entradas de mercancía desde SAP: " . $e->getMessage());
            return [];
        }
    }

    // Igual que getOrdenesCompraByCardcode() pero consultando Entradas de Mercancía (OPDN),
    // devolviendo la MISMA forma de fila (docentry, numero_oc, fecha, monto, moneda, estado)
    // — a propósito, para poder reusar tal cual el modal de selección de "reportar factura" y
    // el modal "Cambiar Orden de Compra" de Compras sin tocar su HTML/JS, solo la fuente de datos.
    public function getEntradasMercanciaFlatByCardcode($cardcode, $estado = 'abierta') {
        try {
            $sap = new DatabaseSAP();
            $conexion = $sap->CONEXION_HANA('T_GT_AGROCENTRO_2016');

            $query = "
                SELECT
                    T0.\"DocEntry\" AS \"docentry\",
                    T0.\"DocNum\"   AS \"numero_oc\",
                    T0.\"DocDate\"  AS \"fecha\",
                    T0.\"DocTotal\" AS \"monto\",
                    COALESCE(T0.\"DocCur\", 'GTQ') AS \"moneda\",
                    CASE
                        WHEN T0.\"DocStatus\" = 'O' THEN 'abierta'
                        ELSE 'cerrada'
                    END AS \"estado\"
                FROM \"T_GT_AGROCENTRO_2016\".OPDN T0
                WHERE T0.\"CardCode\" = ?
                  AND T0.\"CANCELED\" <> 'Y'
            ";

            if ($estado === 'abierta') {
                $query .= " AND T0.\"DocStatus\" = 'O'";
            } elseif ($estado === 'cerrada') {
                $query .= " AND T0.\"DocStatus\" = 'C'";
            }

            $query .= " ORDER BY T0.\"DocDate\" DESC";

            error_log("Ejecutando consulta SAP OPDN (Entrada de Mercancía, plano) para CardCode: " . $cardcode);

            $stmt = odbc_prepare($conexion, $query);
            if (!$stmt || !odbc_execute($stmt, [$cardcode])) {
                throw new Exception("Error ejecutando consulta: " . odbc_errormsg($conexion));
            }

            $entradas = [];
            while ($row = odbc_fetch_object($stmt)) {
                $entradas[] = [
                    'docentry'  => $row->docentry ?? '',
                    'numero_oc' => $row->numero_oc ?? '',
                    'fecha'     => $row->fecha ?? '',
                    'monto'     => (float)($row->monto ?? 0),
                    'moneda'    => $row->moneda ?? 'GTQ',
                    'estado'    => $row->estado ?? 'abierta'
                ];
            }

            odbc_free_result($stmt);
            odbc_close($conexion);

            error_log("Entradas de Mercancía (plano) encontradas desde SAP: " . count($entradas));
            return $entradas;

        } catch (Exception $e) {
            error_log("Error al consultar entradas de mercancía (plano) desde SAP: " . $e->getMessage());
            return [];
        }
    }

    // Extrae los DocEntry de SAP guardados en facturas.ordenes_relacionadas (columna JSON).
    // El JSON puede venir en dos formatos según de dónde se creó la factura: ["9000276"]
    // (un DocEntry por elemento) o ["9000276,9000275"] (varios DocEntry unidos por coma en
    // un solo elemento, como los guarda el formulario de reportar factura del proveedor) —
    // por eso se separan por coma antes de devolverlos.
    private function normalizarDocEntries($ordenesRelacionadasJson) {
        $ordenes = json_decode($ordenesRelacionadasJson ?? '[]', true) ?: [];
        $docEntries = [];
        foreach ($ordenes as $item) {
            foreach (explode(',', (string)$item) as $pieza) {
                $pieza = trim($pieza);
                if ($pieza !== '' && ctype_digit($pieza)) {
                    $docEntries[] = (int)$pieza;
                }
            }
        }
        return array_values(array_unique($docEntries));
    }

    // Suma el DocTotal en SAP de las órdenes de compra vinculadas a UNA factura. Se usa en la
    // vista de detalle (tras buscar una factura puntual); si SAP falla o no hay órdenes
    // vinculadas devuelve null y el llamador simplemente omite la etiqueta comparativa.
    public function getMontoOrdenesRelacionadas($cardcode, $ordenesRelacionadasJson) {
        $docEntries = $this->normalizarDocEntries($ordenesRelacionadasJson);

        if (empty($docEntries) || empty($cardcode)) {
            return null;
        }

        try {
            $sap = new DatabaseSAP();
            $conexion = $sap->CONEXION_HANA('T_GT_AGROCENTRO_2016');

            $placeholders = implode(',', array_fill(0, count($docEntries), '?'));
            $query = "
                SELECT SUM(T0.\"DocTotal\") AS \"total\"
                FROM \"T_GT_AGROCENTRO_2016\".OPOR T0
                WHERE T0.\"CardCode\" = ?
                  AND T0.\"DocEntry\" IN ($placeholders)
            ";

            $stmt = odbc_prepare($conexion, $query);
            $params = array_merge([$cardcode], $docEntries);
            if (!$stmt || !odbc_execute($stmt, $params)) {
                throw new Exception("Error ejecutando consulta: " . odbc_errormsg($conexion));
            }

            $row = odbc_fetch_object($stmt);
            odbc_free_result($stmt);
            odbc_close($conexion);

            return ($row && $row->total !== null) ? (float)$row->total : null;
        } catch (Exception $e) {
            error_log("Error al consultar monto de órdenes vinculadas: " . $e->getMessage());
            return null;
        }
    }

    // Trae en UNA sola consulta a SAP el DocTotal de todos los DocEntry referenciados por una
    // lista de columnas ordenes_relacionadas (una por factura). Pensado para las tablas de
    // listado (hasta 50 facturas) de las pantallas de aprobación, para no hacer una consulta
    // a SAP por fila. Devuelve un mapa [docentry => monto]; si no hay DocEntry válidos o SAP
    // falla devuelve un array vacío (el llamador simplemente omite la etiqueta para esas filas).
    public function getMontosOrdenesBatch(array $listaOrdenesRelacionadasJson) {
        $docEntries = [];
        foreach ($listaOrdenesRelacionadasJson as $json) {
            $docEntries = array_merge($docEntries, $this->normalizarDocEntries($json));
        }
        $docEntries = array_values(array_unique($docEntries));

        if (empty($docEntries)) {
            return [];
        }

        try {
            $sap = new DatabaseSAP();
            $conexion = $sap->CONEXION_HANA('T_GT_AGROCENTRO_2016');

            $placeholders = implode(',', array_fill(0, count($docEntries), '?'));
            $query = "
                SELECT T0.\"DocEntry\" AS \"docentry\", T0.\"DocTotal\" AS \"monto\"
                FROM \"T_GT_AGROCENTRO_2016\".OPOR T0
                WHERE T0.\"DocEntry\" IN ($placeholders)
            ";

            $stmt = odbc_prepare($conexion, $query);
            if (!$stmt || !odbc_execute($stmt, $docEntries)) {
                throw new Exception("Error ejecutando consulta: " . odbc_errormsg($conexion));
            }

            $mapa = [];
            while ($row = odbc_fetch_object($stmt)) {
                $mapa[(int)$row->docentry] = (float)$row->monto;
            }
            odbc_free_result($stmt);
            odbc_close($conexion);

            return $mapa;
        } catch (Exception $e) {
            error_log("Error al consultar montos de órdenes (batch): " . $e->getMessage());
            return [];
        }
    }

    // Igual que getMontoOrdenesRelacionadas() pero para proveedores material_empaque, cuya
    // factura queda vinculada a una Entrada de Mercancía (OPDN) en vez de una Orden de Compra.
    public function getMontoEntradaMercanciaRelacionada($cardcode, $ordenesRelacionadasJson) {
        $docEntries = $this->normalizarDocEntries($ordenesRelacionadasJson);

        if (empty($docEntries) || empty($cardcode)) {
            return null;
        }

        try {
            $sap = new DatabaseSAP();
            $conexion = $sap->CONEXION_HANA('T_GT_AGROCENTRO_2016');

            $placeholders = implode(',', array_fill(0, count($docEntries), '?'));
            $query = "
                SELECT SUM(T0.\"DocTotal\") AS \"total\"
                FROM \"T_GT_AGROCENTRO_2016\".OPDN T0
                WHERE T0.\"CardCode\" = ?
                  AND T0.\"DocEntry\" IN ($placeholders)
            ";

            $stmt = odbc_prepare($conexion, $query);
            $params = array_merge([$cardcode], $docEntries);
            if (!$stmt || !odbc_execute($stmt, $params)) {
                throw new Exception("Error ejecutando consulta: " . odbc_errormsg($conexion));
            }

            $row = odbc_fetch_object($stmt);
            odbc_free_result($stmt);
            odbc_close($conexion);

            return ($row && $row->total !== null) ? (float)$row->total : null;
        } catch (Exception $e) {
            error_log("Error al consultar monto de entrada de mercancía vinculada: " . $e->getMessage());
            return null;
        }
    }

    // Igual que getMontosOrdenesBatch() pero consultando OPDN, para las filas de material_empaque
    // en las tablas de listado de las pantallas de aprobación.
    public function getMontosEntradasMercanciaBatch(array $listaOrdenesRelacionadasJson) {
        $docEntries = [];
        foreach ($listaOrdenesRelacionadasJson as $json) {
            $docEntries = array_merge($docEntries, $this->normalizarDocEntries($json));
        }
        $docEntries = array_values(array_unique($docEntries));

        if (empty($docEntries)) {
            return [];
        }

        try {
            $sap = new DatabaseSAP();
            $conexion = $sap->CONEXION_HANA('T_GT_AGROCENTRO_2016');

            $placeholders = implode(',', array_fill(0, count($docEntries), '?'));
            $query = "
                SELECT T0.\"DocEntry\" AS \"docentry\", T0.\"DocTotal\" AS \"monto\"
                FROM \"T_GT_AGROCENTRO_2016\".OPDN T0
                WHERE T0.\"DocEntry\" IN ($placeholders)
            ";

            $stmt = odbc_prepare($conexion, $query);
            if (!$stmt || !odbc_execute($stmt, $docEntries)) {
                throw new Exception("Error ejecutando consulta: " . odbc_errormsg($conexion));
            }

            $mapa = [];
            while ($row = odbc_fetch_object($stmt)) {
                $mapa[(int)$row->docentry] = (float)$row->monto;
            }
            odbc_free_result($stmt);
            odbc_close($conexion);

            return $mapa;
        } catch (Exception $e) {
            error_log("Error al consultar montos de entradas de mercancía (batch): " . $e->getMessage());
            return [];
        }
    }

    // Suma, a partir del mapa devuelto por getMontosOrdenesBatch() o getMontosEntradasMercanciaBatch(),
    // el monto correspondiente a las órdenes/entradas vinculadas de UNA factura. Devuelve null si
    // esa factura no tiene ninguna vinculada presente en el mapa (sin vínculo, o SAP no la devolvió).
    public function getMontoOrdenDesdeMapa($ordenesRelacionadasJson, array $mapaMontos) {
        $docEntries = $this->normalizarDocEntries($ordenesRelacionadasJson);
        if (empty($docEntries)) {
            return null;
        }

        $total = 0;
        $encontrado = false;
        foreach ($docEntries as $docEntry) {
            if (isset($mapaMontos[$docEntry])) {
                $total += $mapaMontos[$docEntry];
                $encontrado = true;
            }
        }

        return $encontrado ? $total : null;
    }

    // Fallback local (por si SAP no responde)
    private function getOrdenesCompraLocal($cardcode, $estado = 'abierta') {
        $sql = "SELECT id, docentry, numero_oc, fecha, monto, moneda, estado 
                FROM ordenes_compra WHERE cardcode = ?";

        $params = [$cardcode];

        if ($estado !== 'todas') {
            $sql .= " AND estado = ?";
            $params[] = $estado;
        }

        $sql .= " ORDER BY fecha DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrdenesCompraAbiertas($cardcode) {
        $stmt = $this->pdo->prepare("
            SELECT id, docentry, numero_oc, fecha, monto, moneda 
            FROM ordenes_compra 
            WHERE cardcode = ? AND estado = 'abierta'
            ORDER BY fecha DESC
        ");
        $stmt->execute([$cardcode]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

        // Obtener detalle completo de una Orden de Compra desde SAP (para PDF)
    public function getOrdenCompraDetallada($docentry, $cardcode) {
        try {
            $sap = new DatabaseSAP();
            $conexion = $sap->CONEXION_HANA('GT_AGROCENTRO_2016');

            $query = "
                SELECT 
                    T0.\"DocNum\"       AS numero_oc,
                    T0.\"CardCode\"     AS cardcode,
                    T0.\"CardName\"     AS proveedor,
                    T0.\"DocDate\"      AS fecha,
                    T0.\"DocTotal\"     AS total,
                    T0.\"Comments\"     AS observaciones,
                    T1.\"OcrCode\"      AS centro_costo,
                    T1.\"AcctCode\"     AS cuenta,
                    T2.\"AcctName\"     AS nombre_cuenta,
                    T1.\"Dscription\"   AS descripcion,
                    T1.\"LineTotal\"    AS monto_linea
                FROM OPOR T0
                INNER JOIN POR1 T1 ON T0.\"DocEntry\" = T1.\"DocEntry\"
                INNER JOIN OACT T2 ON T1.\"AcctCode\" = T2.\"AcctCode\"
                WHERE T0.\"DocEntry\" = ?
                  AND T0.\"CardCode\" = ?
                  AND T0.\"DocStatus\" = 'O'
            ";

            $stmt = odbc_prepare($conexion, $query);
            odbc_execute($stmt, [$docentry, $cardcode]);

            $lineas = [];
            while ($row = odbc_fetch_object($stmt)) {
                $lineas[] = [
                    'centro_costo'  => $row->centro_costo ?? '',
                    'cuenta'        => $row->cuenta ?? '',
                    'nombre_cuenta' => $row->nombre_cuenta ?? '',
                    'descripcion'   => $row->descripcion ?? '',
                    'monto_linea'   => (float)($row->monto_linea ?? 0)
                ];
            }

            // Datos generales de la orden (tomamos del primer registro)
            $orden = !empty($lineas) ? [
                'numero_oc'     => $row->numero_oc ?? '',
                'fecha'         => $row->fecha ?? '',
                'total'         => (float)($row->total ?? 0),
                'observaciones' => $row->observaciones ?? ''
            ] : [];

            odbc_free_result($stmt);
            odbc_close($conexion);

            return ['orden' => $orden, 'lineas' => $lineas];

        } catch (Exception $e) {
            error_log("Error al obtener detalle de orden SAP: " . $e->getMessage());
            return ['orden' => [], 'lineas' => []];
        }
    }

    // Indica si el proveedor está clasificado como "Contabilidad" en SAP (OCRD.QryGroup9 = Properties9).
    // Estos proveedores saltan la autorización de Compras: sus facturas entran directo a la cola de Contabilidad.
    public function esProveedorContabilidad($cardcode) {
        try {
            $sap = new DatabaseSAP();
            $conexion = $sap->CONEXION_HANA('T_GT_AGROCENTRO_2016');

            $query = '
                SELECT T0."QryGroup9" as "qrygroup9"
                FROM "T_GT_AGROCENTRO_2016".OCRD T0
                WHERE T0."CardCode" = ?
            ';

            $stmt = odbc_prepare($conexion, $query);
            if (!$stmt || !odbc_execute($stmt, [$cardcode])) {
                throw new Exception("Error ejecutando consulta: " . odbc_errormsg($conexion));
            }

            $row = odbc_fetch_array($stmt);
            odbc_free_result($stmt);
            odbc_close($conexion);

            return $row && trim($row['qrygroup9'] ?? '') === 'Y';
        } catch (Exception $e) {
            error_log("esProveedorContabilidad - Error consultando OCRD para $cardcode: " . $e->getMessage());
            return false;
        }
    }

}