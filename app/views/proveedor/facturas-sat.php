<div class="page-container">
    <h1>Facturas Emitidas por SAT</h1>
    <p>Lista completa de todas las facturas registradas con tu NIT como emisor.</p>

    <?php if (!empty($errorSAT)): ?>
        <div class="alert error"><?= htmlspecialchars($errorSAT) ?></div>
    <?php endif; ?>

    <?php if (!empty($facturasSAT)): ?>
        <p style="margin-bottom: 15px; color:#006400;">
            <strong><?= count($facturasSAT) ?></strong> facturas encontradas
        </p>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha Emisión</th>
                    <th>Serie</th>
                    <th>N° DTE</th>
                    <th>Nombre Emisor</th>
                    <th>Monto Total</th>
                    <th>IVA</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($facturasSAT as $f): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($f['fecha_emision'])) ?></td>
                    <td><?= htmlspecialchars($f['serie'] ?? '') ?></td>
                    <td><strong><?= htmlspecialchars($f['numero_dte'] ?? '') ?></strong></td>
                    <td><?= htmlspecialchars($f['nombre_emisor'] ?? '') ?></td>
                    <td>Q <?= number_format($f['gran_total'] ?? 0, 2) ?></td>
                    <td>Q <?= number_format($f['iva'] ?? 0, 2) ?></td>
                    <td>
                        <span class="status <?= ($f['usado'] ?? 'X') === 'Y' ? 'usada' : 'disponible' ?>">
                            <?= ($f['usado'] ?? 'X') === 'Y' ? 'Ya usada' : 'Disponible' ?>
                        </span>
                    </td>
                    <td>
                        <?php if (($f['usado'] ?? 'X') !== 'Y'): ?>
                            <a href="index.php?controller=proveedor&action=reportarFactura&preseleccion=<?= urlencode(($f['serie'] ?? '').' '.($f['numero_dte'] ?? '')) ?>" 
                               class="btn-small">Reportar esta Factura</a>
                        <?php else: ?>
                            <span style="color:#999; font-size:0.9em;">Ya reportada</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php else: ?>
        <div class="alert info">
            No se encontraron facturas con tu NIT como emisor.
        </div>
    <?php endif; ?>

    <div style="margin-top: 30px; text-align: center;">
        <a href="index.php?controller=proveedor&action=dashboard" class="btn-secondary">← Volver al Dashboard</a>
    </div>
</div>