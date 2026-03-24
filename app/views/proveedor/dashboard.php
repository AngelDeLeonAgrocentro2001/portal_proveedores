<?php 
$nombre = htmlspecialchars($proveedor['nombre']);
$nit    = htmlspecialchars($proveedor['nit']);
$dias   = (int)$proveedor['dias_credito'];
?>

<div class="dashboard-container">
    <div class="welcome">
        <h1>Bienvenido, <?= $nombre ?></h1>
        <p>NIT: <?= $nit ?> | Días de crédito: <?= $dias ?></p>
    </div>

    <!-- Tarjetas de resumen -->
    <div class="cards-grid">
        <div class="card">
            <h3>Total Facturas</h3>
            <p class="big-number"><?= $resumen['total'] ?></p>
        </div>
        <div class="card">
            <h3>Pendientes</h3>
            <p class="big-number"><?= $resumen['reportadas'] + ($resumen['total'] - $resumen['pagadas'] - $resumen['reportadas']) ?></p>
            <small>Monto: Q <?= number_format($resumen['monto_pendiente'], 2) ?></small>
        </div>
        <div class="card">
            <h3>Facturas Pagadas</h3>
            <p class="big-number"><?= $resumen['pagadas'] ?></p>
        </div>
    </div>

    <!-- Últimas Facturas -->
    <div class="section">
        <h2>Últimas Facturas Reportadas</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Factura</th>
                    <th>Fecha</th>
                    <th>Monto</th>
                    <th>Estado</th>
                    <th>Contraseña</th>
                    <th>Pago Esperado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($facturas as $f): ?>
                <tr>
                    <td><?= htmlspecialchars($f['numero_factura']) ?></td>
                    <td><?= date('d/m/Y', strtotime($f['fecha_emision'])) ?></td>
                    <td>Q <?= number_format($f['monto'], 2) ?></td>
                    <td><span class="status <?= $f['estado'] ?>"><?= ucfirst($f['estado']) ?></span></td>
                    <td><strong><?= $f['contrasena_pago'] ?? '—' ?></strong></td>
                    <td><?= $f['fecha_pago_esperada'] ? date('d/m/Y', strtotime($f['fecha_pago_esperada'])) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="index.php?controller=proveedor&action=mis-facturas" class="btn-link">Ver todas mis facturas →</a>
    </div>

    <!-- Últimos Pagos -->
    <div class="section">
        <h2>Últimos Pagos Recibidos</h2>
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
                <?php foreach ($pagos as $p): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($p['fecha_pago'])) ?></td>
                    <td><?= htmlspecialchars($p['numero_factura']) ?></td>
                    <td>Q <?= number_format($p['monto_pagado'], 2) ?></td>
                    <td><?= htmlspecialchars($p['detalle'] ?? 'Pago realizado') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($pagos)): ?>
                <tr><td colspan="4" style="text-align:center;">Aún no tienes pagos registrados</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Menú rápido -->
    <div class="quick-menu">
        <a href="index.php?controller=proveedor&action=reportar-factura" class="btn-primary">+ Reportar Nueva Factura</a>
        <a href="index.php?controller=proveedor&action=ordenes-compra" class="btn-secondary">Ver Órdenes de Compra</a>
        <a href="index.php?controller=proveedor&action=pagos" class="btn-secondary">Ver Todos los Pagos</a>
    </div>
</div>