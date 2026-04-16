<div class="form-container">
    <h1>Reportar Nueva Factura</h1>

    <?php if (!empty($success)): ?>
        <div class="alert success"><?= $success ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert error"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="reportarForm">

        <!-- Factura SAT -->
        <div class="form-group">
            <label>Seleccionar Factura del SAT</label>
            <?php if (empty($facturasSAT)): ?>
                <p style="color: red;">No hay facturas disponibles para tu NIT.</p>
            <?php else: ?>
                <select name="factura_sat" id="factura_sat" class="form-select" onchange="llenarFactura(this)">
                    <option value="">-- Selecciona una factura SAT --</option>
                    <?php foreach ($facturasSAT as $f): ?>
                    <option value="<?= htmlspecialchars($f['serie'] . ' ' . $f['numero_dte']) ?>" 
                            data-fecha="<?= htmlspecialchars($f['fecha_emision'] ?? '') ?>" 
                            data-monto="<?= htmlspecialchars($f['gran_total'] ?? 0) ?>">
                        <?= htmlspecialchars($f['serie'] . '-' . $f['numero_dte']) ?> 
                        | <?= date('d/m/Y', strtotime($f['fecha_emision'])) ?> 
                        | Q <?= number_format($f['gran_total'] ?? 0, 2) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Número de Factura (SAT) *</label>
            <input type="text" name="numero_factura" id="numero_factura" required>
        </div>

        <div class="form-group">
            <label>Fecha de Emisión *</label>
            <input type="date" name="fecha_emision" id="fecha_emision" required>
        </div>

        <div class="form-group">
            <label>Monto Total (Q) *</label>
            <input type="number" name="monto" id="monto" step="0.01" required>
        </div>

        <div class="form-group">
            <label>Retención (Q) (si aplica)</label>
            <input type="number" name="retencion" step="0.01" value="0">
        </div>

        <!-- BOTÓN QUE ABRE EL MODAL DE ÓRDENES -->
        <div class="form-group">
            <label>Órdenes de Compra (SAP) *</label>
            <button type="button" class="btn-primary" onclick="abrirModalOrdenes()" style="width:100%;">
                Seleccionar Órdenes de Compra (<?= count($ordenesAbiertas) ?> disponibles)
            </button>
            <input type="hidden" name="ordenes[]" id="ordenesSeleccionadas" value="">
            <div id="ordenesSeleccionadasTexto" style="margin-top:8px; font-size:0.95rem; color:#006400;"></div>
        </div>

        <div class="form-group">
            <label>Viajes facturados (opcional)</label>
            <input type="text" name="viajes" placeholder="Viaje 45, Viaje 46">
        </div>

        <div class="form-group">
            <label>Factura PDF (SAT) *</label>
            <input type="file" name="pdf_factura" accept=".pdf" required>
        </div>

        <div class="form-group">
            <label>Constancia de Recepción (opcional)</label>
            <input type="file" name="pdf_constancia" accept=".pdf">
        </div>

        <button type="submit" class="btn-primary">Reportar Factura y Generar Contraseña</button>
    </form>
</div>

<!-- ====================== MODAL DE ÓRDENES DE COMPRA ====================== -->
<div id="modalOrdenes" class="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModalOrdenes()">&times;</span>
        <h2>Seleccionar Órdenes de Compra (SAP)</h2>
        <p>Selecciona una o varias órdenes:</p>

        <div class="ordenes-list" style="max-height:400px; overflow-y:auto;">
            <?php if (empty($ordenesAbiertas)): ?>
                <p>No hay órdenes de compra abiertas disponibles.</p>
            <?php else: ?>
                <?php foreach ($ordenesAbiertas as $oc): ?>
                <label class="checkbox-label">
                    <input type="checkbox" class="orden-check" 
                           value="<?= $oc['docentry'] ?>" 
                           data-numero="<?= htmlspecialchars($oc['numero_oc']) ?>"
                           data-monto="<?= $oc['monto'] ?>">
                    <strong><?= htmlspecialchars($oc['numero_oc']) ?></strong> 
                    - Q <?= number_format($oc['monto'], 2) ?> 
                    (<?= date('d/m/Y', strtotime($oc['fecha'])) ?>)
                </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="margin-top:20px; text-align:right;">
            <button type="button" class="btn-secondary" onclick="cerrarModalOrdenes()">Cancelar</button>
            <button type="button" class="btn-primary" onclick="confirmarSeleccionOrdenes()">Aceptar Selección</button>
        </div>
    </div>
</div>

<!-- ====================== MODAL DE CONTRASEÑA ====================== -->
<div id="modalContraseña" class="modal" style="display:none;">
    <div class="modal-content">
        <h2 style="color:#006400; text-align:center;">✅ Factura Reportada Correctamente</h2>
        
        <div style="text-align:center; margin:25px 0;">
            <p><strong>Contraseña generada:</strong></p>
            <p id="contrasenaDisplay" style="font-size:1.65rem; color:#006400; font-weight:bold; background:#f0f8f0; padding:18px; border-radius:10px;">
                <!-- Se llena con JS -->
            </p>
        </div>

        <div id="modalMensaje" style="margin:20px 0; font-size:1.08rem; line-height:1.6; text-align:center;"></div>

        <div style="text-align:center; margin-top:30px;">
            <button onclick="cerrarModalContraseña()" class="btn-primary" style="padding:14px 50px; font-size:1.1rem;">
                Aceptar
            </button>
        </div>
    </div>
