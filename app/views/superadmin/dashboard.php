<?php
// app/views/superadmin/dashboard.php
$rolesDisponibles = [
    'superadmin'           => 'Super Admin',
    'admin'                => 'Admin',
    'consultas'            => 'Consultas',
    'crear_contrasenas'    => 'Crear Contraseñas',
    'supervisor_compras'   => 'Supervisor de Compras',
    'supervisor_finanzas'  => 'Supervisor de Finanzas',
    'contabilidad'         => 'Contabilidad',
];
$tiposSupervisorDisponibles = ['normal', 'estratégico', 'ocasional', 'servicios', 'transporte', 'material_empaque', 'saci', 'finanzas', 'contabilidad', 'admin'];
$tiposProveedorDisponibles = ['normal', 'estratégico', 'ocasional', 'servicios', 'transporte', 'material_empaque', 'saci'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - Portal Proveedores</title>
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
        .admin-header a { color: #fff; text-decoration: none; }
        .admin-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .seccion {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .seccion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
    </style>
</head>
<body style="background:#f4f6f8;">

    <div class="admin-header">
        <div><strong>🔐 Super Admin</strong> — Gestión de Usuarios y Proveedores</div>
        <div>
            <?= htmlspecialchars($_SESSION['user']['username'] ?? '') ?> |
            <a href="index.php?controller=auth&action=logout">Cerrar sesión</a>
        </div>
    </div>

    <div class="admin-container">

        <?php if (!empty($error)): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- ==================== PROVEEDORES ==================== -->
        <div class="seccion">
            <div class="seccion-header">
                <h2 style="margin:0;">🏢 Proveedores (<?= count($proveedores) ?>)</h2>
                <button type="button" class="btn-primary" style="width:auto;" onclick="abrirModal('modalCrearProveedor')">+ Nuevo Proveedor</button>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>CardCode</th>
                            <th>Nombre</th>
                            <th>NIT</th>
                            <th>Tipo</th>
                            <th>Días Crédito</th>
                            <th>Email</th>
                            <th>Estado</th>
                            <th>Doble Factura</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($proveedores)): ?>
                            <tr><td colspan="9">No hay proveedores registrados</td></tr>
                        <?php else: foreach ($proveedores as $p): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['cardcode']) ?></strong></td>
                                <td><?= htmlspecialchars($p['nombre']) ?></td>
                                <td><?= htmlspecialchars($p['nit']) ?></td>
                                <td><?= htmlspecialchars($p['tipo_proveedor']) ?></td>
                                <td><?= (int)$p['dias_credito'] ?></td>
                                <td><?= htmlspecialchars($p['email'] ?? '—') ?></td>
                                <td>
                                    <span class="status <?= $p['estado'] === 'activo' ? 'abierta' : 'rechazada' ?>">
                                        <?= ucfirst($p['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $p['doble_factura'] ? '2️⃣ Sí' : '—' ?>
                                </td>
                                <td style="white-space:nowrap;">
                                    <button type="button" class="btn-small"
                                        onclick='abrirModalEditarProveedor(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                        ✏️ Editar
                                    </button>
                                    <form method="POST" action="index.php?controller=superadmin&action=cambiarEstadoProveedor" style="display:inline;"
                                          onsubmit="return confirm('¿<?= $p['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?> este proveedor?')">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="estado" value="<?= $p['estado'] === 'activo' ? 'inactivo' : 'activo' ?>">
                                        <button type="submit" class="btn-small" style="background:<?= $p['estado'] === 'activo' ? '#dc3545' : '#28a745' ?>; color:#fff;">
                                            <?= $p['estado'] === 'activo' ? '🚫 Desactivar' : '✅ Activar' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ==================== USUARIOS ==================== -->
        <div class="seccion">
            <div class="seccion-header">
                <h2 style="margin:0;">👤 Usuarios (<?= count($usuarios) ?>)</h2>
                <button type="button" class="btn-primary" style="width:auto;" onclick="abrirModal('modalCrearUsuario')">+ Nuevo Usuario</button>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>CardCode</th>
                            <th>Proveedor</th>
                            <th>Correo</th>
                            <th>Rol</th>
                            <th>Tipo Supervisor</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr><td colspan="7">No hay usuarios registrados</td></tr>
                        <?php else: foreach ($usuarios as $u): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                                <td><?= htmlspecialchars($u['cardcode']) ?></td>
                                <td><?= htmlspecialchars($u['proveedor_nombre'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($rolesDisponibles[$u['rol']] ?? $u['rol']) ?></td>
                                <td><?= htmlspecialchars($u['tipo_supervisor'] ?? '—') ?></td>
                                <td style="white-space:nowrap;">
                                    <button type="button" class="btn-small"
                                        onclick='abrirModalEditarUsuario(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                        ✏️ Editar
                                    </button>
                                    <form method="POST" action="index.php?controller=superadmin&action=eliminarUsuario" style="display:inline;"
                                          onsubmit="return confirm('¿Eliminar el usuario &quot;<?= htmlspecialchars($u['username']) ?>&quot;? Esta acción no se puede deshacer.')">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn-small" style="background:#dc3545; color:#fff;">🗑️ Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: Crear Proveedor ==================== -->
    <div id="modalCrearProveedor" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalCrearProveedor')">&times;</span>
            <h2>🏢 Nuevo Proveedor</h2>
            <form method="POST" action="index.php?controller=superadmin&action=crearProveedor">
                <div class="form-group">
                    <label>CardCode (SAP) *</label>
                    <input type="text" name="cardcode" id="crear_prov_cardcode" required
                           onblur="autocompletarDiasCreditoSAP(this.value)">
                </div>
                <div class="form-group">
                    <label>NIT *</label>
                    <input type="text" name="nit" required>
                </div>
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" required>
                </div>
                <div class="form-group">
                    <label>Dirección</label>
                    <input type="text" name="direccion">
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email">
                </div>
                <div class="form-group">
                    <label>Días de Crédito</label>
                    <input type="number" name="dias_credito" id="crear_prov_dias_credito" value="30">
                    <small id="crear_prov_dias_credito_status" style="display:block; margin-top:4px; color:#666;"></small>
                </div>
                <div class="form-group">
                    <label>Tipo de Proveedor</label>
                    <select name="tipo_proveedor">
                        <?php foreach ($tiposProveedorDisponibles as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="doble_factura" value="1">
                        2️⃣ Requiere Doble Factura (puede reportar facturas de otros proveedores, ej. transporte/fletes)
                    </label>
                </div>
                <div style="text-align:right; margin-top:20px;">
                    <button type="button" class="btn-secondary" onclick="cerrarModal('modalCrearProveedor')">Cancelar</button>
                    <button type="submit" class="btn-primary" style="width:auto;">Crear Proveedor</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: Editar Proveedor ==================== -->
    <div id="modalEditarProveedor" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalEditarProveedor')">&times;</span>
            <h2>✏️ Editar Proveedor</h2>
            <form method="POST" action="index.php?controller=superadmin&action=actualizarProveedor">
                <input type="hidden" name="id" id="edit_prov_id">
                <div class="form-group">
                    <label>CardCode</label>
                    <input type="text" id="edit_prov_cardcode" disabled>
                </div>
                <div class="form-group">
                    <label>NIT</label>
                    <input type="text" id="edit_prov_nit" disabled>
                </div>
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" id="edit_prov_nombre" required>
                </div>
                <div class="form-group">
                    <label>Dirección</label>
                    <input type="text" name="direccion" id="edit_prov_direccion">
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono" id="edit_prov_telefono">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_prov_email">
                </div>
                <div class="form-group">
                    <label>Días de Crédito</label>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <input type="number" name="dias_credito" id="edit_prov_dias_credito" style="flex:1;">
                        <button type="button" class="btn-secondary" style="width:auto; white-space:nowrap;"
                                onclick="actualizarDiasCreditoDesdeSAP()">🔄 Actualizar desde SAP</button>
                    </div>
                    <small id="edit_prov_dias_credito_status" style="display:block; margin-top:4px; color:#666;"></small>
                </div>
                <div class="form-group">
                    <label>Tipo de Proveedor</label>
                    <select name="tipo_proveedor" id="edit_prov_tipo_proveedor">
                        <?php foreach ($tiposProveedorDisponibles as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="doble_factura" id="edit_prov_doble_factura" value="1">
                        2️⃣ Requiere Doble Factura (puede reportar facturas de otros proveedores, ej. transporte/fletes)
                    </label>
                </div>
                <div style="text-align:right; margin-top:20px;">
                    <button type="button" class="btn-secondary" onclick="cerrarModal('modalEditarProveedor')">Cancelar</button>
                    <button type="submit" class="btn-primary" style="width:auto;">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: Crear Usuario ==================== -->
    <div id="modalCrearUsuario" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalCrearUsuario')">&times;</span>
            <h2>👤 Nuevo Usuario</h2>
            <form method="POST" action="index.php?controller=superadmin&action=crearUsuario">
                <div class="form-group">
                    <label>CardCode del Proveedor *</label>
                    <input type="text" name="cardcode" required placeholder="Debe existir ya en Proveedores">
                </div>
                <div class="form-group">
                    <label>Correo *</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Usuario *</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Contraseña *</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>Rol *</label>
                    <select name="rol">
                        <?php foreach ($rolesDisponibles as $val => $label): ?>
                            <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tipo Supervisor (solo si el rol es Supervisor de Compras)</label>
                    <select name="tipo_supervisor">
                        <option value="">— No aplica —</option>
                        <?php foreach ($tiposSupervisorDisponibles as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Área (opcional)</label>
                    <input type="text" name="area">
                </div>
                <div style="text-align:right; margin-top:20px;">
                    <button type="button" class="btn-secondary" onclick="cerrarModal('modalCrearUsuario')">Cancelar</button>
                    <button type="submit" class="btn-primary" style="width:auto;">Crear Usuario</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: Editar Usuario ==================== -->
    <div id="modalEditarUsuario" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalEditarUsuario')">&times;</span>
            <h2>✏️ Editar Usuario</h2>
            <form method="POST" action="index.php?controller=superadmin&action=actualizarUsuario">
                <input type="hidden" name="id" id="edit_user_id">
                <div class="form-group">
                    <label>CardCode del Proveedor *</label>
                    <input type="text" name="cardcode" id="edit_user_cardcode" required>
                </div>
                <div class="form-group">
                    <label>Correo *</label>
                    <input type="email" name="email" id="edit_user_email" required>
                </div>
                <div class="form-group">
                    <label>Usuario *</label>
                    <input type="text" name="username" id="edit_user_username" required>
                </div>
                <div class="form-group">
                    <label>Nueva Contraseña (dejar en blanco para no cambiarla)</label>
                    <input type="password" name="password">
                </div>
                <div class="form-group">
                    <label>Rol *</label>
                    <select name="rol" id="edit_user_rol">
                        <?php foreach ($rolesDisponibles as $val => $label): ?>
                            <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tipo Supervisor (solo si el rol es Supervisor de Compras)</label>
                    <select name="tipo_supervisor" id="edit_user_tipo_supervisor">
                        <option value="">— No aplica —</option>
                        <?php foreach ($tiposSupervisorDisponibles as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Área (opcional)</label>
                    <input type="text" name="area" id="edit_user_area">
                </div>
                <div style="text-align:right; margin-top:20px;">
                    <button type="button" class="btn-secondary" onclick="cerrarModal('modalEditarUsuario')">Cancelar</button>
                    <button type="submit" class="btn-primary" style="width:auto;">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModal(id) {
            document.getElementById(id).style.display = 'flex';
        }
        function cerrarModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function abrirModalEditarProveedor(p) {
            document.getElementById('edit_prov_id').value = p.id;
            document.getElementById('edit_prov_cardcode').value = p.cardcode;
            document.getElementById('edit_prov_nit').value = p.nit;
            document.getElementById('edit_prov_nombre').value = p.nombre || '';
            document.getElementById('edit_prov_direccion').value = p.direccion || '';
            document.getElementById('edit_prov_telefono').value = p.telefono || '';
            document.getElementById('edit_prov_email').value = p.email || '';
            document.getElementById('edit_prov_dias_credito').value = p.dias_credito || 0;
            document.getElementById('edit_prov_dias_credito_status').textContent = '';
            document.getElementById('edit_prov_tipo_proveedor').value = p.tipo_proveedor || 'normal';
            document.getElementById('edit_prov_doble_factura').checked = !!parseInt(p.doble_factura);
            abrirModal('modalEditarProveedor');
        }

        // Trae los días de crédito reales desde SAP (Grupo de Proveedores -> ExtraDays) y los
        // pone en el campo indicado — el campo sigue siendo editable normalmente, esto solo
        // sugiere el valor en vez de que se digite a mano. Usado tanto en "Nuevo Proveedor"
        // (al salir del campo CardCode) como en "Editar Proveedor" (botón "Actualizar desde SAP").
        async function consultarDiasCreditoSAP(cardcode, inputId, statusId) {
            const status = document.getElementById(statusId);
            cardcode = (cardcode || '').trim();
            if (!cardcode) {
                status.textContent = '';
                return;
            }

            status.textContent = '⏳ Buscando días de crédito en SAP...';
            status.style.color = '#666';

            try {
                const response = await fetch('index.php?controller=superadmin&action=buscarDiasCreditoSAP&cardcode=' + encodeURIComponent(cardcode));
                const data = await response.json();

                if (data.success) {
                    document.getElementById(inputId).value = data.dias_credito;
                    status.textContent = `✅ ${data.dias_credito} días (SAP: ${data.cardname})`;
                    status.style.color = '#2e7d32';
                } else {
                    status.textContent = `⚠️ ${data.message} — ingresa los días de crédito manualmente`;
                    status.style.color = '#e65100';
                }
            } catch (error) {
                console.error('Error al buscar días de crédito en SAP:', error);
                status.textContent = '⚠️ No se pudo consultar SAP — ingresa los días de crédito manualmente';
                status.style.color = '#e65100';
            }
        }

        function autocompletarDiasCreditoSAP(cardcode) {
            consultarDiasCreditoSAP(cardcode, 'crear_prov_dias_credito', 'crear_prov_dias_credito_status');
        }

        function actualizarDiasCreditoDesdeSAP() {
            const cardcode = document.getElementById('edit_prov_cardcode').value;
            consultarDiasCreditoSAP(cardcode, 'edit_prov_dias_credito', 'edit_prov_dias_credito_status');
        }

        function abrirModalEditarUsuario(u) {
            document.getElementById('edit_user_id').value = u.id;
            document.getElementById('edit_user_cardcode').value = u.cardcode;
            document.getElementById('edit_user_email').value = u.email;
            document.getElementById('edit_user_username').value = u.username;
            document.getElementById('edit_user_rol').value = u.rol;
            document.getElementById('edit_user_tipo_supervisor').value = u.tipo_supervisor || '';
            document.getElementById('edit_user_area').value = u.area || '';
            abrirModal('modalEditarUsuario');
        }

    </script>
</body>
</html>
