<?php
// app/views/proveedor/reportar-factura.php
?>
<div class="form-container">
    <h1>Reportar Nueva Factura</h1>

    <?php if (!empty($success)): ?>
        <div class="alert success"><?= $success ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert error"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="reportarForm">

        <!-- Indicador de doble factura (solo para proveedores autorizados) -->
        <?php if ($esDobleFactura): ?>
        <div class="form-group" style="background: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <label style="display: flex; align-items: center; cursor: pointer;">
                <input type="checkbox" name="es_doble_factura" id="es_doble_factura" value="1" 
                       onchange="toggleDobleFactura(this)">
                <span style="margin-left: 10px; font-weight: bold;">✅ Agregar Facturas Adicionales (Cobros de otros proveedores)</span>
            </label>
            <small style="display: block; margin-top: 8px; color: #666;">
                Marca esta opción si necesitas agregar facturas de otros proveedores (transporte, fletes, etc.)
            </small>
        </div>
        <?php endif; ?>

        <!-- Factura SAT Principal -->
        <div class="form-group">
            <label>Seleccionar Factura del SAT (Principal)</label>
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

        <!-- SECCIÓN DE FACTURAS ADICIONALES (ESTILO LIQUIDACIÓN CON FILTRO) -->
        <div id="seccionDobleFactura" style="display: none; border: 2px solid #ff9800; padding: 20px; border-radius: 10px; margin: 20px 0; background: #fff8e1;">
            <h3 style="color: #e65100; margin-bottom: 15px;">📄 Facturas Adicionales (Otros Proveedores)</h3>
            <p style="margin-bottom: 15px;">Ingresa el NIT del proveedor para buscar sus facturas disponibles</p>
            
            <!-- Buscador estilo liquidación con filtro -->
            <div class="form-group">
                <label>NIT del Proveedor</label>
                <div class="dte-search-container">
                    <input type="text" id="nit_emisor_adicional" placeholder="Ingrese NIT del emisor" class="form-control" autocomplete="off">
                    <div id="dte-suggestions-adicional" class="dte-suggestions" style="display: none;">
                        <!-- Campo de búsqueda dentro de las sugerencias -->
                        <div style="padding: 8px; border-bottom: 1px solid #eee; background: #f8f9fa;">
                            <input type="text" id="filter_factura_input" placeholder="🔍 Filtrar por número de factura..." class="form-control" style="font-size: 13px; padding: 6px;">
                        </div>
                        <div class="suggestions-list" id="dte-suggestions-list-adicional" style="max-height: 250px; overflow-y: auto;"></div>
                    </div>
                </div>
            </div>
            
            <!-- Preview de factura seleccionada -->
            <div id="factura-seleccionada-adicional" class="factura-seleccionada" style="display: none; margin-top: 10px;">
                <div id="factura-seleccionada-contenido"></div>
                <button type="button" class="btn-small" onclick="agregarFacturaAdicionalSeleccionada()" style="margin-top: 10px;">+ Agregar esta factura</button>
            </div>
            
            <!-- Campo oculto para la factura seleccionada temporalmente -->
            <input type="hidden" id="factura_adicional_temp" value="">
            
            <!-- Lista de facturas adicionales agregadas -->
            <h4 style="margin-top: 20px;">📋 Facturas Adicionales Agregadas</h4>
            <div id="listaFacturasAdicionales" style="max-height: 300px; overflow-y: auto;">
                <p style="color: #999; text-align: center;">No hay facturas adicionales agregadas</p>
            </div>
            
            <!-- Campo oculto para almacenar JSON de facturas adicionales -->
            <input type="hidden" name="facturas_adicionales" id="facturas_adicionales" value="[]">
        </div>

        <div class="form-group">
            <label>Retención (Q) (si aplica)</label>
            <input type="number" name="retencion" step="0.01" value="0">
        </div>

        <!-- Órdenes de Compra -->
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

        <div class="form-group" id="pdfs_adicionales_container" style="display: none;">
            <label>PDFs de Facturas Adicionales</label>
            <div id="pdfs_adicionales_list"></div>
        </div>

        <button type="submit" class="btn-primary">Reportar Factura y Generar Contraseña</button>
    </form>
