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
    
    <!-- Alerta de saldo pendiente: el monto de la factura no coincide con el saldo pendiente
         real en SAP de la(s) orden(es) que tiene seleccionadas. No bloquea nada — solo informa
         para que Compras decida con criterio si aprueba o rechaza. -->
    <?php if ($comparacionSaldoPendiente !== null): ?>
    <div class="alerta-saldo">
        <h2>⚠️ El monto de la factura no coincide con el saldo pendiente de la orden</h2>
        <p>
            Saldo pendiente real en SAP de las órdenes seleccionadas:
            <strong>Q <?= number_format($comparacionSaldoPendiente['total_saldo_pendiente'], 2) ?></strong>
            &nbsp;|&nbsp; Monto de la factura: <strong>Q <?= number_format($factura['monto'], 2) ?></strong>
            &nbsp;|&nbsp; Diferencia:
            <strong style="color:<?= $comparacionSaldoPendiente['diferencia'] > 0 ? '#dc3545' : '#b45309' ?>;">
                Q <?= number_format(abs($comparacionSaldoPendiente['diferencia']), 2) ?>
                (<?= $comparacionSaldoPendiente['diferencia'] > 0 ? 'factura mayor que el saldo' : 'saldo mayor que la factura' ?>)
            </strong>
        </p>
        <?php foreach ($comparacionSaldoPendiente['detalle'] as $docentry => $orden): ?>
        <table class="data-table" style="margin-top:12px;">
            <thead>
                <tr>
                    <th colspan="3">Orden <?= htmlspecialchars($orden['docnum']) ?> (DocEntry <?= (int)$docentry ?>) — líneas abiertas en SAP</th>
                </tr>
                <tr>
                    <th># Línea</th>
                    <th>Descripción</th>
                    <th>Saldo Pendiente</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orden['lineas'] as $linea): ?>
                <tr>
                    <td><?= (int)$linea['linenum'] ?></td>
                    <td><?= htmlspecialchars($linea['descripcion']) ?></td>
                    <td>Q <?= number_format($linea['saldo_pendiente'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="font-weight:bold; background:#f8f9fa;">
                    <td colspan="2">Total orden <?= htmlspecialchars($orden['docnum']) ?></td>
                    <td>Q <?= number_format($orden['total'], 2) ?></td>
                </tr>
            </tbody>
        </table>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

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
.alerta-saldo { background: #fff8e6; border: 1px solid #ffe08a; border-left: 5px solid #b45309; border-radius: 8px; padding: 18px 20px; margin-bottom: 25px; }
.alerta-saldo h2 { font-size: 1.1rem; color: #856404; margin-bottom: 10px; }
.alerta-saldo .data-table { width: 100%; border-collapse: collapse; background: white; }
.alerta-saldo .data-table th, .alerta-saldo .data-table td { padding: 8px 10px; border-bottom: 1px solid #eee; text-align: left; font-size: 0.9rem; }
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