<?php
// app/views/finanzas/dashboard.php
?>
<h1>📋 Autorización de Pagos</h1>

<?php if (!empty($error)): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Buscador -->
<div class="search-box">
    <h2>🔍 Buscar Factura</h2>
    <form method="GET" style="display: flex; gap: 10px;">
        <input type="hidden" name="controller" value="finanzas">
        <input type="hidden" name="action" value="dashboard">
        <input type="text" name="buscar" placeholder="Número de factura..."
            style="flex:1;" value="<?= htmlspecialchars($_GET['buscar'] ?? '') ?>">
        <button type="submit" class="btn-primary" style="width: auto;">Buscar</button>
    </form>
</div>

<!-- Detalle de factura encontrada -->
<?php if (isset($factura) && $factura): ?>
<div class="factura-detalle">
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
        <?php if ($factura['estado'] === 'aprobada_compras'): ?>
        <tr>
    <td><strong>Acción:</strong></td>
    <td>
        <form method="POST" style="display: inline-block;" id="formAprobacion">
            <input type="hidden" name="factura_id" value="<?= $factura['id'] ?>">
            <select name="semana_pago" required style="padding: 8px; margin-right: 10px;">
                <option value="">-- Seleccionar semana de pago --</option>
                <option value="este_viernes">✅ Este Viernes (<?= date('d/m/Y', strtotime('this friday')) ?>)</option>
                <option value="proximo_viernes">📅 Próximo Viernes (<?= date('d/m/Y', strtotime('next friday')) ?>)</option>
            </select>
            <textarea name="comentarios" placeholder="Comentarios (opcional)" style="width: 100%; margin: 10px 0; padding: 8px;" rows="2"></textarea>
            
            <!-- Campo para motivo de rechazo (inicialmente oculto) -->
            <div id="divMotivoRechazo" style="display: none; margin: 10px 0;">
                <label>Motivo del rechazo *:</label>
                <textarea name="motivo_rechazo" id="motivo_rechazo" rows="3" style="width: 100%; padding: 8px;" placeholder="Especifique el motivo del rechazo..."></textarea>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" name="aprobar_factura" class="btn-finanzas-aprobar" style="padding: 10px 20px;">✅ Aprobar Pago</button>
                <button type="button" name="rechazar_factura" class="btn-finanzas-rechazar" id="btnRechazar" style="padding: 10px 20px;" onclick="mostrarMotivoRechazo()">❌ Rechazar</button>
            </div>
        </form>
    </td>
</tr>
        <?php elseif ($factura['estado'] === 'aprobada_finanzas'): ?>
        <tr>
            <td><strong>Fecha Pago Propuesta:</strong></td>
            <td><strong style="color: #1a237e;"><?= date('d/m/Y', strtotime($factura['fecha_pago_propuesta'])) ?></strong></td>
        </tr>
        <tr>
            <td><strong>Aprobado por Finanzas:</strong></td>
            <td><?= htmlspecialchars($factura['aprobado_por_finanzas']) ?> el <?= date('d/m/Y H:i', strtotime($factura['fecha_aprobacion_finanzas'])) ?></td>
        </tr>
        <?php endif; ?>
    </table>
    
    <?php if (!empty($factura['pdf_factura'])): ?>
    <div style="margin-top: 20px;">
        <h3>📎 Factura PDF</h3>
        <iframe src="index.php?controller=finanzas&action=descargarPDF&id=<?= $factura['id'] ?>&tipo=factura" 
                style="width:100%; height:400px; border:1px solid #ddd;"></iframe>
    </div>
    <?php endif; ?>
    
    <div style="margin-top: 20px;">
        <a href="index.php?controller=finanzas&action=pdfContraseña&id=<?= $factura['id'] ?>" class="btn-small" target="_blank">📄 Ver Contraseña PDF</a>
    </div>
</div>
<?php endif; ?>

<!-- Facturas Pendientes de Finanzas -->
<h2>⏳ Facturas Pendientes de Autorización Financiera</h2>
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha Reporte</th>
                <th>Proveedor</th>
                <th>Tipo</th>
                <th>Factura</th>
                <th>Monto</th>
                <th>Aprobado Compras</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($facturas_pendientes)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:40px;">No hay facturas pendientes de autorización financiera</td>
                </tr>
            <?php else: ?>
                <?php foreach ($facturas_pendientes as $f): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($f['fecha_emision'])) ?></td>
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
                    <td>
                        <?= htmlspecialchars($f['aprobado_por_compras'] ?? 'N/A') ?><br>
                        <small><?= $f['fecha_aprobacion_compras'] ? date('d/m/Y', strtotime($f['fecha_aprobacion_compras'])) : '' ?></small>
                    </td>
                    <td>
                        <a href="?controller=finanzas&action=dashboard&buscar=<?= urlencode($f['numero_factura']) ?>" 
                           class="btn-small">Revisar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Facturas ya Aprobadas por Finanzas -->
<h2>✅ Facturas Aprobadas por Finanzas (Pendientes Contabilidad)</h2>
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha Aprobación</th>
                <th>Proveedor</th>
                <th>Factura</th>
                <th>Monto</th>
                <th>Fecha Pago Propuesta</th>
                <th>Aprobado por</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($facturas_aprobadas)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:40px;">No hay facturas aprobadas por Finanzas</td>
                </tr>
            <?php else: ?>
                <?php foreach ($facturas_aprobadas as $f): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($f['fecha_aprobacion_finanzas'])) ?></td>
                    <td><?= htmlspecialchars(substr($f['proveedor_nombre'], 0, 30)) ?></td>
                    <td><strong><?= htmlspecialchars($f['numero_factura']) ?></strong></td>
                    <td>Q <?= number_format($f['monto'], 2) ?></td>
                    <td><strong class="semana-este badge-semana"><?= date('d/m/Y', strtotime($f['fecha_pago_propuesta'])) ?></strong></td>
                    <td><?= htmlspecialchars($f['aprobado_por_finanzas']) ?></td>
                    <td>
                        <a href="?controller=finanzas&action=dashboard&buscar=<?= urlencode($f['numero_factura']) ?>" 
                           class="btn-small">Ver</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>


<script>
function mostrarMotivoRechazo() {
    const divMotivo = document.getElementById('divMotivoRechazo');
    const btnRechazar = document.getElementById('btnRechazar');
    
    if (divMotivo.style.display === 'none') {
        divMotivo.style.display = 'block';
        btnRechazar.textContent = '❌ Confirmar Rechazo';
        btnRechazar.style.background = '#c82333';
    } else {
        const motivo = document.getElementById('motivo_rechazo').value.trim();
        if (!motivo) {
            alert('Debe ingresar un motivo de rechazo');
            return;
        }
        
        if (confirm('¿Está seguro de RECHAZAR esta factura? La contraseña se anulará y la factura SAT quedará disponible nuevamente.')) {
            const form = document.getElementById('formAprobacion');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'rechazar_factura';
            input.value = '1';
            form.appendChild(input);
            form.submit();
        }
    }
}
</script>

<style>
.badge-tipo {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: bold;
}
.tipo-transporte { background: #17a2b8; color: white; }
.tipo-material { background: #ff9800; color: white; }
.factura-detalle {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
    border-left: 5px solid #1a237e;
}
.btn-finanzas-aprobar {
    background: #1a237e;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}
.btn-finanzas-rechazar {
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}
.search-box {
    background: white;
    padding: 25px;
    border-radius: 10px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
</style>