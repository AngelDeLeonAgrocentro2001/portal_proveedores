<?php
// app/views/contabilidad/dashboard.php
?>

<h1>📋 Gestión de Pagos a Proveedores</h1>

<?php if (!empty($error)): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Estadísticas -->
<div class="estadisticas-grid">
    <div class="estadistica-card">
        <div class="estadistica-number"><?= $estadisticas['pendientes_sap'] ?? 0 ?></div>
        <div>Pendientes Envío SAP</div>
    </div>
    <div class="estadistica-card">
        <div class="estadistica-number"><?= $estadisticas['en_sap'] ?? 0 ?></div>
        <div>En SAP (Por Pagar)</div>
    </div>
    <div class="estadistica-card">
        <div class="estadistica-number"><?= $estadisticas['pagadas_mes'] ?? 0 ?></div>
        <div>Pagadas (último mes)</div>
    </div>
    <div class="estadistica-card">
        <div class="estadistica-number">Q <?= number_format($estadisticas['monto_pendiente'] ?? 0, 2) ?></div>
        <div>Monto Pendiente</div>
    </div>
</div>

<!-- Buscador -->
<div class="search-box">
    <h2>🔍 Buscar Factura</h2>
    <form method="GET" style="display: flex; gap: 10px;">
        <input type="hidden" name="controller" value="contabilidad">
        <input type="hidden" name="action" value="dashboard">
        <input type="text" name="buscar" placeholder="Número de factura..."
            style="flex:1;" value="<?= htmlspecialchars($_GET['buscar'] ?? '') ?>">
        <button type="submit" class="btn-primary" style="width: auto;">Buscar</button>
    </form>
</div>

<!-- Detalle de factura encontrada -->
<?php if (isset($factura) && $factura): ?>
<div class="factura-detalle" id="factura-detalle">
    <h2>📄 Detalle de Factura</h2>
    <table style="width:100%">
        <tr>
            <td width="150"><strong>Proveedor:</strong></td>
            <td><?= htmlspecialchars($factura['proveedor_nombre']) ?> (<?= htmlspecialchars($factura['cardcode']) ?>)</td>
        </tr>
        <tr>
            <td><strong>Factura:</strong></td>
            <td><?= htmlspecialchars($factura['numero_factura']) ?></td>
        </tr>
        <tr>
            <td><strong>Monto:</strong></td>
            <td>Q <?= number_format($factura['monto'], 2) ?></td>
        </tr>
        <tr>
            <td><strong>Estado actual:</strong></td>
            <td><span class="status <?= $factura['estado'] ?>"><?= ucfirst(str_replace('_', ' ', $factura['estado'])) ?></span></td>
        </tr>
        <?php if (!empty($factura['fecha_pago_propuesta'])): ?>
        <tr>
            <td><strong>Fecha Pago Propuesta:</strong></td>
            <td><strong style="color: #00695c;"><?= date('d/m/Y', strtotime($factura['fecha_pago_propuesta'])) ?></strong></td>
        </tr>
        <?php endif; ?>
        <?php if ($factura['estado'] === 'en_sap'): ?>
        <tr>
            <td><strong>Fecha Envío SAP:</strong></td>
            <td><?= date('d/m/Y H:i', strtotime($factura['fecha_envio_sap'])) ?> por <?= htmlspecialchars($factura['enviado_por']) ?></td>
        </tr>
        <?php if (!empty($factura['comprobante_sap'])): ?>
        <tr>
            <td><strong>Comprobante SAP:</strong></td>
            <td><?= htmlspecialchars($factura['comprobante_sap']) ?></td>
        </tr>
        <?php endif; ?>
        <?php endif; ?>
    </table>
    
    <?php if (!empty($factura['pdf_factura'])): ?>
    <div style="margin-top: 20px;">
        <h3>📎 Factura PDF</h3>
        <iframe src="index.php?controller=contabilidad&action=descargarPDF&id=<?= $factura['id'] ?>&tipo=factura" 
                style="width:100%; height:400px; border:1px solid #ddd;"></iframe>
    </div>
    <?php endif; ?>
    
    <div style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="index.php?controller=contabilidad&action=pdfContraseña&id=<?= $factura['id'] ?>" class="btn-small" target="_blank">📄 Ver Contraseña PDF</a>
        
        <?php if ($factura['estado'] === 'aprobada_finanzas'): ?>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
        <button onclick="mostrarModalEnviarSAP(<?= $factura['id'] ?>)" class="btn-contabilidad">📤 Enviar a SAP</button>
        <button onclick="mostrarModalRechazar(<?= $factura['id'] ?>)" class="btn-rechazar" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">❌ Rechazar</button>
    </div>
        <?php endif; ?>
        
        <?php if ($factura['estado'] === 'en_sap'): ?>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
        <button onclick="mostrarModalRegistrarPago(<?= $factura['id'] ?>, '<?= htmlspecialchars($factura['numero_factura']) ?>', <?= $factura['monto'] ?>)" class="btn-pagar">💰 Registrar Pago</button>
        <button onclick="mostrarModalRechazar(<?= $factura['id'] ?>)" class="btn-rechazar" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">❌ Rechazar</button>
    </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>


