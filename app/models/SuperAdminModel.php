<?php
// app/models/SuperAdminModel.php
require_once BASE_PATH . 'database/DatabasePortal.php';
require_once BASE_PATH . 'database/DatabaseSAP.php';

class SuperAdminModel {
    private $pdo;

    public function __construct() {
        $this->pdo = DatabasePortal::getInstance()->getPdo();
    }

    // ==================== PROVEEDORES ====================

    public function getProveedores() {
        return $this->pdo->query("
            SELECT p.*, COALESCE(pdf.activo, 0) AS doble_factura
            FROM proveedores p
            LEFT JOIN proveedores_doble_factura pdf ON pdf.cardcode = p.cardcode
            ORDER BY p.fecha_creacion DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // La tabla proveedores_doble_factura marca, por cardcode, qué proveedores pueden reportar
    // facturas de otros proveedores (transporte, fletes, etc.) en una misma factura del portal.
    public function setDobleFactura($cardcode, $nombre, $activo) {
        $stmt = $this->pdo->prepare("
            INSERT INTO proveedores_doble_factura (cardcode, nombre, activo)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), activo = VALUES(activo)
        ");
        return $stmt->execute([$cardcode, $nombre, $activo ? 1 : 0]);
    }

    public function getProveedorById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM proveedores WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function cardcodeExiste($cardcode) {
        $stmt = $this->pdo->prepare("SELECT 1 FROM proveedores WHERE cardcode = ?");
        $stmt->execute([$cardcode]);
        return (bool)$stmt->fetchColumn();
    }

    // Trae los días de crédito reales del proveedor desde SAP (OCRD.GroupNum -> OCTG.ExtraDays,
    // el "Grupo de Proveedores" configurado en SAP ya trae sus propios días de crédito). Se usa
    // al crear el proveedor en el portal, para no tener que digitarlos a mano y que coincidan con
    // lo que ya está configurado en SAP. Devuelve null si el CardCode no existe en SAP o si SAP
    // no responde — el formulario simplemente no se autocompleta en ese caso, y el campo sigue
    // siendo editable a mano como hasta ahora.
    public function getDiasCreditoSAP($cardcode) {
        if (empty($cardcode)) {
            return null;
        }

        try {
            $sap = new DatabaseSAP();
            $conexion = $sap->CONEXION_HANA('T_GT_AGROCENTRO_2016');

            $query = "
                SELECT
                    T0.\"CardCode\" AS \"cardcode\",
                    T0.\"CardName\" AS \"cardname\",
                    T0.\"GroupNum\" AS \"groupnum\",
                    T1.\"ExtraDays\" AS \"extradays\"
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

            if (!$row) {
                return null;
            }

            return [
                'cardname' => $row->cardname ?? '',
                'dias_credito' => (int)($row->extradays ?? 0)
            ];
        } catch (Exception $e) {
            error_log("Error al consultar días de crédito en SAP: " . $e->getMessage());
            return null;
        }
    }

    public function crearProveedor($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO proveedores
                (cardcode, nit, nombre, direccion, telefono, email, dias_credito, tipo_proveedor, estado, estatus_autorizacion)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'activo', 'aprobado')
        ");
        return $stmt->execute([
            $data['cardcode'], $data['nit'], $data['nombre'], $data['direccion'],
            $data['telefono'], $data['email'], $data['dias_credito'], $data['tipo_proveedor']
        ]);
    }

    // No incluye cardcode/nit: son identificadores usados como FK en facturas, ordenes_compra
    // y usuarios; cambiarlos después de creado el proveedor es riesgoso y no se expone en el panel.
    public function actualizarProveedor($id, $data) {
        $stmt = $this->pdo->prepare("
            UPDATE proveedores
            SET nombre = ?, direccion = ?, telefono = ?, email = ?, dias_credito = ?, tipo_proveedor = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['nombre'], $data['direccion'], $data['telefono'], $data['email'],
            $data['dias_credito'], $data['tipo_proveedor'], $id
        ]);
    }

    public function cambiarEstadoProveedor($id, $estado) {
        $stmt = $this->pdo->prepare("UPDATE proveedores SET estado = ? WHERE id = ?");
        return $stmt->execute([$estado, $id]);
    }

    // ==================== USUARIOS ====================

    public function getUsuarios() {
        return $this->pdo->query("
            SELECT u.*, p.nombre AS proveedor_nombre
            FROM usuarios u
            LEFT JOIN proveedores p ON u.cardcode = p.cardcode
            ORDER BY u.fecha_creacion DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUsuarioById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function crearUsuario($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO usuarios (cardcode, email, username, password, rol, tipo_supervisor, area)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $data['cardcode'], $data['email'], $data['username'], $data['password_hash'],
            $data['rol'], $data['tipo_supervisor'], $data['area']
        ]);
    }

    public function actualizarUsuario($id, $data) {
        if (!empty($data['password_hash'])) {
            $stmt = $this->pdo->prepare("
                UPDATE usuarios
                SET cardcode = ?, email = ?, username = ?, rol = ?, tipo_supervisor = ?, area = ?, password = ?
                WHERE id = ?
            ");
            return $stmt->execute([
                $data['cardcode'], $data['email'], $data['username'], $data['rol'],
                $data['tipo_supervisor'], $data['area'], $data['password_hash'], $id
            ]);
        }

        $stmt = $this->pdo->prepare("
            UPDATE usuarios
            SET cardcode = ?, email = ?, username = ?, rol = ?, tipo_supervisor = ?, area = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['cardcode'], $data['email'], $data['username'], $data['rol'],
            $data['tipo_supervisor'], $data['area'], $id
        ]);
    }

    // Puede lanzar PDOException (SQLSTATE 23000) si el usuario tiene facturas asociadas (facturas.id_usuario)
    public function eliminarUsuario($id) {
        $stmt = $this->pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
