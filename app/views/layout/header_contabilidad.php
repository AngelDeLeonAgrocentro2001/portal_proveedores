<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Proveedores - Contabilidad</title>
    <link rel="stylesheet" href="/portal_proveedores/public/assets/css/style.css">
    <style>
        .contabilidad-header {
            background: #00695c;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .contabilidad-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .badge-tipo {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        .tipo-transporte { background: #17a2b8; color: white; }
        .tipo-material { background: #ff9800; color: white; }
        .estadisticas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .estadistica-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .estadistica-number {
            font-size: 2rem;
            font-weight: bold;
            color: #00695c;
        }
        .search-box {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .factura-detalle {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 5px solid #00695c;
        }
        .btn-contabilidad {
            background: #00695c;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-pagar {
            background: #4caf50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 25px;
            border-radius: 10px;
            width: 50%;
            max-width: 600px;
        }
        .close {
            float: right;
            font-size: 28px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="contabilidad-header">
        <h1>📊 Contabilidad - Gestión de Pagos</h1>
        <div>
            👤 <?= htmlspecialchars($_SESSION['user']['username'] ?? 'Usuario') ?>
            <a href="index.php?controller=auth&action=logout" style="color:white; margin-left:20px;">🚪 Cerrar Sesión</a>
        </div>
    </div>
    <div class="contabilidad-container"></div>