<!-- Modal Rechazar Factura -->
<div id="modalRechazar" class="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal('modalRechazar')">&times;</span>
        <h2>❌ Rechazar Factura</h2>
        <p>Esta acción anulará la contraseña y liberará la(s) factura(s) SAT para que el proveedor pueda volver a usarlas.</p>
        
        <input type="hidden" id="factura_id_rechazar" value="">
        
        <div class="form-group">
            <label>Motivo del rechazo *:</label>
            <textarea id="motivo_rechazo" rows="4" style="width:100%; padding:8px;" required placeholder="Especifique el motivo del rechazo..."></textarea>
            <small style="color: #666;">Este motivo se registrará y la factura SAT quedará disponible nuevamente.</small>
        </div>
        
        <div style="margin-top:20px; text-align:right;">
            <button type="button" class="btn-secondary" onclick="cerrarModal('modalRechazar')">Cancelar</button>
            <button type="button" class="btn-rechazar" onclick="confirmarRechazar()" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">❌ Rechazar Factura</button>
        </div>
    </div>
</div>

<!-- Facturas Pendientes de Envío a SAP -->
<h2>⏳ Pendientes de Envío a SAP</h2>
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha Aprobación</th>
                <th>Proveedor</th>
                <th>Tipo</th>
                <th>Factura</th>
                <th>Monto</th>
                <th>Fecha Pago Propuesta</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($facturas_pendientes_sap)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:40px;">No hay facturas pendientes de envío a SAP</td>
                </tr>
            <?php else: ?>
                <?php foreach ($facturas_pendientes_sap as $f): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($f['fecha_aprobacion_finanzas'])) ?></td>
                    <td><?= htmlspecialchars(substr($f['proveedor_nombre'], 0, 30)) ?></td>
                    <td>
                        <?php if ($f['tipo_proveedor'] === 'transporte'): ?>
                            <span class="badge-tipo tipo-transporte">🚚 Transporte</span>
                        <?php elseif ($f['tipo_proveedor'] === 'material_empaque'): ?>
                            <span class="badge-tipo tipo-material">📦 Material</span>
                        <?php else: ?>
                            <?= $f['tipo_proveedor'] ?>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($f['numero_factura']) ?></strong></td>
                    <td>Q <?= number_format($f['monto'], 2) ?></td>
                    <td><strong><?= date('d/m/Y', strtotime($f['fecha_pago_propuesta'])) ?></strong></td>
                    <td>
                        <a href="?controller=contabilidad&action=dashboard&buscar=<?= urlencode($f['numero_factura']) ?>" 
                           class="btn-small">Revisar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Facturas Enviadas a SAP (Pendientes de Pago) -->
<h2>📤 Facturas en SAP (Pendientes de Pago)</h2>
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha Envío SAP</th>
                <th>Proveedor</th>
                <th>Factura</th>
                <th>Monto</th>
                <th>Fecha Pago Propuesta</th>
                <th>Comprobante SAP</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($facturas_en_sap)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:40px;">No hay facturas en SAP pendientes de pago</td>
                </tr>
            <?php else: ?>
                <?php foreach ($facturas_en_sap as $f): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($f['fecha_envio_sap'])) ?></td>
                    <td><?= htmlspecialchars(substr($f['proveedor_nombre'], 0, 30)) ?></td>
                    <td><strong><?= htmlspecialchars($f['numero_factura']) ?></strong></td>
                    <td>Q <?= number_format($f['monto'], 2) ?></td>
                    <td><?= date('d/m/Y', strtotime($f['fecha_pago_propuesta'])) ?></td>
                    <td><?= htmlspecialchars($f['comprobante_sap'] ?? '—') ?></td>
                    <td>
                        <a href="?controller=contabilidad&action=dashboard&buscar=<?= urlencode($f['numero_factura']) ?>" 
                           class="btn-small">Registrar Pago</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </tr>
