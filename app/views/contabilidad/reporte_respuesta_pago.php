<?php
// app/views/contabilidad/reporte_respuesta_pago.php

// Definir funciones de formato seguro
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

function safeDateTimeFormat($date, $format = 'd/m/Y H:i')
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
<div class="reporte-container">
    <div class="reporte-header">
        <h1>📊 Reporte de Respuesta de Pago</h1>
        <p>Facturas autorizadas y cambios de fecha de pago por semana</p>
    </div>

    <!-- Filtro de semana -->
    <div class="filtro-reporte" style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 25px;">
        <form method="GET" class="form-filtro" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="controller" value="contabilidad">
            <input type="hidden" name="action" value="reporteRespuestaPago">

            <div class="form-group">
                <label>📅 Seleccionar Semana:</label>
                <select name="semana" id="semana_select" onchange="cargarFechasPorSemana()" style="padding: 8px 15px; border-radius: 5px; border: 1px solid #ddd;">
                    <?php foreach ($semanas_disponibles as $semana): ?>
                        <option value="<?= $semana['key'] ?>"
                            data-inicio="<?= $semana['inicio'] ?>"
                            data-fin="<?= $semana['fin'] ?>"
                            <?= ($semana_seleccionada == $semana['key']) ? 'selected' : '' ?>>
                            <?= $semana['label'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>📅 Desde:</label>
                <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?= $fecha_inicio ?>" style="padding: 8px; border-radius: 5px; border: 1px solid #ddd;">
            </div>

            <div class="form-group">
                <label>📅 Hasta:</label>
                <input type="date" name="fecha_fin" id="fecha_fin" value="<?= $fecha_fin ?>" style="padding: 8px; border-radius: 5px; border: 1px solid #ddd;">
            </div>

            <div class="form-group">
                <button type="submit" class="btn-primary" style="padding: 8px 25px; background: #1a237e; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    🔍 Buscar
                </button>
            </div>

            <div class="form-group">
                <button type="button" onclick="exportarExcel()" class="btn-success" style="background: #28a745; color: white; padding: 8px 25px; border: none; border-radius: 5px; cursor: pointer;">📊 Exportar Excel</button>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <a href="?controller=contabilidad&action=dashboard" class="btn-reporte" style="background: #1a237e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px;">
                    📊 Regresar
                </a>
            </div>
        </form>
    </div>

    <!-- Tarjetas de resumen -->
    <div class="resumen-cards" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px;">
        <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px;">
            <div style="font-size: 2rem; font-weight: bold;"><?= $resumen['total_autorizadas'] ?></div>
            <div>📋 Facturas Autorizadas</div>
            <small>En la semana seleccionada</small>
        </div>

        <div class="card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 10px;">
            <div style="font-size: 2rem; font-weight: bold;">Q <?= number_format($resumen['total_monto_autorizado'], 2) ?></div>
            <div>💰 Monto Autorizado</div>
            <small>Total aprobado</small>
        </div>

        <div class="card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 20px; border-radius: 10px;">
            <div style="font-size: 2rem; font-weight: bold;"><?= $resumen['total_pospuestas'] ?></div>
            <div>⏰ Facturas Pospuestas</div>
            <small>Cambiaron fecha de pago</small>
        </div>

        <div class="card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 10px;">
            <div style="font-size: 2rem; font-weight: bold;">Q <?= number_format($resumen['total_monto_pospuesto'], 2) ?></div>
            <div>💰 Monto Pospuesto</div>
            <small>Reprogramado</small>
        </div>
    </div>

    <!-- Tabla de Facturas Autorizadas -->
    <div class="seccion-reporte" style="margin-bottom: 30px;">
        <h2>✅ Facturas Autorizadas en la Semana</h2>
        <div class="table-container">
            <table class="data-table" id="tabla-autorizadas">
                <thead>
                    <tr>
                        <th>Fecha Autorización</th>
                        <th>Proveedor</th>
                        <th>NIT</th>
                        <th>Factura</th>
                        <th>Monto</th>
                        <th>Fecha Pago</th>
                        <th>Semana Pago</th>
                        <th>Tipo Aprobación</th>
                        <th>Comprobante</th>
                        <th>Autorizado por</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($facturas_autorizadas)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 40px;">No hay facturas autorizadas en este período</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($facturas_autorizadas as $factura): ?>
                            <tr>
                                <td><?= safeDateFormat($factura['fecha_aprobacion_finanzas'] ?? null) ?></td>
                                <td><strong><?= htmlspecialchars($factura['proveedor_nombre'] ?? 'N/A') ?></strong><br>
                                    <small>(<?= htmlspecialchars($factura['cardcode'] ?? 'N/A') ?>)</small>
                                </td>
                                <td><?= htmlspecialchars($factura['nit'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($factura['numero_factura'] ?? 'N/A') ?></td>
                                <td><strong>Q <?= number_format($factura['monto'] ?? 0, 2) ?></strong></td>
                                <td>
                                    <?php if (!empty($factura['fecha_pago_real'])): ?>
                                        ✅ <?= safeDateFormat($factura['fecha_pago_real']) ?>
                                    <?php else: ?>
                                        📅 <?= safeDateFormat($factura['fecha_pago_esperada'] ?? null) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $semana_pago = $factura['semana_pago'] ?? '';
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
                                    <?php
                                    $estado = $factura['estado'] ?? '';
                                    $badgeColor = '';
                                    $badgeText = '';
                                    switch ($estado) {
                                        case 'aprobado_para_pago':
                                            $badgeColor = '#28a745';
                                            $badgeText = '✅ Aprobado para Pago';
                                            break;
                                        case 'confirmacion_pago':
                                            $badgeColor = '#ffc107';
                                            $badgeText = '📅 Confirmación de Fecha';
                                            break;
                                        case 'pagada':
                                            $badgeColor = '#17a2b8';
                                            $badgeText = '💰 Pagada';
                                            break;
                                        default:
                                            $badgeColor = '#6c757d';
                                            $badgeText = ucfirst(str_replace('_', ' ', $estado));
                                    }
                                    ?>
                                    <span class="badge" style="background: <?= $badgeColor ?>;"><?= $badgeText ?></span>
                                </td>
                                <td><?= htmlspecialchars($factura['numero_comprobante_pago'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($factura['aprobado_por_finanzas'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tabla de Facturas Pospuestas -->
    <div class="seccion-reporte">
        <h2>⏰ Facturas que Pospusieron su Pago</h2>
        <div class="table-container">
            <table class="data-table" id="tabla-pospuestas">
                <thead>
                    <tr>
                        <th>Fecha Autorización</th>
                        <th>Proveedor</th>
                        <th>NIT</th>
                        <th>Factura</th>
                        <th>Monto</th>
                        <th>Fecha Pago Propuesta Original</th>
                        <th>Nueva Fecha de Pago</th>
                        <th>Días Diferencia</th>
                        <th>Tipo de Cambio</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($facturas_pospuestas)): ?>
                        <tr>
                            <td colspan="11" style="text-align: center; padding: 40px;">No hay facturas con cambios de fecha en este período</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($facturas_pospuestas as $factura): ?>
                            <?php
                            $fecha_original_display = '—';
                            $nueva_fecha_display = '—';
                            $dias_diferencia_display = '—';

                            // Obtener fecha original (priorizar fecha_anterior o fecha_pago_propuesta_original)
                            $fecha_original = null;
                            if (!empty($factura['fecha_anterior']) && $factura['fecha_anterior'] != 'Desconocida' && $factura['fecha_anterior'] != 'Fecha original no registrada') {
                                // Intentar convertir a fecha si es un string válido
                                $timestamp = strtotime($factura['fecha_anterior']);
                                if ($timestamp !== false && $timestamp > 0) {
                                    $fecha_original = $factura['fecha_anterior'];
                                    $fecha_original_display = safeDateFormat($fecha_original);
                                }
                            } elseif (!empty($factura['fecha_pago_propuesta_original']) && $factura['fecha_pago_propuesta_original'] != '0000-00-00') {
                                $fecha_original = $factura['fecha_pago_propuesta_original'];
                                $fecha_original_display = safeDateFormat($fecha_original);
                            } elseif (!empty($factura['fecha_pago_propuesta_anterior']) && $factura['fecha_pago_propuesta_anterior'] != '0000-00-00') {
                                $fecha_original = $factura['fecha_pago_propuesta_anterior'];
                                $fecha_original_display = safeDateFormat($fecha_original);
                            }

                            // Obtener nueva fecha
                            $fecha_nueva = null;
                            if (!empty($factura['nueva_fecha']) && $factura['nueva_fecha'] != 'Desconocida' && $factura['nueva_fecha'] != 'Fecha original no registrada') {
                                $timestamp = strtotime($factura['nueva_fecha']);
                                if ($timestamp !== false && $timestamp > 0) {
                                    $fecha_nueva = $factura['nueva_fecha'];
                                    $nueva_fecha_display = safeDateFormat($fecha_nueva);
                                }
                            } elseif (!empty($factura['fecha_pago_propuesta']) && $factura['fecha_pago_propuesta'] != '0000-00-00') {
                                $fecha_nueva = $factura['fecha_pago_propuesta'];
                                $nueva_fecha_display = safeDateFormat($fecha_nueva);
                            }

                            // Calcular diferencia en días (solo si ambas fechas son válidas)
                            if ($fecha_original && $fecha_nueva) {
                                try {
                                    $fecha_orig_obj = new DateTime($fecha_original);
                                    $fecha_nueva_obj = new DateTime($fecha_nueva);
                                    $diferencia = $fecha_orig_obj->diff($fecha_nueva_obj);
                                    $dias = $diferencia->days;
                                    if ($fecha_nueva_obj > $fecha_orig_obj) {
                                        $dias_diferencia_display = '⏩ +' . $dias . ' días';
                                    } elseif ($fecha_nueva_obj < $fecha_orig_obj) {
                                        $dias_diferencia_display = '⏪ -' . $dias . ' días';
                                    } else {
                                        $dias_diferencia_display = '📅 Mismo día';
                                    }
                                } catch (Exception $e) {
                                    $dias_diferencia_display = '—';
                                }
                            }

                            // Badge de estado
                            $estado_badge = '';
                            $estado_color = '';
                            switch ($factura['estado'] ?? '') {
                                case 'aprobado_para_pago':
                                    $estado_color = '#28a745';
                                    $estado_badge = '✅ Aprobado para Pago';
                                    break;
                                case 'confirmacion_pago':
                                    $estado_color = '#ffc107';
                                    $estado_badge = '📅 Confirmación de Fecha';
                                    break;
                                case 'pagada':
                                    $estado_color = '#17a2b8';
                                    $estado_badge = '💰 Pagada';
                                    break;
                                default:
                                    $estado_color = '#6c757d';
                                    $estado_badge = ucfirst(str_replace('_', ' ', $factura['estado'] ?? 'desconocido'));
                            }
                            ?>
                            <tr style="background: #fff8e1;">
                                <td><?= safeDateFormat($factura['fecha_aprobacion_finanzas'] ?? null) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($factura['proveedor_nombre'] ?? 'N/A') ?></strong><br>
                                    <small>(<?= htmlspecialchars($factura['cardcode'] ?? 'N/A') ?>)</small>
                                </td>
                                <td><?= htmlspecialchars($factura['nit'] ?? '—') ?></td>
                                <td>
                                    <?= htmlspecialchars($factura['numero_factura'] ?? 'N/A') ?><br>
                                    <small class="factura-link" style="cursor: pointer; color: #1a237e;" onclick="buscarFactura('<?= htmlspecialchars($factura['numero_factura'] ?? '') ?>')">
                                        🔍 Ver detalle
                                    </small>
                                </td>
                                <td><strong>Q <?= number_format($factura['monto'] ?? 0, 2) ?></strong></td>
                                <td>
                                    <span style="color: #dc3545; font-weight: bold;">📅 <?= $fecha_original_display ?></span>
                                </td>
                                <td>
                                    <span style="color: #28a745; font-weight: bold;">✅ <?= $nueva_fecha_display ?></span>
                                </td>
                                <td><?= $dias_diferencia_display ?></td>
                                <!-- AQUÍ VA EL NUEVO CÓDIGO - TIPO DE CAMBIO -->
                                <td>
                                    <?php
                                    $semana_pago = $factura['semana_pago'] ?? '';
                                    if ($semana_pago === 'fecha_personalizada'):
                                    ?>
                                        <span style="background: #2196f3; color: white; padding: 3px 10px; border-radius: 15px; font-size: 0.7rem; display: inline-block;">📅 Fecha Personalizada</span>
                                    <?php else: ?>
                                        <span style="background: #ff9800; color: white; padding: 3px 10px; border-radius: 15px; font-size: 0.7rem; display: inline-block;">⏰ Cambio de Fecha</span>
                                    <?php endif; ?>
                                </td>
                                <!-- FIN DEL CÓDIGO NUEVO -->
                                <td>
                                    <small><?= htmlspecialchars(substr($factura['motivo'] ?? 'No especificado', 0, 60)) ?></small>
                                </td>
                                <td>
                                    <span class="badge" style="background: <?= $estado_color ?>;"><?= $estado_badge ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>


</div>

<script>
    function cargarFechasPorSemana() {
        const select = document.getElementById('semana_select');
        const selectedOption = select.options[select.selectedIndex];
        const inicio = selectedOption.dataset.inicio;
        const fin = selectedOption.dataset.fin;

        if (inicio && fin) {
            document.getElementById('fecha_inicio').value = inicio;
            document.getElementById('fecha_fin').value = fin;
            // Auto-submit el formulario
            select.closest('form').submit();
        }
    }

    function exportarExcel() {
        // Obtener datos de ambas tablas
        const tablaAutorizadas = document.getElementById('tabla-autorizadas');
        const tablaPospuestas = document.getElementById('tabla-pospuestas');

        if (!tablaAutorizadas || !tablaPospuestas) {
            alert('No hay datos para exportar');
            return;
        }

        // Clonar las tablas para exportar
        const wb = XLSX.utils.book_new();

        // Convertir tabla de autorizadas a hoja de Excel
        const wsAutorizadas = XLSX.utils.table_to_sheet(tablaAutorizadas, {
            raw: true
        });
        XLSX.utils.book_append_sheet(wb, wsAutorizadas, 'Facturas Autorizadas');

        // Convertir tabla de pospuestas a hoja de Excel
        const wsPospuestas = XLSX.utils.table_to_sheet(tablaPospuestas, {
            raw: true
        });
        XLSX.utils.book_append_sheet(wb, wsPospuestas, 'Facturas Pospuestas');

        // Generar nombre de archivo con fecha
        const fechaInicio = document.getElementById('fecha_inicio').value;
        const fechaFin = document.getElementById('fecha_fin').value;
        const nombreArchivo = `Reporte_Respuesta_Pago_${fechaInicio}_al_${fechaFin}.xlsx`;

        // Descargar archivo
        XLSX.writeFile(wb, nombreArchivo);
    }

    function buscarFactura(numeroFactura) {
        // Redirigir al dashboard de contabilidad con la factura seleccionada
        window.location.href = 'index.php?controller=contabilidad&action=dashboard&buscar=' + encodeURIComponent(numeroFactura);
    }
</script>

<!-- Agregar SheetJS para exportar a Excel -->
<script src="https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js"></script>

<style>
    .reporte-container {
        padding: 20px;
    }

    .reporte-header {
        margin-bottom: 25px;
    }

    .reporte-header h1 {
        color: #1a237e;
        margin-bottom: 5px;
    }

    .reporte-header p {
        color: #666;
    }

    .badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 15px;
        font-size: 0.7rem;
        font-weight: bold;
        color: white;
    }

    .seccion-reporte {
        background: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .seccion-reporte h2 {
        margin-top: 0;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
        color: #1a237e;
    }

    .data-table td {
        padding: 10px 8px;
    }

    .btn-success {
        background: #28a745;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-success:hover {
        background: #218838;
    }

    @media print {

        .filtro-reporte,
        .btn-success {
            display: none;
        }

        .resumen-cards {
            break-inside: avoid;
        }

        .seccion-reporte {
            break-inside: avoid;
            page-break-inside: avoid;
        }
    }
</style>