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

<!-- Estadísticas de semanas -->
<div class="estadisticas-semanas" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px;">
    <div class="stats-card" style="background: #e3f2fd; padding: 15px; border-radius: 10px; text-align: center;">
        <div style="font-size: 1.8rem; font-weight: bold; color: #1565c0;"><?= $estadisticas_semanas['esta_semana'] ?? 0 ?></div>
        <div style="font-size: 0.85rem; color: #666;">📅 Facturas esta semana</div>
        <div style="font-size: 0.8rem; color: #1565c0;">Q <?= number_format($estadisticas_semanas['monto_esta_semana'] ?? 0, 2) ?></div>
    </div>
    <div class="stats-card" style="background: #fff3e0; padding: 15px; border-radius: 10px; text-align: center;">
        <div style="font-size: 1.8rem; font-weight: bold; color: #e65100;"><?= $estadisticas_semanas['proxima_semana'] ?? 0 ?></div>
        <div style="font-size: 0.85rem; color: #666;">📅 Facturas próxima semana</div>
        <div style="font-size: 0.8rem; color: #e65100;">Q <?= number_format($estadisticas_semanas['monto_proxima_semana'] ?? 0, 2) ?></div>
    </div>
    <div class="stats-card" style="background: #ffebee; padding: 15px; border-radius: 10px; text-align: center;">
        <div style="font-size: 1.8rem; font-weight: bold; color: #c62828;"><?= $estadisticas_semanas['atrasadas'] ?? 0 ?></div>
        <div style="font-size: 0.85rem; color: #666;">⚠️ Facturas atrasadas</div>
    </div>
    <div class="stats-card" style="background: #e8f5e9; padding: 15px; border-radius: 10px; text-align: center;">
        <div style="font-size: 1.8rem; font-weight: bold; color: #2e7d32;"><?= $estadisticas_semanas['futuras'] ?? 0 ?></div>
        <div style="font-size: 0.85rem; color: #666;">📌 Facturas futuras</div>
    </div>
</div>

<!-- Filtro de semanas -->
<div class="filtro-semanas" style="background: white; padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <div style="display: flex; gap: 10px;">
        <a href="?controller=finanzas&action=dashboard&filtro_semana=actual"
            class="filtro-btn <?= ($_GET['filtro_semana'] ?? 'actual') === 'actual' ? 'active' : '' ?>"
            style="padding: 8px 20px; border-radius: 25px; text-decoration: none; background: <?= ($_GET['filtro_semana'] ?? 'actual') === 'actual' ? '#006400' : '#e9ecef'; ?>; color: <?= ($_GET['filtro_semana'] ?? 'actual') === 'actual' ? 'white' : '#495057'; ?>;">
            📅 Esta Semana
        </a>
        <a href="?controller=finanzas&action=dashboard&filtro_semana=proxima"
            class="filtro-btn <?= ($_GET['filtro_semana'] ?? '') === 'proxima' ? 'active' : '' ?>"
            style="padding: 8px 20px; border-radius: 25px; text-decoration: none; background: <?= ($_GET['filtro_semana'] ?? '') === 'proxima' ? '#ff9800' : '#e9ecef'; ?>; color: <?= ($_GET['filtro_semana'] ?? '') === 'proxima' ? 'white' : '#495057'; ?>;">
            📅 Próxima Semana
        </a>
        <a href="?controller=finanzas&action=dashboard&filtro_semana=todas"
            class="filtro-btn <?= ($_GET['filtro_semana'] ?? '') === 'todas' ? 'active' : '' ?>"
            style="padding: 8px 20px; border-radius: 25px; text-decoration: none; background: <?= ($_GET['filtro_semana'] ?? '') === 'todas' ? '#17a2b8' : '#e9ecef'; ?>; color: <?= ($_GET['filtro_semana'] ?? '') === 'todas' ? 'white' : '#495057'; ?>;">
            📋 Todas
        </a>
    </div>

    <!-- Buscador -->
    <form method="GET" style="display: flex; gap: 10px;">
        <input type="hidden" name="controller" value="finanzas">
        <input type="hidden" name="action" value="dashboard">
        <input type="hidden" name="filtro_semana" value="<?= htmlspecialchars($_GET['filtro_semana'] ?? 'actual') ?>">
        <input type="text" name="buscar" placeholder="🔍 Buscar factura..." style="padding: 8px 15px; border-radius: 25px; border: 1px solid #ddd;">
        <button type="submit" class="btn-small">Buscar</button>
    </form>
</div>