</div>

<!-- Facturas Pagadas Recientemente -->
<h2>✅ Últimas Facturas Pagadas</h2>
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha Pago</th>
                <th>Proveedor</th>
                <th>Factura</th>
                <th>Monto</th>
                <th>Comprobante Pago</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($facturas_pagadas)): ?>
                <tr>
                    <td colspan="6" style="text-align:center; padding:40px;">No hay facturas pagadas recientemente</td>
                </tr>
            <?php else: ?>
                <?php foreach ($facturas_pagadas as $f): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($f['fecha_pago_real'])) ?></td>
                    <td><?= htmlspecialchars(substr($f['proveedor_nombre'], 0, 30)) ?></td>
                    <td><strong><?= htmlspecialchars($f['numero_factura']) ?></strong></td>
                    <td>Q <?= number_format($f['monto'], 2) ?></td>
                    <td><?= htmlspecialchars($f['numero_comprobante_pago'] ?? '—') ?></td>
                    <td>
                        <a href="?controller=contabilidad&action=dashboard&buscar=<?= urlencode($f['numero_factura']) ?>" 
                           class="btn-small">Ver</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Enviar a SAP -->
<div id="modalEnviarSAP" class="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal('modalEnviarSAP')">&times;</span>
        <h2>📤 Enviar a SAP</h2>
        <p>Confirma el envío de esta factura al sistema SAP.</p>
        
        <input type="hidden" id="factura_id_sap" value="">
        
        <div class="form-group">
            <label>Número de Comprobante SAP (opcional):</label>
            <input type="text" id="comprobante_sap" class="form-control" placeholder="Ej: SAP-2024-001">
        </div>
        
        <div class="form-group">
            <label>Observaciones (opcional):</label>
            <textarea id="observaciones_sap" rows="3" class="form-control" placeholder="Agrega algún comentario..."></textarea>
        </div>
        
        <div style="margin-top:20px; text-align:right;">
            <button type="button" class="btn-secondary" onclick="cerrarModal('modalEnviarSAP')">Cancelar</button>
            <button type="button" class="btn-contabilidad" onclick="confirmarEnviarSAP()">✓ Confirmar Envío a SAP</button>
        </div>
    </div>
</div>

<!-- Modal Registrar Pago -->
<div id="modalRegistrarPago" class="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal('modalRegistrarPago')">&times;</span>
        <h2>💰 Registrar Pago</h2>
        <p>Factura: <strong id="factura_numero"></strong></p>
        
        <input type="hidden" id="factura_id_pago" value="">
        
        <div class="form-group">
            <label>Monto Pagado *:</label>
            <input type="number" id="monto_pagado" step="0.01" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label>Fecha de Pago *:</label>
            <input type="date" id="fecha_pago" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
        
        <div class="form-group">
            <label>Número de Comprobante *:</label>
            <input type="text" id="numero_comprobante" class="form-control" placeholder="Ej: TRANS-001, CHEQUE-123" required>
        </div>
        
        <div class="form-group">
            <label>Observaciones (opcional):</label>
            <textarea id="observaciones_pago" rows="3" class="form-control" placeholder="Agrega algún comentario..."></textarea>
        </div>
        
        <div style="margin-top:20px; text-align:right;">
            <button type="button" class="btn-secondary" onclick="cerrarModal('modalRegistrarPago')">Cancelar</button>
            <button type="button" class="btn-pagar" onclick="confirmarRegistrarPago()">💰 Confirmar Pago</button>
        </div>
    </div>
</div>

<script>
function mostrarModalRechazar(facturaId) {
    document.getElementById('factura_id_rechazar').value = facturaId;
    document.getElementById('motivo_rechazo').value = '';
    document.getElementById('modalRechazar').style.display = 'block';
}

