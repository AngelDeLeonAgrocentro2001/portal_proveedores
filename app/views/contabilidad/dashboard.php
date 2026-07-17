<?php
// app/views/contabilidad/dashboard.php - VERSIÓN CON ENLACE AL REPORTE
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1 style="margin: 0;">📋 Gestión de Pagos a Proveedores</h1>
    <a href="?controller=contabilidad&action=reporteRespuestaPago" class="btn-reporte" style="background: #1a237e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px;">
        📊 Reporte Respuesta de Pago
    </a>
</div>

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

<!-- Función helper para formatear fechas de forma segura -->
<?php 
function safeDateFormat($date, $format = 'd/m/Y') {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '—';
    }
    $timestamp = strtotime($date);
    if ($timestamp === false || $timestamp <= 0) {
        return '—';
    }
    return date($format, $timestamp);
}

function safeDateTimeFormat($date, $format = 'd/m/Y H:i') {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '—';
    }
    $timestamp = strtotime($date);
    if ($timestamp === false || $timestamp <= 0) {
        return '—';
    }
    return date($format, $timestamp);
}
?>

<!-- Detalle de factura encontrada -->
<?php if (isset($factura) && $factura): ?>
<div class="factura-detalle" id="factura-detalle">
    <h2>📄 Detalle de Factura</h2>
    <table style="width:100%">
        <tr>
            <td width="150"><strong>Proveedor:</strong></td>
            <td><?= htmlspecialchars($factura['proveedor_nombre'] ?? 'N/A') ?> (<?= htmlspecialchars($factura['cardcode'] ?? 'N/A') ?>)</td>
        </tr>
        <tr>
            <td><strong>Factura:</strong></td>
            <td><?= htmlspecialchars($factura['numero_factura'] ?? 'N/A') ?></td>
        </tr>
        <tr>
            <td><strong>Monto:</strong></td>
            <td>Q <?= number_format($factura['monto'] ?? 0, 2) ?></td>
        </tr>
        <tr>
            <td><strong>Estado actual:</strong></td>
            <td><span class="status <?= $factura['estado'] ?? '' ?>"><?= ucfirst(str_replace('_', ' ', $factura['estado'] ?? 'desconocido')) ?></span></td>
        </tr>
        <?php if (!empty($factura['fecha_pago_esperada'])): ?>
        <tr>
            <td><strong>Fecha Pago Propuesta:</strong></td>
            <td><strong style="color: #00695c;"><?= safeDateFormat($factura['fecha_pago_esperada']) ?></strong></td>
        </tr>
        <?php endif; ?>
        
        <?php if (($factura['estado'] ?? '') === 'en_sap'): ?>
        <tr>
            <td><strong>Fecha Envío SAP:</strong></td>
            <td><?= safeDateTimeFormat($factura['fecha_envio_sap'] ?? null) ?> por <?= htmlspecialchars($factura['enviado_por'] ?? 'N/A') ?></td>
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
        
        <?php if (($factura['estado'] ?? '') === 'aprobada_compras'): ?>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button onclick="mostrarModalEnviarSAP(<?= $factura['id'] ?>)" class="btn-contabilidad">📤 Enviar a SAP</button>
                <button onclick="mostrarModalRechazar(<?= $factura['id'] ?>)" class="btn-rechazar" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">❌ Rechazar</button>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- TIPO DE FACTURA Y RETENCIONES -->
    <div style="margin-top: 20px; padding: 15px; background: #e8f5e9; border-radius: 8px; border-left: 4px solid #43a047;">
        <h3 style="color: #2e7d32; margin-bottom: 15px;">🧾 Tipo de Factura y Retenciones</h3>
        <form method="POST" id="formTipoRetenciones">
            <input type="hidden" name="factura_id" value="<?= $factura['id'] ?>">
            <input type="hidden" name="guardar_tipo_retenciones" value="1">

            <div style="margin-bottom: 15px;">
                <strong>Tipo de factura:</strong>
                <div style="margin-top: 8px; display: flex; gap: 30px;">
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                        <input type="radio" name="tipo_factura" value="contribuyente_normal"
                            <?= ($factura['tipo_factura'] ?? '') === 'contribuyente_normal' ? 'checked' : '' ?>
                            onchange="mostrarRetenciones(this.value)">
                        <span>Contribuyente Normal</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                        <input type="radio" name="tipo_factura" value="pequeno_contribuyente"
                            <?= ($factura['tipo_factura'] ?? '') === 'pequeno_contribuyente' ? 'checked' : '' ?>
                            onchange="mostrarRetenciones(this.value)">
                        <span>Pequeño Contribuyente</span>
                    </label>
                </div>
            </div>

            <?php
            $retencionesGuardadas = json_decode($factura['retenciones_seleccionadas'] ?? '[]', true) ?: [];
            $tipoActual = $factura['tipo_factura'] ?? '';
            ?>

            <!-- Retenciones Contribuyente Normal -->
            <div id="retenciones_normal" style="display:<?= $tipoActual === 'contribuyente_normal' ? 'block' : 'none' ?>; margin-bottom: 15px;">
                <strong>Retenciones aplicables:</strong>
                <div style="margin-top: 8px; display: grid; grid-template-columns: 1fr 1fr; gap: 6px;">
                    <?php
                    $opcionesNormal = [
                        'sin_retencion'         => 'Sin Retención',
                        'retencion_iva_65'       => 'Retención IVA 65%',
                        'retencion_iva_15'       => 'Retención IVA 15%',
                        'retencion_isr_5'        => 'Retención ISR 5% (primeros Q30,000)',
                        'retencion_isr_7'        => 'Retención ISR 7% (mayor a Q30,000)',
                        'isr_no_domiciliados_5'  => 'ISR No Domiciliados 5%',
                        'retencion_definitiva'   => 'Retención Definitiva ISR',
                        'combustible_idp'        => 'Combustible / IDP',
                        'timbres_fiscales'       => 'Timbres Fiscales 0.05%',
                        'inguat_10'              => 'INGUAT 10%',
                    ];
                    foreach ($opcionesNormal as $val => $label): ?>
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; padding:4px 8px; background:#f1f8e9; border-radius:4px;">
                        <input type="checkbox" name="retenciones[]" value="<?= $val ?>"
                            <?= in_array($val, $retencionesGuardadas) ? 'checked' : '' ?>>
                        <span style="font-size:0.9rem;"><?= $label ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Retenciones Pequeño Contribuyente -->
            <div id="retenciones_pequeno" style="display:<?= $tipoActual === 'pequeno_contribuyente' ? 'block' : 'none' ?>; margin-bottom: 15px;">
                <strong>Retenciones aplicables:</strong>
                <div style="margin-top: 8px; display: grid; grid-template-columns: 1fr 1fr; gap: 6px;">
                    <?php
                    $opcionesPequeno = [
                        'sin_retencion'     => 'Sin Retención',
                        'retencion_iva_5'   => 'Retención IVA 5% Pequeño Contribuyente',
                        'inguat_10'         => 'INGUAT 10%',
                        'timbres_fiscales'  => 'Timbres Fiscales 0.05%',
                    ];
                    foreach ($opcionesPequeno as $val => $label): ?>
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; padding:4px 8px; background:#f1f8e9; border-radius:4px;">
                        <input type="checkbox" name="retenciones[]" value="<?= $val ?>"
                            <?= in_array($val, $retencionesGuardadas) ? 'checked' : '' ?>>
                        <span style="font-size:0.9rem;"><?= $label ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" style="background:#43a047; color:white; border:none; padding:8px 20px; border-radius:5px; cursor:pointer; font-size:0.95rem;">
                💾 Guardar tipo y retenciones
            </button>

            <?php if (!empty($factura['tipo_factura'])): ?>
            <span style="margin-left:12px; color:#2e7d32; font-size:0.85rem;">
                ✔ Guardado: <?= $factura['tipo_factura'] === 'pequeno_contribuyente' ? 'Pequeño Contribuyente' : 'Contribuyente Normal' ?>
                <?php if (!empty($retencionesGuardadas)): ?>
                — <?= implode(', ', array_map(fn($r) => str_replace('_', ' ', $r), $retencionesGuardadas)) ?>
                <?php endif; ?>
            </span>
            <?php endif; ?>
        </form>
    </div>

    <!-- SECCIÓN DE RETENCIONES -->
    <div style="margin-top: 20px; padding: 15px; background: #fef9e6; border-radius: 8px; border-left: 4px solid #ff9800;">
        <h3 style="color: #e65100; margin-bottom: 15px;">📎 Documentos de Retención</h3>
        
        <div style="display: flex; gap: 30px; flex-wrap: wrap;">
            <!-- Retención IVA -->
            <div style="flex: 1; min-width: 200px;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <span style="font-weight: bold;">💰 Retención IVA:</span>
                    <?php if (!empty($factura['pdf_retencion_iva'])): ?>
                        <a href="index.php?controller=contabilidad&action=descargarRetencionIVA&id=<?= $factura['id'] ?>" 
                           class="btn-small" target="_blank" style="background: #ff9800; color: white;">
                            📄 Ver PDF
                        </a>
                    <?php endif; ?>
                </div>
                <form method="POST" enctype="multipart/form-data" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="factura_id" value="<?= $factura['id'] ?>">
                    <input type="file" name="pdf_retencion_iva" accept=".pdf" style="flex: 1; padding: 5px;">
                    <button type="submit" name="subir_retencion_iva" class="btn-small" style="background: #ff9800; color: white; border: none; padding: 6px 15px; border-radius: 5px; cursor: pointer;">
                        📤 Subir IVA
                    </button>
                </form>
                <small style="color: #666;">Solo PDF - La retención de IVA es opcional</small>
            </div>
            
            <!-- Retención ISR -->
            <div style="flex: 1; min-width: 200px;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <span style="font-weight: bold;">💰 Retención ISR:</span>
                    <?php if (!empty($factura['pdf_retencion_isr'])): ?>
                        <a href="index.php?controller=contabilidad&action=descargarRetencionISR&id=<?= $factura['id'] ?>" 
                           class="btn-small" target="_blank" style="background: #ff9800; color: white;">
                            📄 Ver PDF
                        </a>
                    <?php endif; ?>
                </div>
                <form method="POST" enctype="multipart/form-data" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="factura_id" value="<?= $factura['id'] ?>">
                    <input type="file" name="pdf_retencion_isr" accept=".pdf" style="flex: 1; padding: 5px;">
                    <button type="submit" name="subir_retencion_isr" class="btn-small" style="background: #ff9800; color: white; border: none; padding: 6px 15px; border-radius: 5px; cursor: pointer;">
                        📤 Subir ISR
                    </button>
                </form>
                <small style="color: #666;">Solo PDF - La retención de ISR es opcional</small>
            </div>
        </div>
        
        <?php if (!empty($factura['fecha_subida_retenciones'])): ?>
        <div style="margin-top: 10px; font-size: 0.75rem; color: #999;">
            📅 Documentos subidos: <?= date('d/m/Y H:i', strtotime($factura['fecha_subida_retenciones'])) ?> por <?= htmlspecialchars($factura['usuario_retenciones'] ?? '') ?>
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
                <th>Fecha Aprobación Compras</th>
                <th>Proveedor</th>
                <th>Tipo</th>
                <th>Factura</th>
                <th>Monto</th>
                <th>Fecha de Pago</th>
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
                    <td><?= safeDateFormat($f['fecha_aprobacion_compras'] ?? null) ?></td>
                    <td><?= htmlspecialchars(substr($f['proveedor_nombre'] ?? '', 0, 30)) ?></td>
                    <td>
                        <?php if (($f['tipo_proveedor'] ?? '') === 'transporte'): ?>
                            <span class="badge-tipo tipo-transporte">🚚 Transporte</span>
                        <?php elseif (($f['tipo_proveedor'] ?? '') === 'material_empaque'): ?>
                            <span class="badge-tipo tipo-material">📦 Material</span>
                        <?php else: ?>
                            <?= $f['tipo_proveedor'] ?? 'Normal' ?>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($f['numero_factura'] ?? 'N/A') ?></strong></td>
                    <td>Q <?= number_format($f['monto'] ?? 0, 2) ?></td>
                    <td><strong><?= safeDateFormat($f['fecha_pago_esperada'] ?? null) ?></strong></td>
                    <td>
                        <a href="?controller=contabilidad&action=dashboard&buscar=<?= urlencode($f['numero_factura'] ?? '') ?>" 
                           class="btn-small">Revisar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Facturas Enviadas a SAP (Pendientes de autorización de Finanzas) -->
