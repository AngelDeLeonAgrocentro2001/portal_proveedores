<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Proveedores - Finanzas</title>
    <link rel="stylesheet" href="/portal_proveedores/public/assets/css/style.css">
    <style>
        .finanzas-header {
            background: #1a237e;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .finanzas-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .card-pago {
            background: #e8eaf6;
            border-left: 5px solid #1a237e;
        }
        .badge-semana {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        .semana-este { background: #4caf50; color: white; }
        .semana-proximo { background: #ff9800; color: white; }
        .btn-finanzas-aprobar { background: #1a237e; color: white; }
        .btn-finanzas-rechazar { background: #dc3545; color: white; }
    </style>
</head>
<body>
    <div class="finanzas-header">
        <h1>💰 Finanzas - Autorización de Pagos</h1>
        <div>
            👤 <?= htmlspecialchars($_SESSION['user']['username'] ?? 'Usuario') ?>
            <a href="index.php?controller=auth&action=logout" style="color:white; margin-left:20px;">🚪 Cerrar Sesión</a>
        </div>
    </div>
    <div class="finanzas-container"></div>