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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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

        .contrasena-cancelada {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            border-left: 4px solid #dc3545;
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 0.85rem;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-warning {
            background: #ff9800;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
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

        .badge-cancelada {
            background: #f8d7da;
            color: #721c24;
        }

        .documentos-list {
            margin-top: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
        }

        .documento-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
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
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 50%;
            max-width: 500px;
        }
    </style>
</head>

<body>
    <div class="admin-header">
        <h1>🏢 Agrosistemas - Gestión de Documentos y Contraseñas</h1>
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

                    <!-- FILA DE CONTRASEÑA CON BOTÓN PDF -->
                    <tr>
                        <td width="150"><strong>Contraseña:</strong></td>
                        <td>
                            <?php if (!empty($factura['contrasena_pago'])): ?>
                                <div class="contrasena-actual" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                    <strong><?= htmlspecialchars($factura['contrasena_pago']) ?></strong>
                                    <!-- <a href="index.php?controller=proveedor&action=pdfContraseña&id=<?= $factura['id'] ?>"
                                        class="btn-small" target="_blank">
                                        📄 Generar PDF Proveedor
                                    </a> -->

                                    <!-- CORREGIDO: Cambiar $f['id'] por $factura['id'] -->
                                    <a href="index.php?controller=admin&action=pdfContraseña&id=<?= $factura['id'] ?>"
                                        class="btn-small" target="_blank">
                                        📄 Generar PDF Proveedor
                                    </a>
                                </div>
                                <?php if (!empty($factura['contrasena_cancelada'])): ?>
                                    <div class="contrasena-cancelada" style="margin-top: 10px;">
                                        <strong>❌ CONTRASEÑA CANCELADA</strong><br>
                                        Motivo: <?= htmlspecialchars($factura['motivo_cancelacion'] ?? 'No especificado') ?><br>
                                        Fecha: <?= date('d/m/Y H:i', strtotime($factura['fecha_cancelacion'] ?? 'now')) ?>
                                    </div>
                                <?php endif; ?>
                            <?php elseif (!empty($factura['contrasena_cancelada'])): ?>
                                <div class="contrasena-cancelada">
                                    <strong>❌ CONTRASEÑA CANCELADA</strong><br>
                                    Motivo: <?= htmlspecialchars($factura['motivo_cancelacion'] ?? 'No especificado') ?><br>
                                    Fecha: <?= date('d/m/Y H:i', strtotime($factura['fecha_cancelacion'] ?? 'now')) ?>
                                    <?php if (!empty($factura['contrasena_pago'])): ?>
                                        <br><br>
                                        <a href="index.php?controller=proveedor&action=pdfContraseña&id=<?= $factura['id'] ?>"
                                            class="btn-small" target="_blank">
                                            📄 Generar PDF de la contraseña cancelada
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color:#999;">No generada aún</span>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <?php if (!empty($factura['fecha_inicio_credito'])): ?>
                        <tr>
                            <td><strong>Fecha Inicio Crédito:</strong></td>
                            <td><?= date('d/m/Y', strtotime($factura['fecha_inicio_credito'])) ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if (!empty($factura['fecha_pago_esperada'])): ?>
                        <tr>
                            <td><strong>Fecha Pago Esperada:</strong></td>
                            <td><?= date('d/m/Y', strtotime($factura['fecha_pago_esperada'])) ?></td>
                        </tr>
                    <?php endif; ?>
                </table>

                <!-- Botón para cancelar contraseña (solo si tiene contraseña y no está cancelada) -->
                <?php if (!empty($factura['contrasena_pago']) && empty($factura['contrasena_cancelada'])): ?>
                    <div style="margin-top: 25px; padding: 15px; background: #fff3cd; border-radius: 8px;">
                        <h3 style="color: #856404;">⚠️ Cancelar Contraseña</h3>
                        <form method="POST" onsubmit="return confirm('¿Estás seguro de cancelar esta contraseña? Esta acción no se puede deshacer.')">
                            <input type="hidden" name="factura_id" value="<?= $factura['id'] ?>">
                            <div class="form-group">
                                <label>Motivo de cancelación *</label>
                                <textarea name="motivo_cancelacion" required style="width:100%; padding:8px;" rows="3" placeholder="Ej: Factura duplicada, error en el monto, etc."></textarea>
                            </div>
                            <button type="submit" name="cancelar_contrasena" class="btn-danger">❌ Cancelar Contraseña</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Resto del código de documentos se mantiene igual... -->
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
                                    <?php if (!empty($f['contrasena_pago']) && empty($f['contrasena_cancelada'])): ?>
                                        <span class="contrasena-actual" style="font-size:0.85rem;">
                                            <?= htmlspecialchars($f['contrasena_pago']) ?>
                                        </span>
                                    <?php elseif (!empty($f['contrasena_cancelada'])): ?>
                                        <span class="badge badge-cancelada">CANCELADA</span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?= !empty($f['fecha_inicio_credito']) ? date('d/m/Y', strtotime($f['fecha_inicio_credito'])) : '—' ?></td>
                                <td>
                                    <a href="?controller=admin&action=gestionarContraseñas&buscar=<?= urlencode($f['numero_factura']) ?>"
                                        class="btn-small">Ver/Gestionar</a>
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