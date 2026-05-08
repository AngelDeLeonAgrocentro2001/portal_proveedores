<div class="page-container">
    <h1>✅ Aprobaciones Pendientes - <?= ucfirst($_SESSION['user']['rol'] ?? '') ?></h1>
    
    <?php if (isset($_SESSION['aprobacion_resultado'])): ?>
        <div class="alert success"><?= htmlspecialchars($_SESSION['aprobacion_resultado']['message'] ?? ''); unset($_SESSION['aprobacion_resultado']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert error"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha Solicitud</th>
                <th>Código</th>
                <th>Proveedor</th>
                <th>NIT</th>
                <th>Tipo</th>
                <th>Límite Crédito</th>
                <th>Estado Autorización</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($solicitudes)): ?>
                <tr><td colspan="8">No hay solicitudes pendientes de tu área</td></tr>
            <?php else: ?>
                <?php foreach ($solicitudes as $s): ?>
                <tr>
                    <td>
                        <?php 
                        $fecha_solicitud = $s['fecha_solicitud'] ?? null;
                        echo $fecha_solicitud ? date('d/m/Y H:i', strtotime($fecha_solicitud)) : 'Fecha no disponible';
                        ?>
                    </td>
                    <td><?= htmlspecialchars($s['cardcode'] ?? '') ?></td>
                    <td><strong><?= htmlspecialchars($s['nombre'] ?? '') ?></strong></td>
                    <td><?= htmlspecialchars($s['nit'] ?? '') ?></td>
                    <td>
                        <span class="badge tipo-<?= htmlspecialchars($s['tipo_proveedor'] ?? 'normal') ?>">
                            <?= ucfirst(htmlspecialchars($s['tipo_proveedor'] ?? 'normal')) ?>
                        </span>
                    </td>
                    <td>Q <?= number_format(floatval($s['limite_credito'] ?? 0), 2) ?></td>
                    <td>
                        <span class="status <?= htmlspecialchars($s['estatus_autorizacion'] ?? 'pendiente') ?>">
                            <?= ucfirst(htmlspecialchars($s['estatus_autorizacion'] ?? 'pendiente')) ?>
                        </span>
                        <?php if (($s['pendiente_con'] ?? '') !== 'completado' && !empty($s['pendiente_con'])): ?>
                            <small>(Pendiente: <?= htmlspecialchars($s['pendiente_con']) ?>)</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="index.php?controller=autorizacionProveedor&action=revisar&id=<?= intval($s['id'] ?? 0) ?>" 
                           class="btn-small">Revisar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.badge.tipo-estratégico { background: #ff9800; color: white; padding: 4px 8px; border-radius: 4px; }
.badge.tipo-normal { background: #4caf50; color: white; padding: 4px 8px; border-radius: 4px; }
.badge.tipo-ocasional { background: #2196f3; color: white; padding: 4px 8px; border-radius: 4px; }
.badge.tipo-servicios { background: #9c27b0; color: white; padding: 4px 8px; border-radius: 4px; }
</style>