</div>

<script>
// ==================== MODAL ÓRDENES ====================
function abrirModalOrdenes() {
    document.getElementById('modalOrdenes').style.display = 'block';
}

function cerrarModalOrdenes() {
    document.getElementById('modalOrdenes').style.display = 'none';
}

function confirmarSeleccionOrdenes() {
    const checks = document.querySelectorAll('.orden-check:checked');
    let valores = [];
    let texto = [];

    checks.forEach(check => {
        valores.push(check.value);
        texto.push(check.getAttribute('data-numero'));
    });

    document.getElementById('ordenesSeleccionadas').value = valores.join(',');
    
    if (texto.length > 0) {
        document.getElementById('ordenesSeleccionadasTexto').innerHTML = 
            '<strong>Seleccionadas:</strong> ' + texto.join(', ');
    } else {
        document.getElementById('ordenesSeleccionadasTexto').innerHTML = '';
    }

    cerrarModalOrdenes();
}

// ==================== AUTOCOMPLETADO FACTURA SAT ====================
function llenarFactura(select) {
    const option = select.options[select.selectedIndex];
    if (!option || !option.value) return;

    document.getElementById('numero_factura').value = option.value.trim();
    const fecha = option.getAttribute('data-fecha');
    if (fecha) document.getElementById('fecha_emision').value = fecha.substring(0, 10);
    const monto = option.getAttribute('data-monto');
    if (monto) document.getElementById('monto').value = parseFloat(monto).toFixed(2);
}

// Autocompletado desde preseleccion (desde facturasSAT)
document.addEventListener('DOMContentLoaded', function() {
    const preseleccion = '<?= addslashes($preseleccion ?? '') ?>'.trim();
    
    if (preseleccion) {
        document.getElementById('numero_factura').value = preseleccion;

        const select = document.getElementById('factura_sat');
        if (select) {
            for (let i = 0; i < select.options.length; i++) {
                const option = select.options[i];
                if (option.value.trim() === preseleccion || option.value.includes(preseleccion)) {
                    const fecha = option.getAttribute('data-fecha');
                    const monto = option.getAttribute('data-monto');

                    if (fecha) document.getElementById('fecha_emision').value = fecha.substring(0, 10);
                    if (monto) document.getElementById('monto').value = parseFloat(monto).toFixed(2);
                    select.value = option.value;
                    break;
                }
            }
        }

        setTimeout(() => {
            document.getElementById('monto').focus();
        }, 600);
    }
});

// ==================== MODAL CONTRASEÑA ====================
function mostrarModalContraseña() {
    const data = <?= json_encode($_SESSION['last_report'] ?? []) ?>;

    if (!data.success) return;

    document.getElementById('contrasenaDisplay').textContent = data.contrasena || 'AGRO-XXXXXX-XXXXXX';

    let mensaje = '';

    if (data.esLunes === true) {
        mensaje = `
            <p style="color:#006400;">
                ✅ La contraseña fue generada correctamente <strong>hoy lunes</strong>.<br><br>
                Se iniciarán los 30 días de crédito a partir de hoy.
            </p>
        `;
    } else {
        const proximoLunes = data.proximoLunes || 'No disponible';
        mensaje = `
            <p style="color:#d32f2f;">
                <strong>⚠️ Importante:</strong><br><br>
                Hoy no es lunes.<br><br>
                La contraseña se tomará en cuenta el <strong>próximo lunes</strong>:<br>
                <strong style="font-size:1.35rem; color:#006400;">${proximoLunes}</strong><br><br>
                A partir de ese lunes se contarán los 30 días de crédito para el pago.
            </p>
        `;
    }

    document.getElementById('modalMensaje').innerHTML = mensaje;
    document.getElementById('modalContraseña').style.display = 'block';
}

function cerrarModalContraseña() {
    document.getElementById('modalContraseña').style.display = 'none';
}

// Mostrar modal de contraseña automáticamente después de cargar la página
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['last_report']) && $_SESSION['last_report']['success']): ?>
        setTimeout(() => {
            mostrarModalContraseña();
            <?php unset($_SESSION['last_report']); ?>
        }, 800);
    <?php endif; ?>
});
</script>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0; top: 0;
    width: 100%; height: 100%;
    background-color: rgba(0,0,0,0.7);
}

.modal-content {
    background-color: #fff;
    margin: 8% auto;
    padding: 30px;
    border-radius: 12px;
    width: 90%;
    max-width: 520px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.close {
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.checkbox-label {
    display: block;
    padding: 10px;
    margin: 5px 0;
    border: 1px solid #eee;
    border-radius: 6px;
}

.ordenes-list {
    margin: 15px 0;
}
</style>