<!-- Función helper para formatear fechas -->
<?php
function safeDateFormat($date, $format = 'd/m/Y')
{
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
    <div class="factura-detalle" style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 30px; border-left: 5px solid #1a237e;">
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
            <tr>
                <td><strong>Fecha de Pago:</strong></td>
                <td><strong style="color: #1a237e;">
                    <?= safeDateFormat($factura['fecha_pago_propuesta'] ?? $factura['fecha_pago_esperada'] ?? null) ?>
                </strong></td>
            </tr>
        </table>

        <?php if (!empty($factura['pdf_factura'])): ?>
            <div style="margin-top: 20px;">
                <h3>📎 Factura PDF</h3>
                <iframe src="index.php?controller=finanzas&action=descargarPDF&id=<?= $factura['id'] ?>&tipo=factura" style="width:100%; height:400px; border:1px solid #ddd;"></iframe>
            </div>
        <?php endif; ?>

        <div style="margin-top: 20px;">
            <a href="index.php?controller=finanzas&action=pdfContraseña&id=<?= $factura['id'] ?>" class="btn-small" target="_blank">📄 Ver Contraseña PDF</a>
        </div>

        <?php if (($factura['estado'] ?? '') === 'en_sap'): ?>
            <!-- Formulario para seleccionar fecha de pago (Finanzas ya no aprueba el pago directamente) -->
            <div style="margin-top: 20px; padding: 20px; background: #e8f0fe; border-radius: 10px; border-left: 5px solid #1a237e;">
                <h3>💰 Seleccionar Fecha de Pago</h3>
                <form method="POST" id="formAprobacion">
                    <input type="hidden" name="factura_id" value="<?= $factura['id'] ?>">

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="font-weight: bold;">Seleccionar fecha de pago *:</label>
                        <select name="semana_pago" id="semana_pago" onchange="toggleFechaCustom()" required style="padding: 10px; margin-left: 10px; border-radius: 5px; border: 1px solid #ccc;">
                            <?php
                            $fecha_esperada = $factura['fecha_pago_esperada'] ?? null;
                            // Viernes de la semana de fecha_pago_esperada
                            if ($fecha_esperada) {
                                $ts = strtotime($fecha_esperada);
                                $diaSemana = (int)date('N', $ts); // 1=Lun ... 5=Vie ... 7=Dom
                                $diasHastaViernes = (5 - $diaSemana + 7) % 7; // 0 si ya es viernes
                                $este_viernes_ts = strtotime("+{$diasHastaViernes} days", $ts);
                                $proximo_viernes_ts = strtotime('+7 days', $este_viernes_ts);
                            } else {
                                $este_viernes_ts = strtotime('this friday');
                                $proximo_viernes_ts = strtotime('next friday');
                            }
                            $este_viernes_str   = date('d/m/Y', $este_viernes_ts);
                            $proximo_viernes_str = date('d/m/Y', $proximo_viernes_ts);
                            $fecha_min_custom   = $fecha_esperada ? date('Y-m-d', $este_viernes_ts) : date('Y-m-d');
                            ?>
                            <option value="este_viernes" data-fecha="<?= date('Y-m-d', $este_viernes_ts) ?>">✅ Este Viernes (<?= $este_viernes_str ?>)</option>
                            <option value="proximo_viernes" data-fecha="<?= date('Y-m-d', $proximo_viernes_ts) ?>">📅 Próximo Viernes (<?= $proximo_viernes_str ?>)</option>
                            <option value="custom">📅 Fecha Personalizada</option>
                        </select>
                        <!-- Lleva exactamente la fecha mostrada arriba: el servidor la usa tal cual, sin recalcularla -->
                        <input type="hidden" name="fecha_pago_calculada" id="fecha_pago_calculada" value="<?= date('Y-m-d', $este_viernes_ts) ?>">
                    </div>

                    <div id="divFechaCustom" style="display: none; margin-bottom: 15px;">
                        <label>📅 Seleccionar Fecha de Pago Personalizada:</label>
                        <input type="date" name="fecha_pago_custom" id="fecha_pago_custom"
                            style="padding: 10px; border-radius: 5px; border: 1px solid #ccc; margin-left: 10px;">
                        <small style="display: block; margin-top: 5px; color: #e65100;">
                            ⚠️ Solo se permiten seleccionar <strong>viernes</strong>
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>Comentarios (opcional):</label>
                        <textarea name="comentarios" placeholder="Agregar comentario..." style="width: 100%; padding: 8px; border-radius: 5px; border: 1px solid #ccc;" rows="2"></textarea>
                    </div>

                    <div id="divMotivoRechazo" style="display: none; margin: 15px 0;">
                        <label>Motivo del rechazo *:</label>
                        <textarea name="motivo_rechazo" id="motivo_rechazo" rows="3" style="width: 100%; padding: 8px; border-radius: 5px; border: 1px solid #ccc;" placeholder="Especifique el motivo del rechazo..."></textarea>
                    </div>

                    <div style="display: flex; gap: 15px;">
                        <button type="submit" name="aprobar_factura" class="btn-finanzas-aprobar" style="padding: 10px 25px; background: #1a237e; color: white; border: none; border-radius: 5px; cursor: pointer;">
                            <span id="btnTexto">📅 Agregar Fecha</span>
                        </button>
                        <button type="button" class="btn-finanzas-rechazar" id="btnRechazar" style="padding: 10px 25px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;" onclick="mostrarMotivoRechazo()">❌ Rechazar</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Tabla de facturas pendientes -->
<h2>⏳ Facturas Pendientes de Autorización</h2>
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha de Pago</th>
                <th>Semana</th>
                <th>Proveedor</th>
                <th>Tipo</th>
                <th>Factura</th>
                <th>Monto</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($facturas_pendientes)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:40px;">No hay facturas pendientes de autorización para el período seleccionado</td>
                </tr>
            <?php else: ?>
                <?php foreach ($facturas_pendientes as $f): ?>
                    <tr style="<?= (!empty($f['fecha_pago_propuesta']) && strtotime($f['fecha_pago_propuesta']) < strtotime(date('Y-m-d'))) ? 'background: #ffebee;' : '' ?>">
                        <td><strong><?= safeDateFormat($f['fecha_pago_esperada'] ?? null) ?></strong></td>
                        <td>
                            <?php if ($f['esta_semana'] ?? false): ?>
                                <span class="badge-semana" style="background: #4caf50; color: white; padding: 3px 10px; border-radius: 15px; font-size: 0.7rem;">📅 Esta Semana</span>
                            <?php elseif ($f['proxima_semana'] ?? false): ?>
                                <span class="badge-semana" style="background: #ff9800; color: white; padding: 3px 10px; border-radius: 15px; font-size: 0.7rem;">📅 Próxima Semana</span>
                            <?php else: ?>
                                <span class="badge-semana" style="background: #9e9e9e; color: white; padding: 3px 10px; border-radius: 15px; font-size: 0.7rem;">📅 Otra fecha</span>
                            <?php endif; ?>
                        </td>
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
                        <td>
                            <a href="?controller=finanzas&action=dashboard&buscar=<?= urlencode($f['numero_factura'] ?? '') ?>&filtro_semana=<?= htmlspecialchars($_GET['filtro_semana'] ?? 'actual') ?>"
                                class="btn-small">Revisar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    function toggleFechaCustom() {
        const select = document.getElementById('semana_pago');
        const divCustom = document.getElementById('divFechaCustom');
        divCustom.style.display = select.value === 'custom' ? 'block' : 'none';

        // Copiar la fecha exacta mostrada en la opción elegida (data-fecha) al campo que se envía
        const selectedOption = select.options[select.selectedIndex];
        document.getElementById('fecha_pago_calculada').value = selectedOption.dataset.fecha || '';

        actualizarTextoBoton();
    }

    function actualizarTextoBoton() {
        const select = document.getElementById('semana_pago');
        const btnTexto = document.getElementById('btnTexto');

        if (!select || !btnTexto) return;

        if (select.value === 'custom') {
            btnTexto.innerHTML = '📅 Confirmar Fecha';
        } else if (select.value === 'este_viernes' || select.value === 'proximo_viernes') {
            btnTexto.innerHTML = '✅ Aprobar Pago';
        } else {
            btnTexto.innerHTML = '📅 Agregar Fecha';
        }
    }

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

    // Inicializar texto del botón y fecha oculta al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        const select = document.getElementById('semana_pago');
        if (select) {
            select.addEventListener('change', actualizarTextoBoton);
            toggleFechaCustom();
            actualizarTextoBoton();
        }
    });

    // Restringir fecha personalizada solo a viernes (día 5)
    document.addEventListener('DOMContentLoaded', function() {
        const inputFecha = document.getElementById('fecha_pago_custom');
        if (!inputFecha) return;

        // Establecer fecha mínima como hoy
        const hoy = new Date();
        inputFecha.min = '<?= $fecha_min_custom ?? date('Y-m-d') ?>';

        inputFecha.addEventListener('change', function() {
            const fechaSeleccionada = new Date(this.value + 'T00:00:00');
            const diaSemana = fechaSeleccionada.getDay(); // 0=Dom, 5=Vie

            if (diaSemana !== 5) {
                alert('⚠️ Solo puede seleccionar viernes como fecha de pago.');
                this.value = '';
            }
        });

        // Resaltar visualmente que solo viernes son válidos
        inputFecha.addEventListener('input', function() {
            const fechaSeleccionada = new Date(this.value + 'T00:00:00');
            const diaSemana = fechaSeleccionada.getDay();

            if (this.value && diaSemana !== 5) {
                this.style.borderColor = '#dc3545';
                this.style.background = '#ffebee';
            } else if (this.value) {
                this.style.borderColor = '#28a745';
                this.style.background = '#e8f5e9';
            } else {
                this.style.borderColor = '#ccc';
                this.style.background = 'white';
            }
        });
    });
</script>

<style>
    .badge-tipo {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: bold;
    }

    .tipo-transporte {
        background: #17a2b8;
        color: white;
    }

    .tipo-material {
        background: #ff9800;
        color: white;
    }

    .filtro-btn.active {
        background: #006400 !important;
        color: white !important;
    }

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
        transition: all 0.3s ease;
    }

    .btn-finanzas-aprobar:hover {
        background: #0d1b5e;
        transform: translateY(-1px);
    }

    .btn-finanzas-rechazar {
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-finanzas-rechazar:hover {
        background: #c82333;
    }

    .search-box {
        background: white;
        padding: 25px;
        border-radius: 10px;
        margin-bottom: 30px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
</style>