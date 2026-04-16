<div class="page-container">
    <h1>Mis Facturas Reportadas</h1>

    <!-- Filtros -->
    <div class="filters">
        <a href="?controller=proveedor&action=misFacturas" class="filter-btn <?= empty($estado) ? 'active' : '' ?>">Todas</a>
        <a href="?controller=proveedor&action=misFacturas&estado=reportada" class="filter-btn <?= $estado === 'reportada' ? 'active' : '' ?>">Reportadas</a>
        <a href="?controller=proveedor&action=misFacturas&estado=validada" class="filter-btn <?= $estado === 'validada' ? 'active' : '' ?>">Validadas</a>
        <a href="?controller=proveedor&action=misFacturas&estado=en_sap" class="filter-btn <?= $estado === 'en_sap' ? 'active' : '' ?>">En SAP</a>
        <a href="?controller=proveedor&action=misFacturas&estado=pagada" class="filter-btn <?= $estado === 'pagada' ? 'active' : '' ?>">Pagadas</a>
    </div>

    <table class="data-table">
    <thead>
        <tr>
            <th>Factura</th>
            <th>Fecha Factura SAT</th>
            <th>Fecha Reporte</th>
            <th>Monto</th>
            <th>Retención</th>
            <th>Estado</th>
            <th>Contraseña</th>
            <th>Fecha Pago Esperada</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($facturas as $f): ?>
        <tr>
            <td><strong><?= htmlspecialchars($f['numero_factura']) ?></strong></td>
            <td><?= $f['fecha_factura_sat'] ? date('d/m/Y', strtotime($f['fecha_factura_sat'])) : '—' ?></td>
            <td><?= date('d/m/Y', strtotime($f['fecha_emision'])) ?></td>
            <td>Q <?= number_format($f['monto'], 2) ?></td>
            <td>Q <?= number_format($f['monto_retencion'] ?? 0, 2) ?></td>
            <td><span class="status <?= $f['estado'] ?>"><?= ucfirst($f['estado']) ?></span></td>
            <td>
                <?php if (!empty($f['contrasena_pago']) && $f['estado'] !== 'pagada'): ?>
                    <strong style="color:#006400;"><?= $f['contrasena_pago'] ?></strong>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
            <td><?= $f['fecha_pago_esperada'] ? date('d/m/Y', strtotime($f['fecha_pago_esperada'])) : '—' ?></td>
            <td>
                <a href="#" onclick="verArchivos(<?= $f['id'] ?>, '<?= htmlspecialchars($f['numero_factura']) ?>')" class="btn-small">Archivos</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<!-- Modal simple para mostrar archivos (puedes mejorarlo después) -->
<div id="modalArchivos" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal()">&times;</span>
        <h3>Archivos de la Factura #<span id="modalFacturaNum"></span></h3>
        <div id="contenidoArchivos"></div>
    </div>
</div>

<script>
function verArchivos(id, numeroFactura) {
    document.getElementById('modalFacturaNum').textContent = numeroFactura;
    
    let html = `
        <p><strong>Factura PDF:</strong> 
            <a href="index.php?controller=proveedor&action=descargar&id=${id}&tipo=factura" target="_blank" class="btn-small">Descargar Factura</a>
        </p>
        <p><strong>Orden de Compra PDF:</strong> 
            <a href="index.php?controller=proveedor&action=descargar&id=${id}&tipo=orden" target="_blank" class="btn-small">Descargar Orden</a>
        </p>
    `;

    if (true) { // siempre mostrar si existe, pero por simplicidad
        html += `
            <p><strong>Constancia (si aplica):</strong> 
                <a href="index.php?controller=proveedor&action=descargar&id=${id}&tipo=constancia" target="_blank" class="btn-small">Descargar Constancia</a>
            </p>
        `;
    }

    document.getElementById('contenidoArchivos').innerHTML = html;
    document.getElementById('modalArchivos').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalArchivos').style.display = 'none';
}
</script>