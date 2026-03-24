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
                SUM(CASE WHEN estado IN ('reportada','validada','en_sap') THEN monto ELSE 0 END) as monto_pendiente
            FROM facturas 
            WHERE cardcode = ?
        ");
        $stmt->execute([$cardcode]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getUltimasFacturas($cardcode, $limit = 5) {
        $stmt = $this->pdo->prepare("
            SELECT id, numero_factura, fecha_emision, monto, estado, contrasena_pago, fecha_pago_esperada 
            FROM facturas 
            WHERE cardcode = ? 
            ORDER BY fecha_creacion DESC 
            LIMIT ?
        ");
        $stmt->execute([$cardcode, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUltimosPagos($cardcode, $limit = 5) {
        $stmt = $this->pdo->prepare("
            SELECT p.fecha_pago, f.numero_factura, p.monto_pagado, p.detalle
            FROM pagos p
            JOIN facturas f ON p.factura_id = f.id
            WHERE f.cardcode = ?
            ORDER BY p.fecha_pago DESC 
            LIMIT ?
        ");
        $stmt->execute([$cardcode, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}