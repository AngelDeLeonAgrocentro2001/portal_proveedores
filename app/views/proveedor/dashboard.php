<?php
$nombre = htmlspecialchars($proveedor['nombre'] ?? '');
$nit    = htmlspecialchars($proveedor['nit'] ?? '');
$rol    = $_SESSION['user']['rol'] ?? 'crear_contrasenas';

$mostrarPagos = in_array($rol, ['admin', 'consultas']);
$esAdmin      = ($rol === 'admin');

// Nueva variable: Mostrar tarjetas de resumen solo para admin y consultas
$mostrarResumen = in_array($rol, ['admin', 'consultas']);
?>

<div class="dashboard-container">

    <!-- Bienvenida -->
    <div class="welcome-section">
        <h1>Bienvenido, <?= htmlspecialchars($_SESSION['user']['username'] ?? 'Usuario') ?></h1>
        <p>
            Proveedor: <?= htmlspecialchars($nombre ?? 'N/A') ?><br>
            Código: <?= htmlspecialchars($_SESSION['user']['cardcode']) ?> | NIT: <?= $nit ?>
        </p>
        <small>Rol: <strong><?= ucfirst(str_replace('_', ' ', $rol)) ?></strong></small></br>
        <small>Dias Credito: 30</small>

    </div>

    <div class="Recordatorio">
        <h2>Recordatorio</h2>
        <p>
            En caso de no ser lunes, la contraseña se tomará en cuenta el próximo lunes y solo se pagaran los dias viernes.
        </p>
    </div>
    <a href="index.php?controller=proveedor&action=contacto" class="btn-action secondary">
    <span class="btn-icon">💬</span>
    Contacto y Soporte
</a>

    <!-- Tarjetas de Resumen - SOLO para admin y consultas -->
    <?php if ($mostrarResumen): ?>
        <div class="cards-grid">
            <div class="card">
                <div class="card-icon">📄</div>
                <h3>Total Facturas</h3>
                <p class="big-number"><?= $resumen['total'] ?? 0 ?></p>
            </div>
            <div class="card">
                <div class="card-icon">⏳</div>
                <h3>Pendientes</h3>
                <p class="big-number"><?= ($resumen['reportadas'] ?? 0) + ($resumen['total'] - ($resumen['pagadas'] ?? 0)) ?></p>
                <small>Q <?= number_format($resumen['monto_pendiente'] ?? 0, 2) ?></small>
            </div>
            <div class="card">
                <div class="card-icon">✅</div>
                <h3>Pagadas</h3>
                <p class="big-number"><?= $resumen['pagadas'] ?? 0 ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Acciones Principales -->
    <div class="actions-section">

        <a href="index.php?controller=proveedor&action=reportarFactura" class="btn-action primary">
            <span class="btn-icon">📤</span>
            Reportar Nueva Factura
        </a>

        <a href="index.php?controller=proveedor&action=misFacturas" class="btn-action secondary">
            <span class="btn-icon">📋</span>
            Ver Mis Facturas
        </a>

        <a href="index.php?controller=proveedor&action=ordenesCompra" class="btn-action secondary">
            <span class="btn-icon">🛒</span>
            Ver Órdenes de Compra
        </a>

        <a href="index.php?controller=proveedor&action=facturasSAT" class="btn-action secondary">
            <span class="btn-icon">📌</span>
            Ver Facturas SAT
        </a>

        <?php if ($mostrarPagos): ?>
            <a href="index.php?controller=proveedor&action=pagos" class="btn-action secondary">
                <span class="btn-icon">💰</span>
                Ver Todos los Pagos
            </a>
        <?php endif; ?>

        <?php if ($esAdmin): ?>
            <a href="index.php?controller=proveedor&action=gestionarUsuarios" class="btn-action admin-btn">
                <span class="btn-icon">👥</span>
                Gestionar Usuarios
            </a>
        <?php endif; ?>

    </div>

    <!-- Últimas Facturas Reportadas -->
    <div class="section">
        <h2>Últimas Facturas Reportadas</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Factura</th>
                    <th>Fecha Factura SAT</th>
                    <th>Fecha Reporte</th>
                    <th>Monto</th>
                    <th>Estado</th>
                    <th>Contraseña</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($facturas)): ?>
                    <tr>
                        <td colspan="6" class="no-data">No has reportado facturas aún</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($facturas as $f): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($f['numero_factura']) ?></strong></td>
                            <td>
                                <?= !empty($f['fecha_factura_sat'])
                                    ? date('d/m/Y', strtotime($f['fecha_factura_sat']))
                                    : '<span style="color:#999;">—</span>' ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($f['fecha_emision'])) ?></td>
                            <td>Q <?= number_format($f['monto'], 2) ?></td>
                            <td><span class="status <?= $f['estado'] ?>"><?= ucfirst($f['estado']) ?></span></td>
                            <td>
                                <?php if (!empty($f['contrasena_pago']) && $f['estado'] !== 'pagada'): ?>
                                    <strong style="color:#006400;"><?= htmlspecialchars($f['contrasena_pago']) ?></strong>
                                    <br>
                                    <a href="index.php?controller=proveedor&action=pdfContraseña&id=<?= $f['id'] ?>"
                                        class="btn-small" style="font-size:0.8rem; padding:4px 8px;" target="_blank">
                                        📄 Imprimir PDF
                                    </a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Últimos Pagos (solo para roles autorizados) -->
    <?php if ($mostrarPagos): ?>
        <div class="section">
            <h2>Últimos 5 Pagos Recibidos</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha Pago</th>
                        <th>Factura</th>
                        <th>Monto Pagado</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pagos)): ?>
                        <tr>
                            <td colspan="4" class="no-data">Aún no se registran pagos</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pagos as $p): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($p['fecha_pago'])) ?></td>
                                <td><?= htmlspecialchars($p['numero_factura']) ?></td>
                                <td><strong>Q <?= number_format($p['monto_pagado'], 2) ?></strong></td>
                                <td><?= htmlspecialchars($p['detalle'] ?? 'Pago procesado desde SAP') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>