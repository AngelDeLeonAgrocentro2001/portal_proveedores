<?php
// app/views/admin/gestionar-contrasenas.php - VERSIÓN COMPLETA CON AUTORIZACIÓN DE PROVEEDORES
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agrosistemas - Gestión de Contraseñas y Autorizaciones</title>
    <link rel="stylesheet" href="/portal_proveedores/public/assets/css/style.css">
    <style>
        .admin-header {
            background: #1a1a2e;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .search-box {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .factura-detalle {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 5px solid #006400;
        }

        .contrasena-actual {
            font-family: monospace;
            font-size: 1.2rem;
            background: #e9ecef;
            padding: 10px;
            border-radius: 5px;
        }

        .contrasena-cancelada {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            border-left: 4px solid #dc3545;
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 0.85rem;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-success {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-warning {
            background: #ff9800;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
        }

        .table-container {
            overflow-x: auto;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .badge-cancelada {
            background: #f8d7da;
            color: #721c24;
        }

        .documentos-list {
            margin-top: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
        }

        .documento-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 70%;
            max-width: 800px;
        }

        .ordenes-list {
            max-height: 400px;
            overflow-y: auto;
            margin: 20px 0;
        }

        .checkbox-label {
            display: block;
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #eee;
            border-radius: 6px;
            cursor: pointer;
        }

        .checkbox-label:hover {
            background: #f0f8f0;
        }

        .acciones-compras {
            margin-top: 25px;
            padding: 20px;
            background: #e8f4f8;
            border-radius: 8px;
            border-left: 5px solid #17a2b8;
        }

        /* Estilos para proveedores pendientes */
        .proveedores-pendientes {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .tipo-transporte { border-left: 5px solid #17a2b8; }
        .tipo-material { border-left: 5px solid #ff9800; }
        
        .badge-tipo {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .tipo-transporte-badge { background: #17a2b8; color: white; }
        .tipo-material-badge { background: #ff9800; color: white; }
        
        .filtro-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        
        .tab-btn {
            padding: 8px 20px;
            background: #f0f0f0;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            background: #006400;
            color: white;
        }
        
        .proveedor-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .proveedor-info {
            flex: 1;
        }
        
        .proveedor-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-sm {
            padding: 5px 12px;
            font-size: 0.85rem;
        }
        
        .documentos-link {
            color: #006400;
            text-decoration: none;
            margin-left: 15px;
            font-size: 0.85rem;
        }
        
        .estatus-pendiente { background: #fff3cd; color: #856404; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; display: inline-block; margin-left: 10px; }
    </style>
</head>

<body>
    <div class="admin-header">
    <h1>🏢 Agrosistemas - Gestión de Documentos y Contraseñas</h1>
    <div>
        <?php if (isset($usuario_info) && $usuario_info['es_supervisor']): ?>
            <span style="margin-right: 15px;">
                👤 <?= htmlspecialchars($usuario_info['nombre']) ?> | 
                Área: <?= $usuario_info['tipo'] === 'transporte' ? '🚚 Transporte' : '📦 Material/Empaque' ?>
            </span>
        <?php elseif (isset($usuario_info) && $usuario_info['es_global']): ?>
            <span style="margin-right: 15px;">
                👑 Admin Global | IP: <?= $_SERVER['REMOTE_ADDR'] ?>
            </span>
        <?php else: ?>
            <span>IP: <?= $_SERVER['REMOTE_ADDR'] ?></span>
        <?php endif; ?>
        <a href="index.php?controller=auth&action=logout" style="color:white; margin-left:20px;">🚪 Cerrar Sesión</a>
    </div>
</div>

    <div class="admin-container">
        <?php if (!empty($error)): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- ==================== SECCIÓN: AUTORIZACIÓN DE PROVEEDORES ==================== -->
        <?php if (!empty($proveedores_pendientes)): ?>
        <div class="proveedores-pendientes">
            <h2>📋 Proveedores Pendientes de Autorización por Tipo</h2>
            
            <div class="filtro-tabs">
                <button class="tab-btn active" onclick="filtrarProveedores('todos')">Todos</button>
                <button class="tab-btn" onclick="filtrarProveedores('transporte')">🚚 Transporte</button>
                <button class="tab-btn" onclick="filtrarProveedores('material_empaque')">📦 Material Empaque</button>
            </div>
            
            <div id="listaProveedores">
                <?php foreach ($proveedores_pendientes as $prov): ?>
                    <?php 
                    $tipoClass = ($prov['tipo_proveedor'] == 'transporte') ? 'tipo-transporte' : 'tipo-material';
                    $tipoBadge = ($prov['tipo_proveedor'] == 'transporte') ? 'tipo-transporte-badge' : 'tipo-material-badge';
                    $tipoNombre = ($prov['tipo_proveedor'] == 'transporte') ? '🚚 Transporte' : '📦 Material Empaque';
                    ?>
                    <div class="proveedor-card <?= $tipoClass ?>" data-tipo="<?= $prov['tipo_proveedor'] ?>">
                        <div class="proveedor-info">
                            <div>
                                <strong><?= htmlspecialchars($prov['nombre']) ?></strong>
                                <span class="badge-tipo <?= $tipoBadge ?>"><?= $tipoNombre ?></span>
                                <span class="estatus-pendiente">Pendiente</span>
                            </div>
                            <div style="font-size:0.85rem; color:#666; margin-top:5px;">
                                Código: <?= htmlspecialchars($prov['cardcode']) ?> | NIT: <?= htmlspecialchars($prov['nit']) ?>
                            </div>
                            <div style="font-size:0.8rem; margin-top:5px;">
                                <a href="#" onclick="verDocumentosProveedor(<?= $prov['id'] ?>)" class="documentos-link">📄 Ver Documentos</a>
                                <?php if (!empty($prov['pdf_rtu'])): ?>
                                    | <a href="/portal_proveedores/<?= $prov['pdf_rtu'] ?>" target="_blank" class="documentos-link">RTU</a>
                                <?php endif; ?>
                                <?php if (!empty($prov['pdf_patente'])): ?>
                                    | <a href="/portal_proveedores/<?= $prov['pdf_patente'] ?>" target="_blank" class="documentos-link">Patente</a>
                                <?php endif; ?>
                                <?php if (!empty($prov['pdf_cedula'])): ?>
                                    | <a href="/portal_proveedores/<?= $prov['pdf_cedula'] ?>" target="_blank" class="documentos-link">Cédula</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="proveedor-actions">
                            <button class="btn-success btn-sm" onclick="abrirModalAprobarProveedor(<?= $prov['id'] ?>, '<?= htmlspecialchars($prov['nombre']) ?>', '<?= $prov['tipo_proveedor'] ?>')">
                                ✅ Aprobar
                            </button>
                            <button class="btn-danger btn-sm" onclick="abrirModalRechazarProveedor(<?= $prov['id'] ?>, '<?= htmlspecialchars($prov['nombre']) ?>')">
                                ❌ Rechazar
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Buscador -->
        <div class="search-box">
            <h2>🔍 Buscar Factura</h2>
            <form method="GET" style="display: flex; gap: 10px;">
                <input type="hidden" name="controller" value="admin">
                <input type="hidden" name="action" value="gestionarContraseñas">
                <input type="text" name="buscar" placeholder="Número de factura..."
                    style="flex:1;" value="<?= htmlspecialchars($_GET['buscar'] ?? '') ?>">
                <button type="submit" class="btn-primary" style="width: auto;">Buscar</button>
            </form>
        </div>

        <!-- Detalle de factura encontrada -->
        <?php if ($factura): ?>
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
                        <td><strong>Estado:</strong></td>
                        <td>
                            <span class="status <?= $factura['estado'] ?>">
                                <?= ucfirst(str_replace('_', ' ', $factura['estado'])) ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Contraseña:</strong></td>
                        <td>
                            <?php if (!empty($factura['contrasena_pago'])): ?>
                                <div class="contrasena-actual" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                    <strong><?= htmlspecialchars($factura['contrasena_pago']) ?></strong>
                                    <a href="index.php?controller=admin&action=pdfContraseña&id=<?= $factura['id'] ?>"
                                        class="btn-small" target="_blank">
                                        📄 Generar PDF
                                    </a>
                                </div>
                            <?php else: ?>
                                <span style="color:#999;">No generada</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Órdenes de Compra:</strong></td>
                        <td>
                            <?php
                            $ordenesActuales = json_decode($factura['ordenes_relacionadas'] ?? '[]', true);
                            if (!empty($ordenesActuales)) {
                                echo implode(', ', array_map('htmlspecialchars', $ordenesActuales));
                            } else {
                                echo '<span style="color:#999;">No definidas</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php if (!empty($factura['fecha_inicio_credito'])): ?>
                    <tr>
                        <td><strong>Fecha Inicio Crédito:</strong></td>
                        <td><?= date('d/m/Y', strtotime($factura['fecha_inicio_credito'])) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($factura['fecha_pago_esperada'])): ?>
                    <tr>
                        <td><strong>Fecha Pago Esperada:</strong></td>
                        <td><?= date('d/m/Y', strtotime($factura['fecha_pago_esperada'])) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>

                <!-- Visualizar PDF de Factura -->
                <?php if (!empty($factura['pdf_factura'])): ?>
                    <div style="margin-top: 20px;">
                        <h3>📎 Factura PDF</h3>
                        <iframe src="/portal_proveedores/<?= $factura['pdf_factura'] ?>" 
                                style="width:100%; height:500px; border:1px solid #ddd;" frameborder="0">
                        </iframe>
                    </div>
                <?php endif; ?>

                <!-- ==================== ACCIONES DE COMPRAS ==================== -->
                <?php if (in_array($factura['estado'], ['reportada', 'rechazada_compras', 'revision_compras'])): ?>
                    <div class="acciones-compras">
                        <h3>🛒 Acciones de Compras</h3>
                        
                        <?php if ($factura['estado'] == 'rechazada_compras'): ?>
                            <div class="alert error" style="margin-bottom: 15px;">
                                <strong>⚠️ Esta factura fue rechazada anteriormente</strong><br>
                                Motivo: <?= htmlspecialchars($factura['motivo_rechazo'] ?? 'No especificado') ?><br>
                                Fecha: <?= date('d/m/Y H:i', strtotime($factura['fecha_rechazo'] ?? 'now')) ?>
                            </div>
                        <?php endif; ?>

                        <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px;">
                            <button type="button" class="btn-info" onclick="abrirModalCambiarOC(<?= $factura['id'] ?>, '<?= htmlspecialchars($factura['cardcode']) ?>')">
                                📝 Cambiar Orden de Compra
                            </button>
                            <button type="button" class="btn-success" onclick="abrirModalAprobar(<?= $factura['id'] ?>)">
                                ✅ Aceptar Factura
                            </button>
                            <button type="button" class="btn-danger" onclick="abrirModalRechazar(<?= $factura['id'] ?>)">
                                ❌ Rechazar Factura
                            </button>
                        </div>
                    </div>
                <?php elseif ($factura['estado'] == 'aprobada_compras'): ?>
                    <div class="alert success" style="margin-top: 20px;">
                        ✅ Factura aprobada por Compras el <?= date('d/m/Y H:i', strtotime($factura['fecha_aprobacion_compras'])) ?><br>
                        Comentarios: <?= htmlspecialchars($factura['comentarios_compras'] ?? 'Sin comentarios') ?>
                    </div>
                <?php elseif ($factura['estado'] == 'rechazada_compras'): ?>
                    <div class="alert error" style="margin-top: 20px;">
                        ❌ Factura rechazada por Compras el <?= date('d/m/Y H:i', strtotime($factura['fecha_rechazo'])) ?><br>
                        Motivo: <?= htmlspecialchars($factura['motivo_rechazo'] ?? 'No especificado') ?>
                    </div>
                <?php endif; ?>

                <!-- Botón para cancelar contraseña manual -->
                <?php if (!empty($factura['contrasena_pago']) && empty($factura['contrasena_cancelada']) && !in_array($factura['estado'], ['aprobada_compras', 'en_sap', 'pagada'])): ?>
                    <div style="margin-top: 25px; padding: 15px; background: #fff3cd; border-radius: 8px;">
                        <h3 style="color: #856404;">⚠️ Cancelar Contraseña (Manual)</h3>
                        <form method="POST" onsubmit="return confirm('¿Estás seguro de cancelar esta contraseña?')">
                            <input type="hidden" name="factura_id" value="<?= $factura['id'] ?>">
                            <div class="form-group">
                                <label>Motivo de cancelación *</label>
                                <textarea name="motivo_cancelacion" required style="width:100%; padding:8px;" rows="3"></textarea>
                            </div>
                            <button type="submit" name="cancelar_contrasena" class="btn-danger">❌ Cancelar Contraseña</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Últimas facturas reportadas -->
        <h2>📋 Últimas Facturas Reportadas</h2>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha Reporte</th>
                        <th>Proveedor</th>
                        <th>Factura</th>
                        <th>Monto</th>
                        <th>Estado</th>
                        <th>Contraseña</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ultimas_facturas)): ?>
                        <tr>
                            <td colspan="7">No hay facturas pendientes</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ultimas_facturas as $f): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($f['fecha_emision'])) ?></td>
                                <td><?= htmlspecialchars(substr($f['proveedor_nombre'], 0, 30)) ?></td>
                                <td><strong><?= htmlspecialchars($f['numero_factura']) ?></strong></td>
                                <td>Q <?= number_format($f['monto'], 2) ?></td>
                                <td><span class="status <?= $f['estado'] ?>"><?= ucfirst(str_replace('_', ' ', $f['estado'])) ?></span></td>
                                <td>
                                    <?php if (!empty($f['contrasena_pago']) && empty($f['contrasena_cancelada'])): ?>
                                        <span class="contrasena-actual" style="font-size:0.85rem;">
                                            <?= htmlspecialchars($f['contrasena_pago']) ?>
                                        </span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?controller=admin&action=gestionarContraseñas&buscar=<?= urlencode($f['numero_factura']) ?>"
                                        class="btn-small">Ver/Gestionar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ==================== MODALES FACTURAS ==================== -->

    <!-- Modal Cambiar Orden de Compra -->
    <div id="modalCambiarOC" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalCambiarOC')">&times;</span>
            <h2>📝 Cambiar Orden de Compra</h2>
            <p>Selecciona una nueva orden de compra:</p>
            
            <div id="ordenesDisponibles" class="ordenes-list">
                <p>Cargando órdenes disponibles...</p>
            </div>
            
            <div class="form-group" style="margin-top: 20px;">
                <label>Comentarios (opcional):</label>
                <textarea id="comentariosOC" rows="3" style="width:100%; padding:8px;" placeholder="Motivo del cambio..."></textarea>
            </div>
            
            <div style="margin-top:20px; text-align:right;">
                <button type="button" class="btn-secondary" onclick="cerrarModal('modalCambiarOC')">Cancelar</button>
                <button type="button" class="btn-primary" onclick="guardarCambioOrden()">Guardar Cambios</button>
            </div>
        </div>
    </div>

    <!-- Modal Aprobar Factura -->
    <div id="modalAprobar" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalAprobar')">&times;</span>
            <h2>✅ Aprobar Factura</h2>
            <p>¿Confirmas que esta factura es correcta y procede al área de Finanzas?</p>
            
            <div class="form-group">
                <label>Comentarios (opcional):</label>
                <textarea id="comentariosAprobar" rows="3" style="width:100%; padding:8px;" placeholder="Agrega algún comentario..."></textarea>
            </div>
            
            <div style="margin-top:20px; text-align:right;">
                <button type="button" class="btn-secondary" onclick="cerrarModal('modalAprobar')">Cancelar</button>
                <button type="button" class="btn-success" onclick="aprobarFactura()">✓ Aprobar</button>
            </div>
        </div>
    </div>

    <!-- Modal Rechazar Factura -->
    <div id="modalRechazar" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalRechazar')">&times;</span>
            <h2>❌ Rechazar Factura</h2>
            <p>Esta acción anulará la contraseña y liberará la factura SAT para que el proveedor pueda volver a usarla.</p>
            
            <div class="form-group">
                <label>Motivo del rechazo *:</label>
                <textarea id="motivoRechazo" rows="4" style="width:100%; padding:8px;" required placeholder="Ej: La orden de compra no corresponde, faltan documentos, etc."></textarea>
            </div>
            
            <div style="margin-top:20px; text-align:right;">
                <button type="button" class="btn-secondary" onclick="cerrarModal('modalRechazar')">Cancelar</button>
                <button type="button" class="btn-danger" onclick="rechazarFactura()">❌ Rechazar Factura</button>
            </div>
        </div>
    </div>

    <!-- ==================== MODALES PROVEEDORES ==================== -->
    
    <!-- Modal Aprobar Proveedor -->
    <div id="modalAprobarProveedor" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalAprobarProveedor')">&times;</span>
            <h2>✅ Aprobar Proveedor</h2>
            <p id="proveedorInfo"></p>
            <p>Al aprobar, el proveedor podrá reportar facturas en el portal.</p>
            
            <div class="form-group">
                <label>Observaciones (opcional):</label>
                <textarea id="observacionesAprobacion" rows="3" style="width:100%; padding:8px;" placeholder="Agrega algún comentario..."></textarea>
            </div>
            
            <div style="margin-top:20px; text-align:right;">
                <button type="button" class="btn-secondary" onclick="cerrarModal('modalAprobarProveedor')">Cancelar</button>
                <button type="button" class="btn-success" onclick="confirmarAprobarProveedor()">✓ Aprobar Proveedor</button>
            </div>
        </div>
    </div>

    <!-- Modal Rechazar Proveedor -->
    <div id="modalRechazarProveedor" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalRechazarProveedor')">&times;</span>
            <h2>❌ Rechazar Proveedor</h2>
            <p id="proveedorRechazoInfo"></p>
            
            <div class="form-group">
                <label>Motivo del rechazo *:</label>
                <textarea id="motivoRechazoProveedor" rows="4" style="width:100%; padding:8px;" required placeholder="Especifique el motivo del rechazo..."></textarea>
            </div>
            
            <div style="margin-top:20px; text-align:right;">
                <button type="button" class="btn-secondary" onclick="cerrarModal('modalRechazarProveedor')">Cancelar</button>
                <button type="button" class="btn-danger" onclick="confirmarRechazarProveedor()">❌ Rechazar Proveedor</button>
            </div>
        </div>
    </div>

    <script>
        let facturaActualId = null;
        let cardcodeActual = null;
        let proveedorActualId = null;
        let proveedorActualTipo = null;

        // ==================== FILTRAR PROVEEDORES ====================
        function filtrarProveedores(tipo) {
            const cards = document.querySelectorAll('.proveedor-card');
            const tabs = document.querySelectorAll('.tab-btn');
            
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            cards.forEach(card => {
                if (tipo === 'todos') {
                    card.style.display = 'flex';
                } else {
                    const cardTipo = card.getAttribute('data-tipo');
                    if (cardTipo === tipo) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                }
            });
        }

        // ==================== MODALES PROVEEDORES ====================
        function abrirModalAprobarProveedor(id, nombre, tipo) {
            proveedorActualId = id;
            proveedorActualTipo = tipo;
            document.getElementById('proveedorInfo').innerHTML = `
                <strong>${escapeHtml(nombre)}</strong><br>
                Tipo: ${tipo === 'transporte' ? '🚚 Transporte' : '📦 Material Empaque'}
            `;
            document.getElementById('observacionesAprobacion').value = '';
            document.getElementById('modalAprobarProveedor').style.display = 'block';
        }
        
        async function confirmarAprobarProveedor() {
            const observaciones = document.getElementById('observacionesAprobacion').value;
            
            if (!confirm(`¿Confirmas APROBAR a este proveedor como ${proveedorActualTipo === 'transporte' ? 'TRANSPORTE' : 'MATERIAL EMPAQUE'}?`)) {
                return;
            }
            
            try {
                const response = await fetch('index.php?controller=admin&action=aprobarProveedorCompras', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `proveedor_id=${proveedorActualId}&observaciones=${encodeURIComponent(observaciones)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✅ Proveedor aprobado correctamente. Pasa al área de Finanzas.');
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al aprobar el proveedor');
            }
        }
        
        function abrirModalRechazarProveedor(id, nombre) {
            proveedorActualId = id;
            document.getElementById('proveedorRechazoInfo').innerHTML = `<strong>${escapeHtml(nombre)}</strong>`;
            document.getElementById('motivoRechazoProveedor').value = '';
            document.getElementById('modalRechazarProveedor').style.display = 'block';
        }
        
        async function confirmarRechazarProveedor() {
            const motivo = document.getElementById('motivoRechazoProveedor').value.trim();
            
            if (!motivo) {
                alert('Debes ingresar un motivo para el rechazo');
                return;
            }
            
            if (!confirm('¿Estás seguro de RECHAZAR a este proveedor?')) {
                return;
            }
            
            try {
                const response = await fetch('index.php?controller=admin&action=rechazarProveedorCompras', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `proveedor_id=${proveedorActualId}&motivo=${encodeURIComponent(motivo)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('❌ Proveedor rechazado.');
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al rechazar el proveedor');
            }
        }
        
        function verDocumentosProveedor(id) {
            alert('Funcionalidad de visualización de documentos en desarrollo');
        }

        // ==================== FUNCIONES FACTURAS ====================
        function abrirModalCambiarOC(facturaId, cardcode) {
            facturaActualId = facturaId;
            cardcodeActual = cardcode;
            
            document.getElementById('modalCambiarOC').style.display = 'block';
            cargarOrdenesDisponibles(cardcode);
        }

        async function cargarOrdenesDisponibles(cardcode) {
            const container = document.getElementById('ordenesDisponibles');
            container.innerHTML = '<p>Cargando órdenes de compra disponibles...</p>';
            
            try {
                const response = await fetch(`index.php?controller=admin&action=getOrdenesDisponibles&cardcode=${encodeURIComponent(cardcode)}`);
                const data = await response.json();
                
                if (!data.success) {
                    container.innerHTML = `<p class="error">Error: ${data.message}</p>`;
                    return;
                }
                
                if (data.ordenes.length === 0) {
                    container.innerHTML = '<p>No hay órdenes de compra abiertas disponibles para este proveedor.</p>';
                    return;
                }
                
                let html = '';
                data.ordenes.forEach(oc => {
                    html += `
                        <label class="checkbox-label">
                            <input type="radio" name="nueva_orden" value="${oc.docentry}" data-numero="${oc.numero_oc}">
                            <strong>${oc.numero_oc}</strong> - Q ${parseFloat(oc.monto).toFixed(2)} 
                            (${oc.fecha ? new Date(oc.fecha).toLocaleDateString() : 'Fecha no disponible'})
                        </label>
                    `;
                });
                
                container.innerHTML = html;
            } catch (error) {
                console.error('Error:', error);
                container.innerHTML = '<p class="error">Error al cargar las órdenes de compra</p>';
            }
        }

        async function guardarCambioOrden() {
            const selectedRadio = document.querySelector('input[name="nueva_orden"]:checked');
            if (!selectedRadio) {
                alert('Por favor selecciona una orden de compra');
                return;
            }
            
            const nuevaOrden = selectedRadio.value;
            const numeroOrden = selectedRadio.getAttribute('data-numero');
            const comentarios = document.getElementById('comentariosOC').value;
            
            if (!confirm(`¿Cambiar la orden de compra a ${numeroOrden}?`)) {
                return;
            }
            
            try {
                const response = await fetch('index.php?controller=admin&action=cambiarOrdenCompra', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `factura_id=${facturaActualId}&nueva_orden=${nuevaOrden}&comentarios=${encodeURIComponent(comentarios)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✅ Orden de compra actualizada correctamente');
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al guardar los cambios');
            }
        }

        function abrirModalAprobar(facturaId) {
            facturaActualId = facturaId;
            document.getElementById('comentariosAprobar').value = '';
            document.getElementById('modalAprobar').style.display = 'block';
        }

        async function aprobarFactura() {
            const comentarios = document.getElementById('comentariosAprobar').value;
            
            if (!confirm('¿Confirmas que esta factura es correcta y pasa al área de Finanzas?')) {
                return;
            }
            
            try {
                const response = await fetch('index.php?controller=admin&action=aprobarFacturaCompras', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `factura_id=${facturaActualId}&comentarios=${encodeURIComponent(comentarios)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✅ Factura aprobada correctamente. Pasa al área de Finanzas.');
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al aprobar la factura');
            }
        }

        function abrirModalRechazar(facturaId) {
            facturaActualId = facturaId;
            document.getElementById('motivoRechazo').value = '';
            document.getElementById('modalRechazar').style.display = 'block';
        }

        async function rechazarFactura() {
            const motivo = document.getElementById('motivoRechazo').value.trim();
            
            if (!motivo) {
                alert('Debes ingresar un motivo para el rechazo');
                return;
            }
            
            if (!confirm('¿Estás seguro de RECHAZAR esta factura? La contraseña se anulará y la factura SAT quedará disponible nuevamente.')) {
                return;
            }
            
            try {
                const response = await fetch('index.php?controller=admin&action=rechazarFacturaCompras', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `factura_id=${facturaActualId}&motivo=${encodeURIComponent(motivo)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('❌ Factura rechazada. La contraseña ha sido anulada.');
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al rechazar la factura');
            }
        }

        // ==================== FUNCIONES UTILITARIAS ====================
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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
</body>

</html>