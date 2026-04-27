<?php
// app/views/admin/gestionar-contrasenas.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agrosistemas - Gestión de Contraseñas</title>
    <link rel="stylesheet" href="/portal_proveedores/public/assets/css/style.css">
    <style>
        .admin-header {
            background: #1a1a2e;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .admin-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
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
            border-left: 5px solid #006400;
        }
        .contrasena-actual {
            font-family: monospace;
            font-size: 1.2rem;
            background: #e9ecef;
            padding: 10px;
            border-radius: 5px;
        }
        .btn-small {
            padding: 5px 10px;
            font-size: 0.85rem;
        }
        .table-container {
            overflow-x: auto;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        .badge-lunes {
            background: #d4edda;
            color: #155724;
        }
        .badge-otro {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🏢 Agrosistemas - Gestión de Contraseñas</h1>
        <div>
            <span>IP: <?= $_SERVER['REMOTE_ADDR'] ?></span>
            <a href="index.php?controller=admin&action=logoutAgrosistemas" style="color:white; margin-left:20px;">🚪 Cerrar Sesión</a>
        </div>
    </div>
    
    <div class="admin-container">
        <?php if (!empty($error)): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <!-- Buscador -->
        <div class="search-box">
            <h2>🔍 Buscar Factura</h2>
            <form method="GET" style="display: flex; gap: 10px;">
                <input type="hidden" name="controller" value="admin">
                <input type="hidden" name="action" value="gestionarContraseñas">
                <input type="text" name="buscar" placeholder="Número de factura..." 
                       style="flex:1;" value="<?= htmlspecialchars($_GET['buscar'] ?? '') ?>">
                <button type="submit" class="btn-primary" style="width: auto;">Buscar</button>
            </form>
        </div>
        
        <!-- Detalle de factura encontrada -->
        <?php if ($factura): ?>
        <div class="factura-detalle">
            <h2>📄 Detalle de Factura</h2>
            <table style="width:100%">
                <tr>
                    <td width="150"><strong>Proveedor:</strong></td>
                    <td><?= htmlspecialchars($factura['proveedor_nombre']) ?> (<?= htmlspecialchars($factura['cardcode']) ?>)</td>
                </tr>
                <tr>
                    <td><strong>Factura:</strong></td>
                    <td><?= htmlspecialchars($factura['numero_factura']) ?></td>
                </tr>
                <tr>
                    <td><strong>Monto:</strong></td>
                    <td>Q <?= number_format($factura['monto'], 2) ?></td>
                </tr>
                <tr>
                    <td><strong>Estado:</strong></td>
                    <td><span class="status <?= $factura['estado'] ?>"><?= ucfirst($factura['estado']) ?></span></td>
                </tr>
                <tr>
                    <td><strong>Contraseña Actual:</strong></td>
                    <td class="contrasena-actual"><?= htmlspecialchars($factura['contrasena_pago'] ?? 'No generada') ?></td>
                </tr>
                <tr>
                    <td><strong>Fecha Inicio Crédito:</strong></td>
                    <td><?= !empty($factura['fecha_inicio_credito']) ? date('d/m/Y', strtotime($factura['fecha_inicio_credito'])) : 'No definida' ?></td>
                </tr>
                <tr>
                    <td><strong>Fecha Pago Esperada:</strong></td>
                    <td><?= !empty($factura['fecha_pago_esperada']) ? date('d/m/Y', strtotime($factura['fecha_pago_esperada'])) : 'No calculada' ?></td>
                </tr>
            </table>
            
            <h3 style="margin-top:25px;">✏️ Actualizar Contraseña</h3>
            <form method="POST" style="margin-top:15px;">
                <input type="hidden" name="factura_id" value="<?= $factura['id'] ?>">
                <div class="form-group">
                    <label>Nueva Contraseña:</label>
                    <input type="text" name="nueva_contrasena" required 
                           value="AGRO-<?= date('Ymd') ?>-<?= strtoupper(substr(uniqid(), -6)) ?>"
                           style="font-family:monospace; font-size:1.1rem;">
                </div>
                <div class="form-group">
                    <label>Fecha de Inicio de Crédito:</label>
                    <input type="date" name="fecha_inicio" value="<?= date('Y-m-d') ?>">
                    <small>Fecha desde la cual se cuentan los 30 días para el pago</small>
                </div>
                <button type="submit" name="actualizar" class="btn-primary">Actualizar Contraseña</button>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Últimas facturas reportadas -->
        <h2>📋 Últimas Facturas Reportadas (Pendientes)</h2>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha Reporte</th>
                        <th>Proveedor</th>
                        <th>CardCode</th>
                        <th>Factura</th>
                        <th>Monto</th>
                        <th>Estado</th>
                        <th>Contraseña</th>
                        <th>Inicio Crédito</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ultimas_facturas)): ?>
                        <tr>
                            <td colspan="9">No hay facturas pendientes</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ultimas_facturas as $f): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($f['fecha_emision'])) ?></td>
                            <td><?= htmlspecialchars(substr($f['proveedor_nombre'], 0, 30)) ?></td>
                            <td><?= htmlspecialchars($f['cardcode']) ?></td>
                            <td><strong><?= htmlspecialchars($f['numero_factura']) ?></strong></td>
                            <td>Q <?= number_format($f['monto'], 2) ?></td>
                            <td><span class="status <?= $f['estado'] ?>"><?= ucfirst($f['estado']) ?></span></td>
                            <td>
                                <span class="contrasena-actual" style="font-size:0.85rem;">
                                    <?= htmlspecialchars($f['contrasena_pago'] ?? '—') ?>
                                </span>
                            </td>
                            <td><?= !empty($f['fecha_inicio_credito']) ? date('d/m/Y', strtotime($f['fecha_inicio_credito'])) : '—' ?></td>
                            <td>
                                <a href="?controller=admin&action=gestionarContraseñas&buscar=<?= urlencode($f['numero_factura']) ?>" 
                                   class="btn-small">Editar</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>