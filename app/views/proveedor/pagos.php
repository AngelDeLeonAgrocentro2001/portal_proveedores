<div class="page-container">
    <h1>Mis Pagos Recibidos</h1>

    <?php 
    $totalPagado = 0;
    foreach ($pagos as $p) {
        $totalPagado += $p['monto_pagado'];
    }
    ?>

    <div class="summary-box">
        <strong>Total Pagado:</strong> 
        <span style="font-size:1.8rem; color:#006400;">Q <?= number_format($totalPagado, 2) ?></span>
        <small>(<?= count($pagos) ?> documentos)</small>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha de Pago</th>
                <th>Factura</th>
                <th>Fecha Reporte</th>
                <th>Monto Factura</th>
                <th>Monto Pagado</th>
                <th>Retención</th>
                <th>Detalle</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pagos)): ?>
                <tr><td colspan="7" class="no-data">Aún no tienes pagos registrados</td></tr>
            <?php else: ?>
                <?php foreach ($pagos as $p): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($p['fecha_pago'])) ?></td>
                    <td><?= htmlspecialchars($p['numero_factura']) ?></td>
                    <td><?= date('d/m/Y', strtotime($p['fecha_reporte'])) ?></td>
                    <td>Q <?= number_format($p['monto_factura'], 2) ?></td>
                    <td><strong>Q <?= number_format($p['monto_pagado'], 2) ?></strong></td>
                    <td>Q <?= number_format($p['monto_retencion'] ?? 0, 2) ?></td>
                    <td><?= htmlspecialchars($p['detalle'] ?? 'Pago procesado desde SAP') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div style="margin-top: 30px; text-align: center;">
        <a href="index.php?controller=proveedor&action=dashboard" class="btn-secondary">← Volver al Dashboard</a>
    </div>
</div>

<style>
.summary-box {
    background: #f0f8f0;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    text-align: center;
    border: 1px solid #c3e6cb;
}
</style>