function confirmarRechazar() {
    const facturaId = document.getElementById('factura_id_rechazar').value;
    const motivo = document.getElementById('motivo_rechazo').value.trim();
    
    if (!motivo) {
        alert('Debe ingresar un motivo de rechazo');
        document.getElementById('motivo_rechazo').focus();
        return;
    }
    
    if (confirm('⚠️ ¿Está seguro de RECHAZAR esta factura?\n\nLa contraseña se anulará y la(s) factura(s) SAT quedarán disponibles nuevamente para que el proveedor pueda utilizarlas.\n\nMotivo: ' + motivo)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.href;
        
        const inputId = document.createElement('input');
        inputId.type = 'hidden';
        inputId.name = 'factura_id';
        inputId.value = facturaId;
        form.appendChild(inputId);
        
        const inputMotivo = document.createElement('input');
        inputMotivo.type = 'hidden';
        inputMotivo.name = 'motivo_rechazo';
        inputMotivo.value = motivo;
        form.appendChild(inputMotivo);
        
        const inputAction = document.createElement('input');
        inputAction.type = 'hidden';
        inputAction.name = 'rechazar_factura';
        inputAction.value = '1';
        form.appendChild(inputAction);
        
        document.body.appendChild(form);
        form.submit();
    }
}

    function mostrarModalEnviarSAP(facturaId) {
        document.getElementById('factura_id_sap').value = facturaId;
        document.getElementById('comprobante_sap').value = '';
        document.getElementById('observaciones_sap').value = '';
        document.getElementById('modalEnviarSAP').style.display = 'block';
    }
    
    function confirmarEnviarSAP() {
        const facturaId = document.getElementById('factura_id_sap').value;
        const comprobante = document.getElementById('comprobante_sap').value;
        const observaciones = document.getElementById('observaciones_sap').value;
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.href;
        
        const inputId = document.createElement('input');
        inputId.type = 'hidden';
        inputId.name = 'factura_id';
        inputId.value = facturaId;
        form.appendChild(inputId);
        
        const inputComprobante = document.createElement('input');
        inputComprobante.type = 'hidden';
        inputComprobante.name = 'comprobante_sap';
        inputComprobante.value = comprobante;
        form.appendChild(inputComprobante);
        
        const inputObservaciones = document.createElement('input');
        inputObservaciones.type = 'hidden';
        inputObservaciones.name = 'observaciones';
        inputObservaciones.value = observaciones;
        form.appendChild(inputObservaciones);
        
        const inputAction = document.createElement('input');
        inputAction.type = 'hidden';
        inputAction.name = 'enviar_sap';
        inputAction.value = '1';
        form.appendChild(inputAction);
        
        document.body.appendChild(form);
        form.submit();
    }
    
    function mostrarModalRegistrarPago(facturaId, facturaNumero, monto) {
        document.getElementById('factura_id_pago').value = facturaId;
        document.getElementById('factura_numero').textContent = facturaNumero;
        document.getElementById('monto_pagado').value = monto;
        document.getElementById('fecha_pago').value = new Date().toISOString().split('T')[0];
        document.getElementById('numero_comprobante').value = '';
        document.getElementById('observaciones_pago').value = '';
        document.getElementById('modalRegistrarPago').style.display = 'block';
    }
    
    function confirmarRegistrarPago() {
        const facturaId = document.getElementById('factura_id_pago').value;
        const montoPagado = document.getElementById('monto_pagado').value;
        const fechaPago = document.getElementById('fecha_pago').value;
        const numeroComprobante = document.getElementById('numero_comprobante').value;
        const observaciones = document.getElementById('observaciones_pago').value;
        
        if (!numeroComprobante) {
            alert('Debe ingresar el número de comprobante de pago');
            return;
        }
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.href;
        
        const inputId = document.createElement('input');
        inputId.type = 'hidden';
        inputId.name = 'factura_id';
        inputId.value = facturaId;
        form.appendChild(inputId);
        
        const inputMonto = document.createElement('input');
        inputMonto.type = 'hidden';
        inputMonto.name = 'monto_pagado';
        inputMonto.value = montoPagado;
        form.appendChild(inputMonto);
        
        const inputFecha = document.createElement('input');
        inputFecha.type = 'hidden';
        inputFecha.name = 'fecha_pago';
        inputFecha.value = fechaPago;
        form.appendChild(inputFecha);
        
        const inputComprobante = document.createElement('input');
        inputComprobante.type = 'hidden';
        inputComprobante.name = 'numero_comprobante';
        inputComprobante.value = numeroComprobante;
        form.appendChild(inputComprobante);
        
        const inputObservaciones = document.createElement('input');
        inputObservaciones.type = 'hidden';
        inputObservaciones.name = 'observaciones';
        inputObservaciones.value = observaciones;
        form.appendChild(inputObservaciones);
        
        const inputAction = document.createElement('input');
        inputAction.type = 'hidden';
        inputAction.name = 'registrar_pago';
        inputAction.value = '1';
        form.appendChild(inputAction);
        
        document.body.appendChild(form);
        form.submit();
    }
    
    function cerrarModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }
</script>