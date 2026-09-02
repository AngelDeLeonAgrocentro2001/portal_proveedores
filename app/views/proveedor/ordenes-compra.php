<div class="page-container">
    <h1><?= $esMaterialEmpaque ? 'Mis Entradas de Mercancía' : 'Mis Órdenes de Compra' ?></h1>

    <!-- Filtros -->
    <div class="filters">
        <a href="?controller=proveedor&action=ordenesCompra&estado=abierta"
           class="filter-btn <?= ($estadoFiltro ?? 'abierta') === 'abierta' ? 'active' : '' ?>">Abiertas</a>
        <a href="?controller=proveedor&action=ordenesCompra&estado=cerrada"
           class="filter-btn <?= ($estadoFiltro ?? '') === 'cerrada' ? 'active' : '' ?>">Cerradas</a>
        <a href="?controller=proveedor&action=ordenesCompra&estado=todas"
           class="filter-btn <?= ($estadoFiltro ?? '') === 'todas' ? 'active' : '' ?>">Todas</a>
    </div>

    <?php if ($esMaterialEmpaque): ?>

        <!-- Resumen Total (Entrada de Mercancía) -->
        <div class="summary-box">
            <strong>Total de Entradas:</strong>
            <span style="font-size:1.8rem; color:#006400;">
                <?= count($entradasMercancia) ?>
            </span>
            <small>documentos</small>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>N° Entrada</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th>Código Artículo</th>
                    <th>Descripción</th>
                    <th>Cantidad</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($entradasMercancia)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:40px;">
                            No se encontraron entradas de mercancía con este filtro.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($entradasMercancia as $entrada): $numLineas = count($entrada['lineas']); ?>
                        <?php foreach ($entrada['lineas'] as $i => $linea): ?>
                        <tr>
                            <?php if ($i === 0): ?>
                            <td rowspan="<?= $numLineas ?>"><strong><?= htmlspecialchars($entrada['docnum']) ?></strong></td>
                            <td rowspan="<?= $numLineas ?>"><?= date('d/m/Y', strtotime($entrada['docdate'])) ?></td>
                            <td rowspan="<?= $numLineas ?>">
                                <span class="status <?= strtolower($entrada['estado']) ?>">
                                    <?= ucfirst($entrada['estado']) ?>
                                </span>
                            </td>
                            <?php endif; ?>
                            <td><?= htmlspecialchars($linea['itemcode']) ?></td>
                            <td><?= htmlspecialchars($linea['descripcion']) ?></td>
                            <td style="text-align:right;"><?= number_format($linea['cantidad'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

    <?php else: ?>

        <!-- Resumen Total -->
        <div class="summary-box">
            <strong>Total de Órdenes:</strong>
            <span style="font-size:1.8rem; color:#006400;">
                Q <?= number_format($totalMonto ?? 0, 2) ?>
            </span>
            <small>(<?= count($ordenes) ?> órdenes)</small>
        </div>

            <table class="data-table">
            <thead>
                <tr>
                    <th>N° Orden</th>
                    <th>Fecha</th>
                    <th>Monto</th>
                    <th>Saldo Pendiente</th>
                    <th>Moneda</th>
                    <th>Estado</th>
                    <th>DocEntry SAP</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ordenes)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding:40px;">
                            No se encontraron órdenes de compra con este filtro.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($ordenes as $oc): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($oc['numero_oc']) ?></strong></td>
                        <td><?= date('d/m/Y', strtotime($oc['fecha'])) ?></td>
                        <td>Q <?= number_format($oc['monto'], 2) ?></td>
                        <td>
                            <?php if (($oc['saldo_pendiente'] ?? 0) > 0.01): ?>
                                <strong style="color:#006400;">Q <?= number_format($oc['saldo_pendiente'], 2) ?></strong>
                            <?php else: ?>
                                <span style="color:#999;">Q 0.00</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($oc['moneda']) ?></td>
                        <td>
                            <span class="status <?= strtolower($oc['estado']) ?>">
                                <?= ucfirst($oc['estado']) ?>
                            </span>
                        </td>
                        <td><?= $oc['docentry'] ?></td>
                        <td>
                            <a href="index.php?controller=proveedor&action=pdfOrdenCompra&docentry=<?= $oc['docentry'] ?>"
                               class="btn-small" target="_blank">
                                📄 Imprimir PDF
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <p style="font-size:0.85rem; color:#777; margin-top:10px;">
            El <strong>Saldo Pendiente</strong> es el monto que SAP tiene disponible en este momento para esa orden — úsalo como referencia al reportar tu factura.
        </p>

    <?php endif; ?>

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