<?php
// app/controllers/ProveedorController.php
require_once BASE_PATH . 'app/models/ProveedorModel.php';

class ProveedorController {

    public function dashboard() {
        if (!isset($_SESSION['user'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }

        $cardcode = $_SESSION['user']['cardcode'];

        $model = new ProveedorModel();

        $proveedor = $model->getProveedorByCardcode($cardcode);
        $resumen   = $model->getResumenFacturas($cardcode);
        $facturas  = $model->getUltimasFacturas($cardcode);
        $pagos     = $model->getUltimosPagos($cardcode);

        // Cargar las vistas
        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/proveedor/dashboard.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }
}