</div>

<!-- Modal de Órdenes de Compra -->
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

<!-- Modal de Contraseña -->
<div id="modalContraseña" class="modal" style="display:none;">
    <div class="modal-content">
        <h2 style="color:#006400; text-align:center;">✅ Factura Reportada Correctamente</h2>
        
        <div style="text-align:center; margin:25px 0;">
            <p><strong>Contraseña generada:</strong></p>
            <p id="contrasenaDisplay" style="font-size:1.65rem; color:#006400; font-weight:bold; background:#f0f8f0; padding:18px; border-radius:10px;">
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
let facturasAdicionales = [];
let contadorTemp = 0;
let cachedDtesAdicional = [];
let facturaAdicionalSeleccionada = null;
let filterTimeout = null;

// Fechas para búsqueda (últimos 3 meses)
const fechaFin = new Date().toISOString().split('T')[0];
const fechaInicio = new Date(new Date().setMonth(new Date().getMonth() - 3)).toISOString().split('T')[0];

// ==================== TOGGLE DOBLE FACTURA ====================
function toggleDobleFactura(checkbox) {
    const seccion = document.getElementById('seccionDobleFactura');
    if (checkbox.checked) {
        seccion.style.display = 'block';
    } else {
        seccion.style.display = 'none';
        facturasAdicionales = [];
        actualizarListaFacturas();
    }
}

