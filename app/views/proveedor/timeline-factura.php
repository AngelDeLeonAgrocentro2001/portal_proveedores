<?php
// app/views/proveedor/timeline-factura.php
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking de Factura - <?= htmlspecialchars($factura['numero_factura']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .tracking-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Header de la factura */
        .factura-header {
            background: white;
            border-radius: 16px;
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border-left: 5px solid #006400;
        }

        .factura-header h1 {
            color: #006400;
            font-size: 1.8rem;
            margin-bottom: 15px;
        }

        .factura-badges {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin: 15px 0;
        }

        .badge-info {
            background: #e8f5e9;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.9rem;
        }

        .badge-info strong {
            color: #006400;
        }

        .estado-actual {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .estado-reportada {
            background: #fff3cd;
            color: #856404;
        }

        .estado-revision_compras {
            background: #cce5ff;
            color: #004085;
        }

        .estado-aprobada_compras {
            background: #d4edda;
            color: #155724;
        }

        .estado-aprobada_finanzas {
            background: #d1ecf1;
            color: #0c5460;
        }

        .estado-en_sap {
            background: #e2f0fb;
            color: #0c63e4;
        }

        .estado-pagada {
            background: #28a745;
            color: white;
        }

        .estado-rechazada_compras,
        .estado-rechazada_contabilidad {
            background: #f8d7da;
            color: #721c24;
        }

        /* Timeline principal */
        .timeline {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .timeline-title {
            font-size: 1.4rem;
            margin-bottom: 25px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .timeline-title i {
            color: #006400;
            font-size: 1.6rem;
        }

        /* Estilos para cada paso */
        .timeline-step {
            display: flex;
            margin-bottom: 30px;
            position: relative;
        }

        .timeline-step:last-child {
            margin-bottom: 0;
        }

        .timeline-step:not(:last-child)::before {
            content: '';
            position: absolute;
            left: 24px;
            top: 50px;
            bottom: -35px;
            width: 2px;
            background: #e0e0e0;
            z-index: 1;
        }

        .timeline-step.completed:not(:last-child)::before {
            background: linear-gradient(to bottom, #006400, #e0e0e0);
        }

        /* Icono del paso */
        .step-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            position: relative;
            z-index: 2;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .step-icon.pending {
            background: #e9ecef;
            color: #adb5bd;
            border: 2px solid #dee2e6;
        }

        .step-icon.completed {
            background: #006400;
            color: white;
            box-shadow: 0 4px 10px rgba(0, 100, 0, 0.3);
        }

        .step-icon.rechazado {
            background: #dc3545;
            color: white;
            box-shadow: 0 4px 10px rgba(220, 53, 69, 0.3);
        }

        .step-icon.current {
            background: #ff9800;
            color: white;
            animation: pulse 2s infinite;
            box-shadow: 0 0 0 0 rgba(255, 152, 0, 0.7);
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 152, 0, 0.7);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(255, 152, 0, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(255, 152, 0, 0);
            }
        }

        /* Contenido del paso */
        .step-content {
            flex: 1;
            margin-left: 20px;
            padding-bottom: 10px;
        }

        .step-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .step-date {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 8px;
        }

        .step-description {
            color: #555;
            line-height: 1.5;
            margin-bottom: 8px;
        }

        .step-detail {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-top: 10px;
            border-left: 3px solid #006400;
        }

        .contrasena-display {
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            font-weight: bold;
            background: #e8f5e9;
            padding: 5px 12px;
            border-radius: 8px;
            display: inline-block;
            letter-spacing: 1px;
        }

        /* Tarjeta de información adicional */
        .info-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .info-card h3 {
            margin-bottom: 20px;
            color: #006400;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .documentos-list {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .documento-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            text-decoration: none;
            color: #006400;
            transition: all 0.3s;
        }

        .documento-link:hover {
            background: #006400;
            color: white;
            transform: translateY(-2px);
        }

        .comentario-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #006400;
        }

        .comentario-area {
            font-weight: 600;
            color: #006400;
            margin-bottom: 8px;
        }

        .btn-volver {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #6c757d;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .btn-volver:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        /* Progreso general */
        .progress-overall {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .progress-bar-container {
            background: #e9ecef;
            border-radius: 20px;
            height: 8px;
            overflow: hidden;
        }

        .progress-bar-fill {
            background: linear-gradient(90deg, #006400, #28a745);
            height: 100%;
            border-radius: 20px;
            transition: width 0.5s ease;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .timeline-step {
                flex-direction: column;
            }

            .step-icon {
                margin-bottom: 15px;
            }

            .step-content {
                margin-left: 0;
            }

            .timeline-step:not(:last-child)::before {
                left: 24px;
                top: 50px;
                bottom: -20px;
            }
        }
    </style>
</head>

<body>

    <div class="tracking-container">
        <!-- Header de la factura -->
        <div class="factura-header">
            <h1><i class="fas fa-chart-line"></i> Tracking de Factura</h1>
            <div class="factura-badges">
                <div class="badge-info"><strong>Factura:</strong> <?= htmlspecialchars($factura['numero_factura']) ?></div>
                <div class="badge-info"><strong>Monto:</strong> Q <?= number_format($factura['monto'], 2) ?></div>
                <div class="badge-info"><strong>Fecha Emisión:</strong> <?= date('d/m/Y', strtotime($factura['fecha_emision'])) ?></div>
                <div class="badge-info">
                    <strong>Estado Actual:</strong>
                    <span class="estado-actual estado-<?= $factura['estado'] ?>">
                        <?php
                        $estadosMap = [
                            'reportada' => '📋 Reportada',
                            'revision_compras' => '🔄 En Revisión (Compras)',
                            'aprobada_compras' => '✅ Aprobada por Compras',
                            'en_sap' => '📤 En SAP (Contabilidad)',
                            'aprobada_finanzas' => '💰 Aprobada por Finanzas',
                            'pagada' => '💵 Pagada',
                            'rechazada_compras' => '❌ Rechazada por Compras',
                            'rechazada_contabilidad' => '❌ Rechazada por Contabilidad'
                        ];
                        echo $estadosMap[$factura['estado']] ?? $factura['estado'];
                        ?>
                    </span>
                </div>
            </div>

            <?php if (!empty($factura['fecha_pago_esperada']) && $factura['estado'] !== 'pagada'): ?>
                <div class="badge-info" style="background: #fff3cd; color: #856404;">
                    <strong>📅 Fecha Estimada de Pago:</strong> <?= date('d/m/Y', strtotime($factura['fecha_pago_esperada'])) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Timeline de estados -->
        <div class="timeline">
            <div class="timeline-title">
                <i class="fas fa-road"></i>
                Línea de Tiempo del Proceso
            </div>

            <!-- Barra de progreso general -->
            <?php
            $totalPasos = count($timeline);
            $completados = 0;
            foreach ($timeline as $step) {
                if ($step['completado']) $completados++;
            }
            $porcentaje = ($completados / max($totalPasos, 1)) * 100;
            ?>
            <div class="progress-overall">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span><i class="fas fa-chart-simple"></i> Progreso general</span>
                    <span><strong><?= round($porcentaje) ?>%</strong></span>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" style="width: <?= $porcentaje ?>%"></div>
                </div>
            </div>

            <div style="margin-top: 30px;">
                <?php foreach ($timeline as $index => $step): ?>
                    <?php
                    $isCompleted = $step['completado'];
                    $isCurrent = !$isCompleted && ($index === 0 || $timeline[$index - 1]['completado'] ?? false);
                    $isRechazo = isset($step['es_rechazo']) && $step['es_rechazo'] === true;

                    $iconClass = 'pending';
                    if ($isRechazo) $iconClass = 'rechazado';
                    elseif ($isCompleted) $iconClass = 'completed';
                    elseif ($isCurrent) $iconClass = 'current';
                    ?>

                    <div class="timeline-step <?= $isCompleted ? 'completed' : '' ?>">
                        <div class="step-icon <?= $iconClass ?>">
                            <i class="fas <?= $step['icono'] ?>"></i>
                        </div>
                        <div class="step-content">
                            <div class="step-title">
                                <?= $step['titulo'] ?>
                                <?php if ($isCurrent && !$isRechazo): ?>
                                    <span style="background: #ff9800; color: white; padding: 2px 10px; border-radius: 20px; font-size: 0.7rem;">
                                        <i class="fas fa-hourglass-half"></i> En proceso
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="step-date">
                                <?php if (!empty($step['fecha'])): ?>
                                    <i class="far fa-calendar-alt"></i> <?= date('d/m/Y H:i', strtotime($step['fecha'])) ?>
                                <?php else: ?>
                                    <i class="far fa-clock"></i> Pendiente
                                <?php endif; ?>
                            </div>
                            <div class="step-description">
                                <?= $step['descripcion'] ?>
                            </div>

                            <!-- CORREGIDO: Verificar que 'detalle' existe y no está vacío -->
                            <?php if (isset($step['detalle']) && !empty($step['detalle'])): ?>
                                <div class="step-detail">
                                    <i class="fas fa-info-circle"></i> <?= $step['detalle'] ?>
                                </div>
                            <?php endif; ?>

                            <!-- CORREGIDO: Usar condición más segura -->
                            <?php if (($step['estado'] ?? '') === 'contrasena_generada' && !empty($step['detalle'] ?? '')): ?>
                                <div style="margin-top: 10px;">
                                    <span class="contrasena-display">
                                        <i class="fas fa-key"></i> <?= htmlspecialchars($step['detalle']) ?>
                                    </span>
                                    <a href="index.php?controller=proveedor&action=pdfContraseña&id=<?= $factura['id'] ?>"
                                        class="documento-link" style="margin-left: 10px; padding: 5px 12px;">
                                        <i class="fas fa-download"></i> Descargar PDF
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Documentos adjuntos -->
        <?php if (!empty($documentos)): ?>
            <div class="info-card">
                <h3><i class="fas fa-paperclip"></i> Documentos Subidos por Administración</h3>
                <div class="documentos-list">
                    <?php foreach ($documentos as $doc): ?>
                        <a href="index.php?controller=admin&action=descargarDocumento&id=<?= $doc['id'] ?>"
                            class="documento-link" target="_blank">
                            <i class="fas fa-file-pdf"></i>
                            <?= htmlspecialchars($doc['nombre_original']) ?>
                            <small style="color: #999;">(<?= date('d/m/Y', strtotime($doc['fecha_subida'])) ?>)</small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Comentarios del proceso -->
        <?php if (!empty($comentarios)): ?>
            <div class="info-card">
                <h3><i class="fas fa-comments"></i> Comentarios del Proceso</h3>
                <?php foreach ($comentarios as $area => $comentario): ?>
                    <div class="comentario-item">
                        <div class="comentario-area">
                            <i class="fas fa-building"></i> Área de <?= $area ?>
                        </div>
                        <div><?= $comentario ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Botón volver -->
        <a href="index.php?controller=proveedor&action=misFacturas" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver a Mis Facturas
        </a>
    </div>

</body>

</html>