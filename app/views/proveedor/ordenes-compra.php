<div class="page-container">
    <h1>Mis Órdenes de Compra</h1>

    <!-- Filtros -->
    <div class="filters">
        <a href="?controller=proveedor&action=ordenesCompra&estado=abierta" 
           class="filter-btn <?= ($estadoFiltro ?? 'abierta') === 'abierta' ? 'active' : '' ?>">Abiertas</a>
        <a href="?controller=proveedor&action=ordenesCompra&estado=cerrada" 
           class="filter-btn <?= ($estadoFiltro ?? '') === 'cerrada' ? 'active' : '' ?>">Cerradas</a>
        <a href="?controller=proveedor&action=ordenesCompra&estado=todas" 
           class="filter-btn <?= ($estadoFiltro ?? '') === 'todas' ? 'active' : '' ?>">Todas</a>
    </div>

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
                <th>Moneda</th>
                <th>Estado</th>
                <th>DocEntry SAP</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($ordenes)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:40px;">
                        No se encontraron órdenes de compra con este filtro.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($ordenes as $oc): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($oc['numero_oc']) ?></strong></td>
                    <td><?= date('d/m/Y', strtotime($oc['fecha'])) ?></td>
                    <td>Q <?= number_format($oc['monto'], 2) ?></td>
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