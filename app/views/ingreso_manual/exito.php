<?php
// app/views/ingreso_manual/exito.php
// Agrega esto al principio para depuración
if (!isset($success) || empty($success)) {
    // Redirigir al inicio si no hay datos de éxito
    header('Location: index.php?controller=ingresoManual&action=index');
    exit;
}

// Valores seguros con fallbacks
$contrasena = $success['contrasena'] ?? 'No disponible';
$numero_factura = $success['numero_factura'] ?? 'No disponible';
$proveedor_nombre = $success['proveedor'] ?? 'Proveedor';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contraseña Generada - Agrosistemas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .success-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 30px 50px rgba(0,0,0,0.2);
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        h1 {
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .contrasena-box {
            background: #f0f8f0;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            border: 2px dashed #28a745;
        }
        
        .contrasena {
            font-family: 'Courier New', monospace;
            font-size: 1.5rem;
            font-weight: bold;
            color: #006400;
            letter-spacing: 2px;
        }
        
        .info-text {
            color: #666;
            margin: 20px 0;
            line-height: 1.6;
        }
        
        .buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
        }
        
        .btn-primary:hover, .btn-secondary:hover {
            transform: translateY(-2px);
            transition: transform 0.2s;
        }
    </style>
</head>
<body>
    <div class="success-card">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h1>¡Contraseña Generada!</h1>
        
        <div class="contrasena-box">
            <div style="font-size: 0.9rem; color: #666; margin-bottom: 10px;">
                <i class="fas fa-key"></i> Contraseña de Pago
            </div>
            <div class="contrasena">
                <?= htmlspecialchars($contrasena) ?>
            </div>
        </div>
        
        <div class="info-text">
            <p><strong>Factura:</strong> <?= htmlspecialchars($numero_factura) ?></p>
            <p><strong>Proveedor:</strong> <?= htmlspecialchars($proveedor_nombre) ?></p>
            <hr style="margin: 15px 0;">
            <p style="font-size: 0.9rem;">
                <i class="fas fa-info-circle"></i> La factura ha sido registrada y seguirá el siguiente flujo:
            </p>
            <ol style="text-align: left; margin-top: 10px;">
                <li>📋 Revisión por Compras</li>
                <li>📤 Registro en SAP (Contabilidad)</li>
                <li>💰 Aprobación por Finanzas</li>
                <li>✅ Pago</li>
            </ol>
        </div>
        
        <div class="buttons">
            <a href="index.php?controller=ingresoManual&action=index" class="btn-primary">
                <i class="fas fa-plus"></i> Nueva Contraseña
            </a>
            <a href="javascript:window.print()" class="btn-secondary">
                <i class="fas fa-print"></i> Imprimir
            </a>
        </div>
    </div>
</body>
</html>