<div class="page-container">
    <h1>Revisar Solicitud de Proveedor</h1>
    
    <?php if (isset($_SESSION['aprobacion_resultado'])): ?>
        <div class="alert success"><?= htmlspecialchars($_SESSION['aprobacion_resultado']['message'] ?? ''); unset($_SESSION['aprobacion_resultado']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert error"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    
    <div class="proveedor-info">
        <h2>Datos del Proveedor</h2>
        <table class="info-table">
            <tr><th>ID:</th><td><?= intval($proveedor['id'] ?? 0) ?></td>
                <th>Código:</th><td><?= htmlspecialchars($proveedor['cardcode'] ?? '') ?></td>
            </tr>
            <tr><th>Nombre:</th><td colspan="3"><strong><?= htmlspecialchars($proveedor['nombre'] ?? '') ?></strong></td></tr>
            <tr><th>NIT:</th><td><?= htmlspecialchars($proveedor['nit'] ?? '') ?></td>
                <th>Tipo:</th><td><span class="badge tipo-<?= htmlspecialchars($proveedor['tipo_proveedor'] ?? 'normal') ?>"><?= ucfirst(htmlspecialchars($proveedor['tipo_proveedor'] ?? 'normal')) ?></span></td>
            </tr>
            <tr><th>Teléfono:</th><td><?= htmlspecialchars($proveedor['telefono'] ?? '') ?></td>
                <th>Email:</th><td><?= htmlspecialchars($proveedor['email'] ?? '') ?></td>
            </tr>
            <tr><th>Dirección:</th><td colspan="3"><?= nl2br(htmlspecialchars($proveedor['direccion'] ?? '')) ?></td></tr>
            <tr><th>Límite Crédito:</th><td>Q <?= number_format(floatval($proveedor['limite_credito'] ?? 0), 2) ?></td>
                <th>Días Crédito:</th><td><?= intval($proveedor['dias_credito'] ?? 0) ?></td>
            </tr>
            <tr><th>Fecha Solicitud:</th>
                <td>
                    <?php 
                    $fecha_solicitud = $proveedor['fecha_solicitud'] ?? null;
                    echo $fecha_solicitud ? date('d/m/Y H:i', strtotime($fecha_solicitud)) : 'No disponible';
                    ?>
                </td>
                <th>Estatus:</th>
                <td>
                    <span class="status <?= htmlspecialchars($proveedor['estatus_autorizacion'] ?? 'pendiente') ?>">
                        <?= ucfirst(htmlspecialchars($proveedor['estatus_autorizacion'] ?? 'pendiente')) ?>
                    </span>
                </td>
            </tr>
            <?php if (!empty($proveedor['aprobado_por'])): ?>
            <tr>
                <th>Aprobado por Compras:</th>
                <td><?= htmlspecialchars($proveedor['aprobado_por']) ?></td>
                <th>Fecha:</th>
                <td>
                    <?php 
                    $fecha = $proveedor['fecha_aprobacion_compras'] ?? null;
                    echo $fecha ? date('d/m/Y H:i', strtotime($fecha)) : 'No disponible';
                    ?>
                </td>
            </tr>
            <?php endif; ?>
            
            <?php if (!empty($proveedor['aprobado_por_finanzas'])): ?>
            <tr>
                <th>Aprobado por Finanzas:</th>
                <td><?= htmlspecialchars($proveedor['aprobado_por_finanzas']) ?></td>
                <th>Fecha:</th>
                <td>
                    <?php 
                    $fecha = $proveedor['fecha_aprobacion_finanzas'] ?? null;
                    echo $fecha ? date('d/m/Y H:i', strtotime($fecha)) : 'No disponible';
                    ?>
                </td>
            </tr>
            <?php endif; ?>
            
            <?php if (!empty($proveedor['motivo_rechazo'])): ?>
            <tr>
                <th>Motivo Rechazo:</th>
                <td colspan="3" style="color: red;"><?= nl2br(htmlspecialchars($proveedor['motivo_rechazo'])) ?></td>
            </tr>
            <tr>
                <th>Rechazado por:</th>
                <td colspan="3"><?= htmlspecialchars($proveedor['rechazado_por'] ?? '') ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <!-- Documentos -->
    <div class="documentos">
        <h2>📎 Documentos Adjuntos</h2>
        <div class="docs-grid">
            <?php if (!empty($proveedor['pdf_rtu'])): ?>
                <a href="<?= BASE_URL . ltrim($proveedor['pdf_rtu'], '/') ?>" target="_blank" class="doc-link">📄 RTU</a>
            <?php endif; ?>
            <?php if (!empty($proveedor['pdf_patente'])): ?>
                <a href="<?= BASE_URL . ltrim($proveedor['pdf_patente'], '/') ?>" target="_blank" class="doc-link">📄 Patente</a>
            <?php endif; ?>
            <?php if (!empty($proveedor['pdf_cedula'])): ?>
                <a href="<?= BASE_URL . ltrim($proveedor['pdf_cedula'], '/') ?>" target="_blank" class="doc-link">📄 Cédula</a>
            <?php endif; ?>
            <?php if (!empty($proveedor['pdf_carta_presentacion'])): ?>
                <a href="<?= BASE_URL . ltrim($proveedor['pdf_carta_presentacion'], '/') ?>" target="_blank" class="doc-link">📄 Carta Presentación</a>
            <?php endif; ?>
            
            <?php if (empty($proveedor['pdf_rtu']) && empty($proveedor['pdf_patente']) && 
                      empty($proveedor['pdf_cedula']) && empty($proveedor['pdf_carta_presentacion'])): ?>
                <p>No hay documentos adjuntos</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Observaciones -->
    <div class="observaciones">
        <h2>📝 Observaciones del Proveedor</h2>
        <p><?= nl2br(htmlspecialchars($proveedor['observaciones_autorizacion'] ?? 'Sin observaciones')) ?></p>
    </div>
    
    <!-- Acciones de aprobación -->
    <div class="acciones">
        <h2>⚙️ Acciones de Aprobación</h2>
        
        <?php
        $rol = $_SESSION['user']['rol'] ?? '';
        $aprobado_compras = !empty($proveedor['aprobado_por']);
        $aprobado_finanzas = !empty($proveedor['aprobado_por_finanzas']);
        $requiere_compras = !empty($proveedor['requiere_autorizacion_compras']);
        $requiere_finanzas = !empty($proveedor['requiere_autorizacion_finanzas']);
        $estatus = $proveedor['estatus_autorizacion'] ?? 'pendiente';
        
        $puede_aprobar = false;
        if ($estatus === 'aprobado' || $estatus === 'rechazado') {
            $puede_aprobar = false;
        } elseif ($rol === 'admin') {
            $puede_aprobar = true;
        } elseif ($rol === 'compras' && $requiere_compras && !$aprobado_compras) {
            $puede_aprobar = true;
        } elseif ($rol === 'finanzas' && $requiere_finanzas && !$aprobado_finanzas && $aprobado_compras) {
            $puede_aprobar = true;
        }
        ?>
        
        <?php if ($puede_aprobar && $estatus !== 'rechazado'): ?>
            <form method="POST" action="index.php?controller=autorizacionProveedor&action=aprobar" style="display: inline;">
                <input type="hidden" name="id" value="<?= intval($proveedor['id'] ?? 0) ?>">
                <button type="submit" class="btn-success">✅ Aprobar Proveedor</button>
            </form>
            
            <button type="button" class="btn-danger" onclick="abrirModalRechazo()">❌ Rechazar Proveedor</button>
        <?php elseif ($estatus === 'aprobado'): ?>
            <div class="alert success">
                ✅ Este proveedor ya ha sido aprobado completamente.
                <?php if ($rol === 'admin'): ?>
                    <br>Ahora puedes crearle un usuario para acceder al portal.
                <?php endif; ?>
            </div>
        <?php elseif ($estatus === 'rechazado'): ?>
            <div class="alert error">
                ❌ Este proveedor ha sido rechazado.
                <br>Motivo: <?= htmlspecialchars($proveedor['motivo_rechazo'] ?? 'No especificado') ?>
            </div>
        <?php else: ?>
            <div class="alert info">
                <?php
                if ($rol === 'compras' && $aprobado_compras) echo "✅ Compras ya aprobó este proveedor. Pendiente de Finanzas.";
                elseif ($rol === 'finanzas' && !$aprobado_compras) echo "⏳ Esperando aprobación de Compras primero.";
                elseif ($rol === 'finanzas' && $aprobado_finanzas) echo "✅ Finanzas ya aprobó este proveedor.";
                elseif ($rol !== 'admin' && $rol !== 'compras' && $rol !== 'finanzas') echo "🔒 No tienes permisos para aprobar proveedores.";
                else echo "ℹ️ No hay acciones pendientes para tu rol.";
                ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Crear usuario (solo admin y proveedor aprobado) -->
    <?php if (($proveedor['estatus_autorizacion'] ?? '') === 'aprobado' && ($_SESSION['user']['rol'] ?? '') === 'admin'): ?>
    <div class="crear-usuario" style="margin-top: 30px; border-top: 2px solid #006400; padding-top: 20px;">
        <h2>👤 Crear Usuario para este Proveedor</h2>
        <form method="POST" action="index.php?controller=autorizacionProveedor&action=crearUsuario">
            <input type="hidden" name="proveedor_id" value="<?= intval($proveedor['id'] ?? 0) ?>">
            <div class="form-row" style="display: flex; gap: 20px;">
                <div class="form-group" style="flex: 1;">
                    <label>Email *</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($proveedor['email'] ?? '') ?>">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Username *</label>
                    <input type="text" name="username" required>
                    <small>Ej: <?= strtolower(str_replace(' ', '_', $proveedor['nombre'] ?? 'proveedor')) ?></small>
                </div>
            </div>
            <div class="form-row" style="display: flex; gap: 20px;">
                <div class="form-group" style="flex: 1;">
                    <label>Contraseña *</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Rol *</label>
                    <select name="rol">
                        <option value="crear_contrasenas">Crear Contraseñas</option>
                        <option value="consultas">Consultas (ver pagos)</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn-primary" style="margin-top: 15px;">Crear Usuario</button>
        </form>
    </div>
    <?php endif; ?>
    
    <div style="margin-top: 30px;">
        <a href="index.php?controller=autorizacionProveedor&action=aprobacionesPendientes" class="btn-secondary">← Volver a Pendientes</a>
    </div>
</div>

<!-- Modal Rechazo -->
<div id="modalRechazo" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal()">&times;</span>
        <h2 style="color: #dc3545;">❌ Rechazar Proveedor</h2>
        <form method="POST" action="index.php?controller=autorizacionProveedor&action=rechazar">
            <input type="hidden" name="id" value="<?= intval($proveedor['id'] ?? 0) ?>">
            <div class="form-group">
                <label>Motivo de Rechazo *</label>
                <textarea name="motivo_rechazo" required rows="4" placeholder="Explique el motivo por el cual no se aprueba este proveedor..."></textarea>
            </div>
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn-secondary" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="btn-danger">Confirmar Rechazo</button>
            </div>
        </form>
    </div>
</div>

<style>
.info-table { width: 100%; border-collapse: collapse; }
.info-table td, .info-table th { padding: 10px; border: 1px solid #ddd; vertical-align: top; }
.info-table th { background: #f0f0f0; width: 180px; }
.docs-grid { display: flex; gap: 15px; flex-wrap: wrap; margin: 15px 0; }
.doc-link { padding: 10px 20px; background: #f0f8f0; border: 1px solid #006400; border-radius: 6px; text-decoration: none; color: #006400; display: inline-block; }
.doc-link:hover { background: #006400; color: white; }
.btn-success { background: #28a745; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; margin-right: 10px; }
.btn-danger { background: #dc3545; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; }
.alert { padding: 12px; border-radius: 6px; margin: 15px 0; }
.alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.alert.info { background: #cce5ff; color: #004085; border: 1px solid #b8daff; }
.badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 12px; }
.badge.tipo-estratégico { background: #ff9800; color: white; }
.badge.tipo-normal { background: #4caf50; color: white; }
.badge.tipo-ocasional { background: #2196f3; color: white; }
.badge.tipo-servicios { background: #9c27b0; color: white; }
.modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal-content { background: white; padding: 25px; border-radius: 10px; max-width: 500px; width: 90%; }
.close { float: right; font-size: 28px; cursor: pointer; }
.form-row { display: flex; gap: 20px; margin-bottom: 15px; }
.form-group { flex: 1; }
.form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
.btn-primary { background: #006400; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
.btn-secondary { background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
</style>

<script>
function abrirModalRechazo() {
    document.getElementById('modalRechazo').style.display = 'flex';
}
function cerrarModal() {
    document.getElementById('modalRechazo').style.display = 'none';
}
</script>