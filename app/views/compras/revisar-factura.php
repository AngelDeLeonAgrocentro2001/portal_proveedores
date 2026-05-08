<div class="page-container">
    <h1>Revisión de Factura - Compras</h1>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    
    <!-- Datos de la factura -->
    <div class="factura-info">
        <h2>Datos de la Factura</h2>
        <table class="info-table">
            <tr><th>Proveedor:</th><td><?= htmlspecialchars($factura['proveedor_nombre']) ?> (<?= $factura['cardcode'] ?>)</td></tr>
            <tr><th>NIT:</th><td><?= htmlspecialchars($factura['proveedor_nit']) ?></td></tr>
            <tr><th>Factura:</th><td><?= htmlspecialchars($factura['numero_factura']) ?></td></tr>
            <tr><th>Monto:</th><td>Q <?= number_format($factura['monto'], 2) ?></td></tr>
            <tr><th>Fecha Emisión:</th><td><?= date('d/m/Y', strtotime($factura['fecha_emision'])) ?></td></tr>
            <tr><th>Contraseña Actual:</th><td>
                <?php if (!empty($factura['contrasena_pago'])): ?>
                    <strong style="color:#006400;"><?= $factura['contrasena_pago'] ?></strong>
                <?php else: ?>
                    <span class="badge warning">Contraseña anulada</span>
                <?php endif; ?>
            </td></tr>
        </table>
    </div>
    
    <!-- Facturas adicionales -->
    <?php if (!empty($facturasAdicionales)): ?>
    <div class="facturas-adicionales">
        <h2>📄 Facturas Adicionales</h2>
        <table class="data-table">
            <thead><tr><th>Proveedor</th><th>Factura</th><th>Fecha</th><th>Monto</th></tr></thead>
            <tbody>
                <?php foreach ($facturasAdicionales as $ad): ?>
                <tr>
                    <td><?= htmlspecialchars($ad['nombre_proveedor']) ?></td>
                    <td><?= $ad['serie'] ?>-<?= $ad['numero_dte'] ?></td>
                    <td><?= date('d/m/Y', strtotime($ad['fecha_emision'])) ?></td>
                    <td>Q <?= number_format($ad['monto'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Formulario para cambiar órdenes de compra -->
    <div class="cambiar-ordenes">
        <h2>Órdenes de Compra Asociadas</h2>
        <form method="POST" action="index.php?controller=compras&action=cambiarOrdenesCompra">
            <input type="hidden" name="factura_id" value="<?= $factura['id'] ?>">
            
            <div class="form-group">
                <label>Seleccionar Órdenes de Compra (puede seleccionar múltiples):</label>
                <div class="ordenes-list">
                    <?php if (empty($ordenesDisponibles)): ?>
                        <p>No hay órdenes de compra abiertas para este proveedor</p>
                    <?php else: ?>
                        <?php foreach ($ordenesDisponibles as $oc): ?>
                        <label class="checkbox-label">
                            <input type="checkbox" name="ordenes[]" value="<?= $oc['docentry'] ?>"
                                <?= in_array($oc['docentry'], $ordenesActuales) ? 'checked' : '' ?>>
                            <strong><?= htmlspecialchars($oc['numero_oc']) ?></strong>
                            - Q <?= number_format($oc['monto'], 2) ?>
                            (<?= date('d/m/Y', strtotime($oc['fecha'])) ?>)
                        </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>Comentario (opcional):</label>
                <textarea name="comentario" rows="3" placeholder="Agregar comentario sobre la modificación..."></textarea>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <button type="submit" class="btn-primary">💾 Actualizar Órdenes de Compra</button>
                <button type="button" class="btn-secondary" onclick="abrirModalRechazo()">❌ Rechazar Factura</button>
                <button type="button" class="btn-success" onclick="aprobarFactura()">✅ Aprobar Factura</button>
            </div>
        </form>
    </div>
    
    <div style="margin-top: 30px;">
        <a href="index.php?controller=compras&action=revisionPendiente" class="btn-secondary">← Volver a pendientes</a>
    </div>
</div>

<!-- Modal para rechazar factura -->
<div id="modalRechazo" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="close" onclick="cerrarModalRechazo()">&times;</span>
        <h2>Rechazar Factura</h2>
        <form method="POST" action="index.php?controller=compras&action=rechazarFactura">
            <input type="hidden" name="factura_id" value="<?= $factura['id'] ?>">
            <div class="form-group">
                <label>Motivo de Rechazo *</label>
                <textarea name="motivo_rechazo" required rows="4" placeholder="Ej: La factura no corresponde a las órdenes de compra seleccionadas, montos inconsistentes, etc."></textarea>
            </div>
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn-secondary" onclick="cerrarModalRechazo()">Cancelar</button>
                <button type="submit" class="btn-danger">Confirmar Rechazo</button>
            </div>
        </form>
    </div>
</div>

<style>
.info-table td, .info-table th { padding: 8px; text-align: left; }
.info-table th { width: 180px; background: #f0f0f0; }
.ordenes-list { max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 6px; }
.checkbox-label { display: block; padding: 8px; margin: 5px 0; border: 1px solid #eee; border-radius: 6px; cursor: pointer; }
.checkbox-label:hover { background: #f9f9f9; }
.btn-success { background: #28a745; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; }
.btn-danger { background: #dc3545; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; }
</style>

<script>
function abrirModalRechazo() {
    document.getElementById('modalRechazo').style.display = 'flex';
}

function cerrarModalRechazo() {
    document.getElementById('modalRechazo').style.display = 'none';
}

function aprobarFactura() {
    if (confirm('¿Aprobar esta factura? Pasará a validación financiera.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'index.php?controller=compras&action=aprobarFactura';
        form.innerHTML = '<input type="hidden" name="factura_id" value="<?= $factura['id'] ?>">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>