<?php
// app/views/proveedor/gastos-cuenta-ajena.php
?>
<div class="page-container">
    <h1>Gastos de Cuenta Ajena</h1>
    <p>Factura: <strong><?= htmlspecialchars($factura['numero_factura']) ?></strong></p>
    <p>Monto Factura: <strong>Q <?= number_format($factura['monto'], 2) ?></strong></p>

    <?php if (!empty($success)): ?>
        <div class="alert success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Formulario para agregar gasto -->
    <div class="form-container" style="max-width:700px;">
        <h2>Registrar Nuevo Gasto</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Concepto del Gasto *</label>
                <input type="text" name="concepto" required placeholder="Ej: Flete adicional, Gastos administrativos, etc.">
            </div>
            <div class="form-group">
                <label>Monto (Q) *</label>
                <input type="number" name="monto" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Número de Factura (opcional)</label>
                <input type="text" name="numero_factura" placeholder="Factura del gasto adicional">
            </div>
            <div class="form-group">
                <label>Comprobante PDF (opcional)</label>
                <input type="file" name="pdf_comprobante" accept=".pdf">
                <small>Sube el comprobante del gasto si lo tienes</small>
            </div>
            <button type="submit" class="btn-primary">Registrar Gasto Adicional</button>
        </form>
    </div>

    <!-- Lista de gastos registrados -->
    <h2 style="margin-top:40px;">Gastos Registrados</h2>
    
    <?php if (empty($gastos)): ?>
        <div class="alert info">No hay gastos registrados para esta factura</div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Concepto</th>
                    <th>Monto</th>
                    <th>Factura</th>
                    <th>Comprobante</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $totalGastos = 0;
                foreach ($gastos as $gasto): 
                    $totalGastos += $gasto['monto'];
                ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($gasto['fecha_registro'])) ?></td>
                    <td><?= htmlspecialchars($gasto['concepto']) ?></td>
                    <td><strong>Q <?= number_format($gasto['monto'], 2) ?></strong></td>
                    <td><?= htmlspecialchars($gasto['numero_factura'] ?? '—') ?></td>
                    <td>
                        <?php if (!empty($gasto['pdf_comprobante'])): ?>
                            <a href="index.php?controller=proveedor&action=descargarComprobanteGasto&id=<?= $gasto['id'] ?>&factura_id=<?= $factura_id ?>" 
                               class="btn-small" target="_blank">📄 Ver</a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status <?= $gasto['estado'] ?>">
                            <?= ucfirst($gasto['estado']) ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este gasto?')">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="gasto_id" value="<?= $gasto['id'] ?>">
                            <button type="submit" class="btn-small" style="background:#dc3545;">🗑️ Eliminar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr style="background:#f0f8f0; font-weight:bold;">
                    <td colspan="2" style="text-align:right;">TOTAL GASTOS:</td>
                    <td colspan="5">Q <?= number_format($totalGastos, 2) ?></td>
                </tr>
                <tr style="background:#e8f4e8; font-weight:bold;">
                    <td colspan="2" style="text-align:right;">TOTAL FACTURA + GASTOS:</td>
                    <td colspan="5">Q <?= number_format($factura['monto'] + $totalGastos, 2) ?></td>
                </tr>
            </tbody>
        </table>
    <?php endif; ?>

    <div style="margin-top: 30px; text-align: center;">
        <a href="index.php?controller=proveedor&action=misFacturas" class="btn-secondary">← Volver a Mis Facturas</a>
    </div>
</div>

<style>
.alert.info {
    background: #cce5ff;
    color: #004085;
    border: 1px solid #b8daff;
}
</style>