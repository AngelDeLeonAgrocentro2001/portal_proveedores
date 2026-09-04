<div class="page-container">
    <h1>Facturas Emitidas por SAT</h1>
    <p>Lista completa de todas las facturas registradas con tu NIT como emisor.</p>

    <?php if (!empty($errorSAT)): ?>
        <div class="alert error"><?= htmlspecialchars($errorSAT) ?></div>
    <?php endif; ?>

    <form method="GET" style="margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap;">
        <input type="hidden" name="controller" value="proveedor">
        <input type="hidden" name="action" value="facturasSAT">
        <input type="text" name="buscar_sat" value="<?= htmlspecialchars($buscar ?? '') ?>"
               placeholder="🔎 Buscar por número de factura, monto o fecha..."
               style="flex:1; min-width:250px; padding:10px; border:1px solid #ccc; border-radius:6px;">
        <button type="submit" class="btn-primary" style="width:auto;">Buscar</button>
        <?php if (!empty($buscar)): ?>
            <a href="index.php?controller=proveedor&action=facturasSAT" class="btn-secondary" style="width:auto;">Limpiar</a>
        <?php endif; ?>
    </form>

    <?php if (!empty($facturasSAT)): ?>
        <p style="margin-bottom: 15px; color:#006400;">
            <strong><?= number_format($totalFacturasSAT) ?></strong> factura(s) encontrada(s)
            <?php if ($totalPaginas > 1): ?>
                — página <?= $pagina ?> de <?= $totalPaginas ?>
            <?php endif; ?>
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

        <?php if ($totalPaginas > 1): ?>
        <div style="margin-top:20px; display:flex; gap:8px; justify-content:center; flex-wrap:wrap; align-items:center;">
            <?php
                $qsBase = 'controller=proveedor&action=facturasSAT' . ($buscar !== '' ? '&buscar_sat=' . urlencode($buscar) : '');
                $rangoInicio = max(1, $pagina - 2);
                $rangoFin = min($totalPaginas, $pagina + 2);
            ?>
            <?php if ($pagina > 1): ?>
                <a href="index.php?<?= $qsBase ?>&pagina=<?= $pagina - 1 ?>" class="btn-small">‹ Anterior</a>
            <?php endif; ?>

            <?php if ($rangoInicio > 1): ?>
                <a href="index.php?<?= $qsBase ?>&pagina=1" class="btn-small">1</a>
                <?php if ($rangoInicio > 2): ?><span>…</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $rangoInicio; $p <= $rangoFin; $p++): ?>
                <?php if ($p === $pagina): ?>
                    <strong class="btn-small" style="background:#006400; color:#fff;"><?= $p ?></strong>
                <?php else: ?>
                    <a href="index.php?<?= $qsBase ?>&pagina=<?= $p ?>" class="btn-small"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($rangoFin < $totalPaginas): ?>
                <?php if ($rangoFin < $totalPaginas - 1): ?><span>…</span><?php endif; ?>
                <a href="index.php?<?= $qsBase ?>&pagina=<?= $totalPaginas ?>" class="btn-small"><?= $totalPaginas ?></a>
            <?php endif; ?>

            <?php if ($pagina < $totalPaginas): ?>
                <a href="index.php?<?= $qsBase ?>&pagina=<?= $pagina + 1 ?>" class="btn-small">Siguiente ›</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="alert info">
            <?= !empty($buscar) ? 'No se encontraron facturas que coincidan con la búsqueda.' : 'No se encontraron facturas con tu NIT como emisor.' ?>
        </div>
    <?php endif; ?>

    <div style="margin-top: 30px; text-align: center;">
        <a href="index.php?controller=proveedor&action=dashboard" class="btn-secondary">← Volver al Dashboard</a>
    </div>
</div>