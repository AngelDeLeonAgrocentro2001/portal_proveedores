<?php
// app/views/ingreso_manual/index.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingreso Manual de Contraseñas - Agrosistemas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 40px 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 40px;
            color: white;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Tarjeta de búsqueda */
        .search-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .search-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .form-group {
            flex: 1;
            min-width: 250px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
        }

        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-danger {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }

        /* Alertas */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert.error {
            background: #fee;
            color: #c00;
            border-left: 4px solid #c00;
        }

        .alert.success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }

        /* Información del proveedor */
        .proveedor-info {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .proveedor-info h3 {
            color: #333;
            margin-bottom: 15px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .info-item {
            background: white;
            padding: 12px 16px;
            border-radius: 10px;
        }

        .info-item strong {
            color: #667eea;
        }

        /* Tablas */
        .table-wrapper {
            background: white;
            border-radius: 12px;
            overflow-x: auto;
            margin-top: 20px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }

        .data-table tbody tr:hover {
            background: #f8f9fa;
        }

        .status-disponible {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #d4edda;
            color: #155724;
        }

        .status-usada {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #f8d7da;
            color: #721c24;
        }

        .radio-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .radio-group input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .checkbox-label:hover {
            background: #f8f9fa;
            border-color: #667eea;
        }

        .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .close {
            font-size: 28px;
            cursor: pointer;
            color: #999;
        }

        .close:hover {
            color: #333;
        }

        /* Loading */
        .loading {
            text-align: center;
            padding: 40px;
            color: #667eea;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.8rem;
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .form-group {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-key"></i> Ingreso Manual de Contraseñas</h1>
            <p>Sistema externo para creación de contraseñas de pago - Proveedores Agrocentro</p>
        </div>

        <div class="search-card">
            <h2><i class="fas fa-search"></i> Buscar Proveedor</h2>
            <form method="POST" class="search-form">
                <div class="form-group">
                    <label>Código de Proveedor (CardCode)</label>
                    <input type="text" name="cardcode" placeholder="Ej: PR0001" required 
                           value="<?= htmlspecialchars($_POST['cardcode'] ?? '') ?>">
                </div>
                <button type="submit" name="buscar_proveedor" class="btn-primary">
                    <i class="fas fa-search"></i> Buscar
                </button>
            </form>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($proveedor && !empty($facturasSAT)): ?>
            <!-- Información del Proveedor -->
            <div class="proveedor-info">
                <h3><i class="fas fa-building"></i> Información del Proveedor</h3>
                <div class="info-grid">
                    <div class="info-item"><strong>Código:</strong> <?= htmlspecialchars($proveedor['cardcode']) ?></div>
                    <div class="info-item"><strong>Nombre:</strong> <?= htmlspecialchars($proveedor['nombre']) ?></div>
                    <div class="info-item"><strong>NIT:</strong> <?= htmlspecialchars($proveedor['nit']) ?></div>
                </div>
            </div>

            <!-- Facturas Disponibles -->
            <h2><i class="fas fa-file-invoice"></i> Facturas Disponibles para Reportar</h2>
            <div class="table-wrapper">
                <form method="POST" id="formReporte">
                    <input type="hidden" name="cardcode" value="<?= htmlspecialchars($proveedor['cardcode']) ?>">
                    <input type="hidden" name="reportar_factura" value="1">
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 50px">Seleccionar</th>
                                <th>Fecha Emisión</th>
                                <th>Serie</th>
                                <th>N° DTE</th>
                                <th>Nombre Emisor</th>
                                <th>Monto</th>
                                <th>IVA</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($facturasSAT as $f): ?>
                                <tr>
                                    <td class="radio-group">
                                        <input type="radio" name="numero_factura" 
                                               value="<?= htmlspecialchars($f['numero_completo']) ?>"
                                               data-serie="<?= htmlspecialchars($f['serie']) ?>"
                                               data-numero="<?= htmlspecialchars($f['numero_dte']) ?>"
                                               data-fecha="<?= $f['fecha_emision'] ?>"
                                               data-monto="<?= $f['monto'] ?>"
                                               required>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($f['fecha_emision'])) ?></td>
                                    <td><?= htmlspecialchars($f['serie'] ?? '-') ?></td>
                                    <td><strong><?= htmlspecialchars($f['numero_dte']) ?></strong></td>
                                    <td><?= htmlspecialchars($f['nombre_emisor'] ?? '-') ?></td>
                                    <td><strong>Q <?= number_format($f['monto'], 2) ?></strong></td>
                                    <td>Q <?= number_format($f['iva'] ?? 0, 2) ?></td>
                                    <td>
                                        <span class="status-disponible">
                                            <i class="fas fa-check-circle"></i> Disponible
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Campos ocultos para la factura seleccionada -->
                    <input type="hidden" name="serie" id="serie_seleccionada">
                    <input type="hidden" name="numero_dte" id="numero_dte_seleccionado">
                    <input type="hidden" name="fecha_emision" id="fecha_emision_seleccionada">
                    <input type="hidden" name="monto" id="monto_seleccionado">

                    <!-- Órdenes de Compra -->
                    <?php if (!empty($ordenesAbiertas)): ?>
                        <h2 style="margin-top: 30px;"><i class="fas fa-shopping-cart"></i> Órdenes de Compra Disponibles</h2>
                        <div class="table-wrapper">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px">Seleccionar</th>
                                        <th>N° Orden de Compra</th>
                                        <th>Fecha</th>
                                        <th>Monto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ordenesAbiertas as $oc): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="ordenes[]" value="<?= $oc['docentry'] ?>"
                                                       data-numero="<?= htmlspecialchars($oc['numero_oc']) ?>">
                                            </td>
                                            <td><strong><?= htmlspecialchars($oc['numero_oc']) ?></strong></td>
                                            <td><?= date('d/m/Y', strtotime($oc['fecha'])) ?></td>
                                            <td>Q <?= number_format($oc['monto'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert error" style="margin-top: 20px;">
                            <i class="fas fa-exclamation-triangle"></i> 
                            No hay órdenes de compra abiertas para este proveedor.
                        </div>
                    <?php endif; ?>

                    <div style="margin-top: 30px; text-align: center;">
                        <button type="submit" class="btn-success" <?= empty($ordenesAbiertas) ? 'disabled' : '' ?>>
                            <i class="fas fa-key"></i> Generar Contraseña de Pago
                        </button>
                    </div>
                </form>
            </div>
        <?php elseif ($proveedor && empty($facturasSAT)): ?>
            <div class="alert error">
                <i class="fas fa-info-circle"></i> 
                No hay facturas SAT disponibles para este proveedor. Todas las facturas ya han sido reportadas.
            </div>
        <?php endif; ?>
    </div>

    <script>
    // Manejar selección de factura
    const radios = document.querySelectorAll('input[name="numero_factura"]');
    radios.forEach(radio => {
        radio.addEventListener('change', function() {
            document.getElementById('serie_seleccionada').value = this.dataset.serie || '';
            document.getElementById('numero_dte_seleccionado').value = this.dataset.numero || '';
            document.getElementById('fecha_emision_seleccionada').value = this.dataset.fecha || '';
            document.getElementById('monto_seleccionado').value = this.dataset.monto || 0;
            
            console.log("Factura seleccionada:");
            console.log("Serie:", this.dataset.serie);
            console.log("Número:", this.dataset.numero);
            console.log("Fecha:", this.dataset.fecha);
            console.log("Monto:", this.dataset.monto);
        });
    });

    // Validar antes de enviar
    document.getElementById('formReporte')?.addEventListener('submit', function(e) {
        const facturaSeleccionada = document.querySelector('input[name="numero_factura"]:checked');
        const ordenesSeleccionadas = document.querySelectorAll('input[name="ordenes[]"]:checked');
        
        if (!facturaSeleccionada) {
            e.preventDefault();
            alert('Por favor, seleccione una factura');
            return false;
        }
        
        if (ordenesSeleccionadas.length === 0) {
            e.preventDefault();
            alert('Por favor, seleccione al menos una Orden de Compra');
            return false;
        }
        
        // Verificar que los campos ocultos tengan valores
        const serie = document.getElementById('serie_seleccionada').value;
        const numero = document.getElementById('numero_dte_seleccionado').value;
        
        if (!serie || !numero) {
            e.preventDefault();
            alert('Error: Datos de factura incompletos. Por favor seleccione nuevamente la factura.');
            return false;
        }
        
        return confirm(`¿Confirmar generación de contraseña para la factura ${serie} ${numero}?\n\nSe generará una contraseña y la factura quedará marcada como usada.`);
    });
</script>
</body>
</html>