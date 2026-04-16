<div class="page-container">
    <h1>Gestión de Usuarios</h1>
    <p>CardCode: <strong><?= htmlspecialchars($_SESSION['user']['cardcode']) ?></strong></p>

    <?php if (!empty($success)): ?>
        <div class="alert success"><?= $success ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert error"><?= $error ?></div>
    <?php endif; ?>

    <!-- Formulario para crear usuario -->
    <div class="form-container" style="max-width:700px;">
        <h2>Crear Nuevo Usuario</h2>
        <form method="POST">
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Username (para login) *</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Contraseña *</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>Rol *</label>
                <select name="rol" required>
                    <option value="crear_contrasenas">Solo Crear Contraseñas</option>
                    <option value="consultas">Consultas (Ver Pagos + Crear)</option>
                    <option value="admin">Administrador (Todo)</option>
                </select>
            </div>
            <button type="submit" class="btn-primary">Crear Usuario</button>
        </form>
    </div>

    <!-- Lista de usuarios existentes -->
    <h2>Usuarios existentes para este CardCode</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Fecha Creación</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($usuarios)): ?>
                <tr><td colspan="4">No hay usuarios registrados</td></tr>
            <?php else: ?>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><strong><?= ucfirst(str_replace('_', ' ', $u['rol'])) ?></strong></td>
                    <td><?= date('d/m/Y H:i', strtotime($u['fecha_creacion'])) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div style="margin-top: 30px;">
        <a href="index.php?controller=proveedor&action=dashboard" class="btn-secondary">← Volver al Dashboard</a>
    </div>
</div>