// ==================== BUSCAR DTEs POR NIT ====================
async function fetchDteSuggestionsAdicional(nit) {
    if (!nit || nit.length === 0) {
        document.getElementById('dte-suggestions-adicional').style.display = 'none';
        return;
    }
    
    // Guardar el NIT actual para evitar llamadas obsoletas
    currentNit = nit;
    isLoading = true;
    
    try {
        const response = await fetch(`index.php?controller=proveedor&action=buscarDTEsPorNit&nit=${encodeURIComponent(nit)}&fecha_inicio=${fechaInicio}&fecha_fin=${fechaFin}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const dtes = await response.json();
        
        // Verificar que la respuesta corresponda al último NIT buscado
        if (currentNit !== nit) return;
        
        if (!response.ok) {
            throw new Error(dtes.error || 'Error al buscar DTEs');
        }
        
        cachedDtesAdicional = dtes;
        displayDteSuggestionsAdicional(cachedDtesAdicional, nit);
    } catch (error) {
        console.error('Error al buscar DTEs:', error);
        if (currentNit === nit) {
            const suggestionsList = document.getElementById('dte-suggestions-list-adicional');
            if (suggestionsList) {
                suggestionsList.innerHTML = '<div style="padding: 12px; text-align: center; color: red;">Error al cargar facturas</div>';
            }
        }
    } finally {
        isLoading = false;
    }
}


// ==================== MOSTRAR SUGERENCIAS CON FILTRO ====================
function displayDteSuggestionsAdicional(dtes, nitBuscado) {
    const suggestionsContainer = document.getElementById('dte-suggestions-adicional');
    const suggestionsList = document.getElementById('dte-suggestions-list-adicional');
    const filterInput = document.getElementById('filter_factura_input');
    
    if (!suggestionsList) return;
    
    // Determinar si la búsqueda fue parcial (menos de 8 caracteres) o exacta
    const esParcial = nitBuscado.length < 8;
    
    // Agrupar por NIT si es búsqueda parcial
    let dtesAgrupadas = [];
    if (esParcial && dtes.length > 0) {
        // Agrupar por NIT
        const groupedByNit = {};
        dtes.forEach(dte => {
            if (!groupedByNit[dte.nit_emisor]) {
                groupedByNit[dte.nit_emisor] = [];
            }
            groupedByNit[dte.nit_emisor].push(dte);
        });
        
        // Crear un array con encabezados de NIT
        for (const [nit, facturas] of Object.entries(groupedByNit)) {
            dtesAgrupadas.push({ type: 'header', nit: nit, count: facturas.length });
            facturas.forEach(f => dtesAgrupadas.push({ type: 'dte', data: f }));
        }
    } else {
        dtesAgrupadas = dtes.map(d => ({ type: 'dte', data: d }));
    }
    
    const renderList = (filterText = '') => {
        suggestionsList.innerHTML = '';
        
        let filteredItems = dtesAgrupadas;
        if (filterText) {
            const searchLower = filterText.toLowerCase();
            filteredItems = dtesAgrupadas.filter(item => {
                if (item.type === 'header') {
                    // Los headers siempre se muestran si tienen DTEs que coinciden
                    const hasMatchingDte = dtesAgrupadas.some(d => 
                        d.type === 'dte' && 
                        d.data.nit_emisor === item.nit &&
                        (`${d.data.serie || ''} ${d.data.numero_dte || ''}`.toLowerCase().includes(searchLower) ||
                         `${d.data.numero_dte || ''}`.toLowerCase().includes(searchLower) ||
                         `${d.data.serie || ''}`.toLowerCase().includes(searchLower))
                    );
                    return hasMatchingDte;
                } else {
                    const dte = item.data;
                    const facturaCompleta = `${dte.serie || ''} ${dte.numero_dte || ''}`.toLowerCase();
                    const soloNumero = `${dte.numero_dte || ''}`.toLowerCase();
                    const soloSerie = `${dte.serie || ''}`.toLowerCase();
                    return facturaCompleta.includes(searchLower) || 
                           soloNumero.includes(searchLower) || 
                           soloSerie.includes(searchLower);
                }
            });
        }
        
        if (filteredItems.length === 0) {
            suggestionsList.innerHTML = '<div style="padding: 12px; text-align: center; color: #999;">No se encontraron facturas</div>';
            return;
        }
        
        // Mostrar mensaje de cuántas facturas se encontraron
        const totalDtes = filteredItems.filter(i => i.type === 'dte').length;
        if (totalDtes > 0) {
            const infoDiv = document.createElement('div');
            infoDiv.style.padding = '8px 12px';
            infoDiv.style.backgroundColor = '#e8f5e9';
            infoDiv.style.fontSize = '12px';
            infoDiv.style.color = '#2e7d32';
            infoDiv.style.borderBottom = '1px solid #c8e6c9';
            infoDiv.innerHTML = `📊 ${totalDtes} factura(s) encontrada(s)`;
            suggestionsList.appendChild(infoDiv);
        }
        
        filteredItems.forEach(item => {
            if (item.type === 'header') {
                // Mostrar encabezado de NIT
                const headerDiv = document.createElement('div');
                headerDiv.style.padding = '8px 12px';
                headerDiv.style.backgroundColor = '#f5f5f5';
                headerDiv.style.fontWeight = 'bold';
                headerDiv.style.fontSize = '12px';
                headerDiv.style.color = '#006400';
                headerDiv.style.borderBottom = '1px solid #ddd';
                headerDiv.innerHTML = `📌 NIT: ${escapeHtml(item.nit)} (${item.count} facturas)`;
                suggestionsList.appendChild(headerDiv);
            } else {
                const dte = item.data;
                const div = document.createElement('div');
                div.classList.add('dte-suggestion');
                div.style.padding = '10px';
                div.style.cursor = 'pointer';
                div.style.borderBottom = '1px solid #eee';
                div.style.display = 'flex';
                div.style.justifyContent = 'space-between';
                div.style.alignItems = 'center';
                div.innerHTML = `
                    <div>
                        <strong>${escapeHtml(dte.serie || '')} ${escapeHtml(dte.numero_dte || '')}</strong><br>
                        <small>${escapeHtml(dte.nombre_emisor || '').substring(0, 35)}</small>
                    </div>
                    <div style="text-align: right;">
                        <div>Q ${parseFloat(dte.monto || 0).toFixed(2)}</div>
                        <small>${dte.fecha_emision ? dte.fecha_emision.split(' ')[0] : ''}</small>
                    </div>
                `;
                div.addEventListener('click', (e) => {
                    e.stopPropagation();
                    selectDteAdicional(dte);
                    suggestionsContainer.style.display = 'none';
                    if (filterInput) filterInput.value = '';
                });
                div.addEventListener('mouseover', () => { div.style.backgroundColor = '#e8f5e9'; });
                div.addEventListener('mouseout', () => { div.style.backgroundColor = 'white'; });
                suggestionsList.appendChild(div);
            }
        });
    };
    
    // Mostrar el contenedor
    suggestionsContainer.style.display = 'block';
    
    // Configurar evento de filtro en el input
    if (filterInput) {
        // Limpiar evento anterior si existe
        const newFilterInput = filterInput.cloneNode(true);
        filterInput.parentNode.replaceChild(newFilterInput, filterInput);
        
        newFilterInput.addEventListener('input', (e) => {
            if (filterTimeout) clearTimeout(filterTimeout);
            filterTimeout = setTimeout(() => {
                renderList(e.target.value);
            }, 300);
        });
        
        newFilterInput.addEventListener('click', (e) => {
            e.stopPropagation();
        });
        
        newFilterInput.focus();
        newFilterInput.placeholder = esParcial ? '🔍 Filtrar por número de factura...' : '🔍 Buscar factura específica...';
    }
    
    renderList('');
}

// ==================== MOSTRAR SUGERENCIAS CON FILTRO ====================
function displayDteSuggestionsAdicional(dtes) {
    const suggestionsContainer = document.getElementById('dte-suggestions-adicional');
    const suggestionsList = document.getElementById('dte-suggestions-list-adicional');
    const filterInput = document.getElementById('filter_factura_input');
    
    if (!suggestionsList) return;
    
    const renderList = (filterText = '') => {
        suggestionsList.innerHTML = '';
        
        let filteredDtes = dtes;
        if (filterText) {
            const searchLower = filterText.toLowerCase();
            filteredDtes = dtes.filter(dte => {
                const facturaCompleta = `${dte.serie || ''} ${dte.numero_dte || ''}`.toLowerCase();
                const soloNumero = `${dte.numero_dte || ''}`.toLowerCase();
                const soloSerie = `${dte.serie || ''}`.toLowerCase();
                return facturaCompleta.includes(searchLower) || 
                       soloNumero.includes(searchLower) || 
                       soloSerie.includes(searchLower);
            });
        }
        
        if (filteredDtes.length === 0) {
            suggestionsList.innerHTML = '<div style="padding: 12px; text-align: center; color: #999;">No se encontraron facturas</div>';
            return;
        }
        
        filteredDtes.forEach(dte => {
            const div = document.createElement('div');
            div.classList.add('dte-suggestion');
            div.style.padding = '10px';
            div.style.cursor = 'pointer';
            div.style.borderBottom = '1px solid #eee';
            div.style.display = 'flex';
            div.style.justifyContent = 'space-between';
            div.style.alignItems = 'center';
            div.innerHTML = `
                <div>
                    <strong>${escapeHtml(dte.serie || '')} ${escapeHtml(dte.numero_dte || '')}</strong><br>
                    <small>${escapeHtml(dte.nombre_emisor || '').substring(0, 35)}</small>
                </div>
                <div style="text-align: right;">
                    <div>Q ${parseFloat(dte.monto || 0).toFixed(2)}</div>
                    <small>${dte.fecha_emision ? dte.fecha_emision.split(' ')[0] : ''}</small>
                </div>
            `;
            div.addEventListener('click', (e) => {
                e.stopPropagation();
                selectDteAdicional(dte);
                suggestionsContainer.style.display = 'none';
                if (filterInput) filterInput.value = '';
            });
            div.addEventListener('mouseover', () => { div.style.backgroundColor = '#e8f5e9'; });
            div.addEventListener('mouseout', () => { div.style.backgroundColor = 'white'; });
            suggestionsList.appendChild(div);
        });
    };
    
    // Mostrar el contenedor
    suggestionsContainer.style.display = 'block';
    
    // Configurar evento de filtro en el input
    if (filterInput) {
        // Limpiar evento anterior si existe
        const newFilterInput = filterInput.cloneNode(true);
        filterInput.parentNode.replaceChild(newFilterInput, filterInput);
        
        newFilterInput.addEventListener('input', (e) => {
            if (filterTimeout) clearTimeout(filterTimeout);
            filterTimeout = setTimeout(() => {
                renderList(e.target.value);
            }, 300);
        });
        
        newFilterInput.addEventListener('click', (e) => {
            e.stopPropagation();
        });
        
        newFilterInput.focus();
    }
    
    renderList('');
}

// ==================== SELECCIONAR DTE ADICIONAL ====================
function selectDteAdicional(dte) {
    facturaAdicionalSeleccionada = dte;
    
    const container = document.getElementById('factura-seleccionada-adicional');
    const contenido = document.getElementById('factura-seleccionada-contenido');
    const hiddenTemp = document.getElementById('factura_adicional_temp');
    
    hiddenTemp.value = JSON.stringify(dte);
    
    contenido.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <strong>Factura seleccionada:</strong><br>
                <strong>Proveedor:</strong> ${escapeHtml(dte.nombre_emisor || '')}<br>
                <strong>NIT:</strong> ${escapeHtml(dte.nit_emisor || '')}<br>
                <strong>Factura:</strong> ${escapeHtml(dte.serie || '')}-${escapeHtml(dte.numero_dte || '')}<br>
                <strong>Fecha:</strong> ${escapeHtml(dte.fecha_emision ? dte.fecha_emision.split(' ')[0] : '')}<br>
                <strong>Monto:</strong> Q ${parseFloat(dte.monto || 0).toFixed(2)}
            </div>
        </div>
    `;
    
    container.style.display = 'block';
    
    // Limpiar input de NIT y cerrar sugerencias
    const nitInput = document.getElementById('nit_emisor_adicional');
    nitInput.value = dte.nit_emisor; // Dejar el NIT completo
    document.getElementById('dte-suggestions-adicional').style.display = 'none';
    
    // Disparar búsqueda exacta con el NIT completo para actualizar sugerencias
    setTimeout(() => {
        fetchDteSuggestionsAdicional(dte.nit_emisor);
    }, 100);
}

// ==================== AGREGAR FACTURA ADICIONAL SELECCIONADA ====================
function agregarFacturaAdicionalSeleccionada() {
    if (!facturaAdicionalSeleccionada) {
        alert('Primero selecciona una factura de la lista de sugerencias');
        return;
    }
    
    const dte = facturaAdicionalSeleccionada;
    
    // Verificar si ya fue agregada
    const existe = facturasAdicionales.some(f => 
        f.nit === dte.nit_emisor && 
        f.numero_dte === dte.numero_dte && 
        f.serie === dte.serie
    );
    
    if (existe) {
        alert('Esta factura ya fue agregada');
        return;
    }
    
    const tempId = Date.now() + contadorTemp++;
    
    facturasAdicionales.push({
        temp_id: tempId,
        nit: dte.nit_emisor,
        nombre: dte.nombre_emisor,
        serie: dte.serie,
        numero_dte: dte.numero_dte,
        fecha_emision: dte.fecha_emision ? dte.fecha_emision.split(' ')[0] : '',
        monto: parseFloat(dte.monto || 0),
        usado: false
    });
    
    actualizarListaFacturas();
    
    // Limpiar selección
    facturaAdicionalSeleccionada = null;
    document.getElementById('factura-seleccionada-adicional').style.display = 'none';
    document.getElementById('factura-seleccionada-contenido').innerHTML = '';
    document.getElementById('factura_adicional_temp').value = '';
    
    // Remover la factura del caché local para que no aparezca de nuevo
    cachedDtesAdicional = cachedDtesAdicional.filter(f => 
        !(f.serie === dte.serie && f.numero_dte === dte.numero_dte)
    );
}

// ==================== ELIMINAR FACTURA ADICIONAL ====================
function eliminarFacturaAdicional(tempId) {
    const factura = facturasAdicionales.find(f => f.temp_id === tempId);
    if (factura) {
        // Devolver al caché
        cachedDtesAdicional.unshift({
            serie: factura.serie,
            numero_dte: factura.numero_dte,
            nombre_emisor: factura.nombre,
            nit_emisor: factura.nit,
            fecha_emision: factura.fecha_emision,
            monto: factura.monto
        });
    }
    facturasAdicionales = facturasAdicionales.filter(f => f.temp_id !== tempId);
    actualizarListaFacturas();
}

// ==================== ACTUALIZAR LISTA DE FACTURAS ====================
function actualizarListaFacturas() {
    const listaDiv = document.getElementById('listaFacturasAdicionales');
    const inputHidden = document.getElementById('facturas_adicionales');
    const pdfsContainer = document.getElementById('pdfs_adicionales_container');
    const pdfsList = document.getElementById('pdfs_adicionales_list');
    
    if (facturasAdicionales.length === 0) {
        listaDiv.innerHTML = '<p style="color: #999; text-align: center;">No hay facturas adicionales agregadas</p>';
        pdfsContainer.style.display = 'none';
        inputHidden.value = '[]';
        return;
    }
    
    let html = '<table style="width:100%; border-collapse: collapse;">';
    html += '<thead><tr style="background:#f0f0f0;"><th>Proveedor</th><th>Factura</th><th>Fecha</th><th>Monto</th><th>PDF</th><th></th></tr></thead><tbody>';
    
    let pdfsHtml = '';
    
    facturasAdicionales.forEach((f) => {
        html += `
            <tr style="border-bottom:1px solid #ddd;">
                <td style="padding:8px;">${escapeHtml(f.nombre.substring(0, 40))}<br><small>NIT: ${f.nit}</small></td>
                <td style="padding:8px;">${f.serie}-${f.numero_dte}</td>
                <td style="padding:8px;">${f.fecha_emision}</td>
                <td style="padding:8px;">Q ${f.monto.toFixed(2)}</td>
                <td style="padding:8px;">
                    <input type="file" name="pdf_adicional_${f.temp_id}" accept=".pdf" class="pdf-extra" data-id="${f.temp_id}" onchange="marcarPDFSubido(this)">
                </td>
                <td style="padding:8px;">
                    <button type="button" class="btn-small" onclick="eliminarFacturaAdicional(${f.temp_id})" style="background:#dc3545;">🗑️</button>
                </td>
            </tr>
        `;
        pdfsHtml += `<input type="hidden" name="factura_adicional_data[]" value='${JSON.stringify(f)}'>`;
    });
    
    html += '</tbody></table>';
    const totalAdicional = facturasAdicionales.reduce((sum, f) => sum + f.monto, 0);
    html += '<p style="margin-top:10px;"><strong>Total facturas adicionales: Q ' + totalAdicional.toFixed(2) + '</strong></p>';
    
    listaDiv.innerHTML = html;
    pdfsList.innerHTML = pdfsHtml;
    pdfsContainer.style.display = 'block';
    
    inputHidden.value = JSON.stringify(facturasAdicionales.map(f => ({
        temp_id: f.temp_id,
        nit: f.nit,
        nombre: f.nombre,
        serie: f.serie,
        numero_dte: f.numero_dte,
        fecha_emision: f.fecha_emision,
        monto: f.monto
    })));
    
    // Actualizar monto total
    const montoPrincipal = parseFloat(document.getElementById('monto').value) || 0;
    
    let totalDisplay = document.getElementById('total_con_adicionales');
    if (!totalDisplay) {
        totalDisplay = document.createElement('div');
        totalDisplay.id = 'total_con_adicionales';
        totalDisplay.style.marginTop = '10px';
        totalDisplay.style.fontWeight = 'bold';
        totalDisplay.style.padding = '10px';
        totalDisplay.style.background = '#e8f5e9';
        totalDisplay.style.borderRadius = '6px';
        document.getElementById('seccionDobleFactura').appendChild(totalDisplay);
    }
    totalDisplay.innerHTML = `<strong>💰 Total General (Principal + Adicionales): Q ${(montoPrincipal + totalAdicional).toFixed(2)}</strong>`;
}

function marcarPDFSubido(input) {
    if (input.files.length > 0) {
        const parentRow = input.closest('tr');
        if (parentRow) {
            const statusCell = parentRow.cells[4];
            statusCell.innerHTML = '✅ PDF cargado';
            setTimeout(() => {
                statusCell.innerHTML = '<input type="file" name="' + input.name + '" accept=".pdf" class="pdf-extra" onchange="marcarPDFSubido(this)">';
            }, 2000);
        }
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ==================== EVENT LISTENERS PARA BÚSQUEDA ADICIONAL ====================
let searchTimeout = null;
let currentNit = '';
let isLoading = false;

document.getElementById('nit_emisor_adicional').addEventListener('input', async (e) => {
    const nit = e.target.value.trim();
    
    // Cancelar búsqueda anterior
    if (searchTimeout) clearTimeout(searchTimeout);
    
    if (nit.length === 0) {
        document.getElementById('dte-suggestions-adicional').style.display = 'none';
        cachedDtesAdicional = [];
        return;
    }
    
    // Mostrar loading mientras busca
    const suggestionsContainer = document.getElementById('dte-suggestions-adicional');
    const suggestionsList = document.getElementById('dte-suggestions-list-adicional');
    if (suggestionsList && nit.length >= 3) {
        suggestionsList.innerHTML = '<div style="padding: 12px; text-align: center; color: #999;">🔍 Buscando facturas...</div>';
        suggestionsContainer.style.display = 'block';
    }
    
    // Debounce de 500ms
    searchTimeout = setTimeout(async () => {
        await fetchDteSuggestionsAdicional(nit);
    }, 500);
});

// Cerrar sugerencias al hacer clic fuera
document.addEventListener('click', (e) => {
    if (!e.target.closest('.dte-search-container')) {
        document.getElementById('dte-suggestions-adicional').style.display = 'none';
    }
});

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

// Autocompletado desde preselección
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
                ${data.mensaje_adicional ? '<br><br>' + data.mensaje_adicional : ''}
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
                ${data.mensaje_adicional ? '<br><br>' + data.mensaje_adicional : ''}
            </p>
        `;
    }

    document.getElementById('modalMensaje').innerHTML = mensaje;
    document.getElementById('modalContraseña').style.display = 'block';
}

function cerrarModalContraseña() {
    document.getElementById('modalContraseña').style.display = 'none';
}

// Mostrar modal de contraseña automáticamente
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
    max-width: 600px;
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
    cursor: pointer;
}

.ordenes-list {
    margin: 15px 0;
    max-height: 400px;
    overflow-y: auto;
}

.form-select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
}

.btn-small {
    padding: 5px 10px;
    font-size: 0.8rem;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    background-color: #006400;
    color: white;
}

.alert {
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 6px;
}

.alert.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Estilos para el buscador estilo liquidación */
.dte-search-container {
    position: relative;
}

.dte-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ccc;
    border-radius: 4px;
    z-index: 1000;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.suggestions-list {
    width: 100%;
}

.dte-suggestion {
    padding: 10px;
    cursor: pointer;
    border-bottom: 1px solid #eee;
    transition: background-color 0.2s;
}

.dte-suggestion:hover {
    background-color: #e8f5e9;
}

.factura-seleccionada {
    background: #f0f8f0;
    padding: 10px;
    border-radius: 6px;
    border-left: 4px solid #006400;
}
</style>