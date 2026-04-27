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
                <th>Fecha de Contraseña</th>
                <th>Fecha Pago Esperada</th>
                <th>Acciones</th>
                <!-- <th>Gastos</th> -->
            </tr>
        </thead>
        <tbody>
            <?php if (empty($facturas)): ?>
                <tr>
                    <td colspan="10" style="text-align:center; padding:30px;">
                        No se encontraron facturas con este filtro.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($facturas as $f): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($f['numero_factura']) ?></strong></td>
                        <td><?= !empty($f['fecha_factura_sat']) ? date('d/m/Y', strtotime($f['fecha_factura_sat'])) : '—' ?></td>
                        <td><?= date('d/m/Y', strtotime($f['fecha_emision'])) ?></td>
                        <td>Q <?= number_format($f['monto'], 2) ?></td>
                        <td>Q <?= number_format($f['monto_retencion'] ?? 0, 2) ?></td>
                        <td><span class="status <?= $f['estado'] ?>"><?= ucfirst($f['estado']) ?></span></td>

                        <!-- COLUMNA CONTRASEÑA + BOTÓN PDF -->
                        <td>
                            <?php if (!empty($f['contrasena_pago']) && $f['estado'] !== 'pagada'): ?>
                                <strong style="color:#006400;"><?= htmlspecialchars($f['contrasena_pago']) ?></strong><br>
                                <a href="index.php?controller=proveedor&action=pdfContraseña&id=<?= $f['id'] ?>"
                                    class="btn-small" style="font-size:0.8rem; padding:4px 8px; margin-top:4px;" target="_blank">
                                    📄 Imprimir PDF
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>

                        <!-- Fecha de Contraseña -->
                        <td>
                            <?= !empty($f['fecha_inicio_credito'])
                                ? '<strong style="color:#006400;">' . date('d/m/Y', strtotime($f['fecha_inicio_credito'])) . '</strong>'
                                : '—' ?>
                        </td>

                        <td><?= !empty($f['fecha_pago_esperada']) ? date('d/m/Y', strtotime($f['fecha_pago_esperada'])) : '—' ?></td>

                        <!-- Acciones (archivos) -->
                        <td>
                            <a href="#" onclick="verArchivos(<?= $f['id'] ?>, '<?= htmlspecialchars($f['numero_factura']) ?>')" class="btn-small">Archivos</a>
                        </td>
                        <!-- <td>
                            <a href="index.php?controller=proveedor&action=gestionarGastos&factura_id=<?= $f['id'] ?>"
                                class="btn-small" style="background:#ff9800;">
                                💰 Gastos Adicionales
                            </a>
                        </td> -->
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal de archivos (se mantiene igual) -->
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

        if (true) {
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