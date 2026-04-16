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
            $conexion = $sap->CONEXION_HANA('GT_AGROCENTRO_2016');

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
                FROM \"GT_AGROCENTRO_2016\".OPOR T0 
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
}