<h2>📤 Facturas en SAP (Pendientes Autorización Finanzas)</h2>
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha Envío SAP</th>
                <th>Proveedor</th>
                <th>Factura</th>
                <th>Monto</th>
                <th>Fecha de Pago</th>
                <th>Comprobante SAP</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($facturas_en_sap)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:40px;">No hay facturas en SAP pendientes de autorización</td>
                </tr>
            <?php else: ?>
                <?php foreach ($facturas_en_sap as $f): ?>
                <tr>
                    <td><?= safeDateFormat($f['fecha_envio_sap'] ?? null) ?></td>
                    <td><?= htmlspecialchars(substr($f['proveedor_nombre'] ?? '', 0, 30)) ?></td>
                    <td><strong><?= htmlspecialchars($f['numero_factura'] ?? 'N/A') ?></strong></td>
                    <td>Q <?= number_format($f['monto'] ?? 0, 2) ?></td>
                    <td><?= safeDateFormat($f['fecha_pago_propuesta'] ?? null) ?></td>
                    <td><?= htmlspecialchars($f['comprobante_sap'] ?? '—') ?></td>
                    <td>
                        <a href="?controller=finanzas&action=dashboard&buscar=<?= urlencode($f['numero_factura'] ?? '') ?>" 
                           class="btn-small">Ir a Finanzas</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<h2>💰 Facturas Aprobadas para Pago</h2>
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha Aprobación Finanzas</th>
                <th>Proveedor</th>
                <th>Factura</th>
                <th>Monto</th>
                <th>Fecha Pago Propuesta</th>
                <th>Semana Pago</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            // Obtener facturas en estado aprobado_para_pago
            $facturas_aprobadas_pago = $this->getFacturasAprobadasPago();
            ?>
            <?php if (empty($facturas_aprobadas_pago)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:40px;">No hay facturas aprobadas para pago pendientes</td>
                </tr>
            <?php else: ?>
                <?php foreach ($facturas_aprobadas_pago as $f): ?>
                <tr>
                    <td><?= safeDateFormat($f['fecha_aprobacion_finanzas'] ?? null) ?></td>
                    <td><?= htmlspecialchars(substr($f['proveedor_nombre'] ?? '', 0, 30)) ?></td>
                    <td><strong><?= htmlspecialchars($f['numero_factura'] ?? 'N/A') ?></strong></td>
                    <td>Q <?= number_format($f['monto'] ?? 0, 2) ?></td>
                    <td><strong><?= safeDateFormat($f['fecha_pago_esperada'] ?? null) ?></strong></td>
                    <td>
                        <?php
                        $semana_pago = $f['semana_pago'] ?? '';
                        switch ($semana_pago) {
                            case 'este_viernes':
                                echo '<span class="badge" style="background: #4caf50;">Este Viernes</span>';
                                break;
                            case 'proximo_viernes':
                                echo '<span class="badge" style="background: #ff9800;">Próximo Viernes</span>';
                                break;
                            case 'fecha_personalizada':
                                echo '<span class="badge" style="background: #2196f3;">Fecha Personalizada</span>';
                                break;
                            default:
                                echo '<span class="badge" style="background: #9e9e9e;">' . htmlspecialchars($semana_pago) . '</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <button onclick="mostrarModalAprobarPago(<?= $f['id'] ?>, '<?= htmlspecialchars($f['numero_factura']) ?>', <?= $f['monto'] ?>, '<?= $f['fecha_pago_esperada'] ?? '' ?>')"
                                class="btn-aprobar-pago" style="background: #28a745; color: white; border: none; padding: 5px 12px; border-radius: 5px; cursor: pointer;">
                            💰 Registrar Pago
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Aprobar Pago (Registrar Pago) -->
<div id="modalAprobarPago" class="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal('modalAprobarPago')">&times;</span>
        <h2>💰 Registrar Pago</h2>
        <p>Confirme el registro del pago para esta factura.</p>
        
        <input type="hidden" id="pago_factura_id" value="">
        
        <div class="form-group">
            <label>Factura:</label>
            <input type="text" id="pago_factura_numero" class="form-control" readonly disabled style="background: #f5f5f5;">
        </div>
        
        <div class="form-group">
            <label>Monto a Pagar *:</label>
            <input type="number" id="pago_monto" step="0.01" class="form-control" required style="padding: 8px; border-radius: 5px; border: 1px solid #ddd;">
        </div>
        
        <div class="form-group">
            <label>Fecha de Pago *:</label>
            <input type="date" id="pago_fecha" value="<?= date('Y-m-d') ?>" class="form-control" required style="padding: 8px; border-radius: 5px; border: 1px solid #ddd;">
        </div>
        
        <div class="form-group">
            <label>Número de Comprobante *:</label>
            <input type="text" id="pago_comprobante" class="form-control" required placeholder="Ej: TRANS-001, CHEQUE-123, PAGO-001" style="padding: 8px; border-radius: 5px; border: 1px solid #ddd;">
        </div>
        
        <div class="form-group">
            <label>Observaciones (opcional):</label>
            <textarea id="pago_observaciones" rows="3" class="form-control" placeholder="Agrega algún comentario..."></textarea>
        </div>
        
        <div style="margin-top:20px; text-align:right;">
            <button type="button" class="btn-secondary" onclick="cerrarModal('modalAprobarPago')">Cancelar</button>
            <button type="button" class="btn-aprobar-pago" onclick="confirmarAprobarPago()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">✅ Confirmar Pago</button>
        </div>
    </div>
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
                    <td><?= safeDateFormat($f['fecha_pago_real'] ?? null) ?></td>
                    <td><?= htmlspecialchars(substr($f['proveedor_nombre'] ?? '', 0, 30)) ?></td>
                    <td><strong><?= htmlspecialchars($f['numero_factura'] ?? 'N/A') ?></strong></td>
                    <td>Q <?= number_format($f['monto'] ?? 0, 2) ?></td>
                    <td><?= htmlspecialchars($f['numero_comprobante_pago'] ?? '—') ?></td>
                    <td>
                        <a href="?controller=contabilidad&action=dashboard&buscar=<?= urlencode($f['numero_factura'] ?? '') ?>" 
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

<script>
    function mostrarRetenciones(tipo) {
        document.getElementById('retenciones_normal').style.display = (tipo === 'contribuyente_normal') ? 'block' : 'none';
        document.getElementById('retenciones_pequeno').style.display = (tipo === 'pequeno_contribuyente') ? 'block' : 'none';
        // Desmarcar todos al cambiar tipo
        document.querySelectorAll('#formTipoRetenciones input[type=checkbox]').forEach(cb => cb.checked = false);
    }

    function mostrarModalAprobarPago(facturaId, facturaNumero, monto,fechaPago) {
    document.getElementById('pago_factura_id').value = facturaId;
    document.getElementById('pago_factura_numero').value = facturaNumero;
    document.getElementById('pago_monto').value = monto;
    document.getElementById('pago_fecha').value = fechaPago || new Date().toISOString().slice(0,10);
    document.getElementById('pago_comprobante').value = '';
    document.getElementById('pago_observaciones').value = '';
    document.getElementById('modalAprobarPago').style.display = 'block';
}

function confirmarAprobarPago() {
    const facturaId = document.getElementById('pago_factura_id').value;
    const monto = document.getElementById('pago_monto').value;
    const fecha = document.getElementById('pago_fecha').value;
    const comprobante = document.getElementById('pago_comprobante').value.trim();
    const observaciones = document.getElementById('pago_observaciones').value;
    
    if (!comprobante) {
        alert('Debe ingresar el número de comprobante');
        document.getElementById('pago_comprobante').focus();
        return;
    }
    
    if (monto <= 0) {
        alert('Debe ingresar un monto válido');
        return;
    }
    
    if (!confirm('¿Confirmar el registro de pago para esta factura?\n\nComprobante: ' + comprobante + '\nMonto: Q ' + parseFloat(monto).toFixed(2))) {
        return;
    }
    
    const btn = event.target;
    const originalText = btn.textContent;
    btn.textContent = '⏳ Procesando...';
    btn.disabled = true;
    
    fetch('index.php?controller=contabilidad&action=aprobarPago', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `factura_id=${facturaId}&numero_comprobante=${encodeURIComponent(comprobante)}&fecha_pago=${fecha}&monto_pagado=${monto}&observaciones=${encodeURIComponent(observaciones)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('❌ Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error de conexión');
    })
    .finally(() => {
        btn.textContent = originalText;
        btn.disabled = false;
        cerrarModal('modalAprobarPago');
    });
}
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
    
    if (!confirm('¿Confirmar envío de esta factura a SAP?\n\nLa factura quedará registrada en el sistema SAP y pasará a autorización de Finanzas.')) {
        return;
    }
    
    // Mostrar loading
    const btn = event.target;
    const originalText = btn.textContent;
    btn.textContent = '⏳ Enviando...';
    btn.disabled = true;
    
    // Usar AJAX para enviar a SAP
    fetch('index.php?controller=contabilidad&action=enviarSAP', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `factura_id=${facturaId}&comprobante_sap=${encodeURIComponent(comprobante)}&observaciones=${encodeURIComponent(observaciones)}`
    })
    .then(response => response.json())
    .then(data => {
        // ========== MOSTRAR JSON EN CONSOLA ==========
        console.log('=== JSON ENVIADO A SAP ===');
        console.log(JSON.stringify(data.payload, null, 2));
        console.log('==========================');
        
        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('❌ Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error de conexión al enviar a SAP');
    })
    .finally(() => {
        btn.textContent = originalText;
        btn.disabled = false;
        cerrarModal('modalEnviarSAP');
    });
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

<style>
.btn-reporte {
    transition: all 0.3s ease;
}

.btn-reporte:hover {
    background: #0d1b5e !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-aprobar-pago {
    transition: all 0.3s ease;
}
.btn-aprobar-pago:hover {
    background: #218838 !important;
    transform: translateY(-1px);
}
.badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 15px;
    font-size: 0.7rem;
    font-weight: bold;
    color: white;
}
</style>