<?php
// app/views/compras/revision-pendiente.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compras - Revisión de Facturas</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
        }

        .compras-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .compras-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .compras-header h1 {
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .compras-header h1:before {
            content: "🛒";
            font-size: 1.8rem;
        }

        .header-stats {
            background: rgba(255,255,255,0.15);
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 0.9rem;
        }

        /* Alertas */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .alert.success {
            background: #d4edda;
            color: #155724;
            border-left: 5px solid #28a745;
        }

        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 5px solid #dc3545;
        }

        .alert.info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 5px solid #17a2b8;
        }

        /* Filtros */
        .filtros-bar {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .filtros-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filtro-btn {
            padding: 8px 20px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
            background: #e9ecef;
            color: #495057;
            text-decoration: none;
        }

        .filtro-btn:hover {
            background: #dee2e6;
            transform: translateY(-2px);
        }

        .filtro-btn.active {
            background: #006400;
            color: white;
            box-shadow: 0 2px 8px rgba(0,100,0,0.3);
        }

        .search-box {
            display: flex;
            gap: 10px;
        }

        .search-box input {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 25px;
            width: 250px;
            font-size: 0.9rem;
        }

        /* Tabla */
        .table-wrapper {
            background: white;
            border-radius: 12px;
            overflow-x: auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .data-table thead {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 2px solid #dee2e6;
        }

        .data-table th {
            padding: 16px 15px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }

        .data-table tbody tr:hover {
            background: #f8f9fa;
            transition: 0.2s;
        }

        /* Badges de estado */
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-reportada {
            background: #fff3cd;
            color: #856404;
        }

        .status-revision_compras {
            background: #cce5ff;
            color: #004085;
        }

        .status-aprobada_compras {
            background: #d4edda;
            color: #155724;
        }

        .status-rechazada_compras {
            background: #f8d7da;
            color: #721c24;
        }

        /* Contraseña */
        .password-cell {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            background: #f0f8f0;
            padding: 6px 12px;
            border-radius: 8px;
            display: inline-block;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        .password-cancelada {
            background: #f8d7da;
            color: #721c24;
            font-size: 0.7rem;
            padding: 4px 10px;
        }

        /* Botón revisar */
        .btn-revisar {
            background: #006400;
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }

        .btn-revisar:hover {
            background: #004d00;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,100,0,0.3);
        }

        /* Pie de página */
        .compras-footer {
            margin-top: 30px;
            text-align: center;
            padding: 20px;
            color: #6c757d;
            font-size: 0.8rem;
            border-top: 1px solid #dee2e6;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .compras-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .filtros-bar {
                flex-direction: column;
            }
            
            .search-box {
                width: 100%;
            }
            
            .search-box input {
                width: 100%;
            }
            
            .data-table th, .data-table td {
                padding: 10px;
                font-size: 0.8rem;
            }
        }

        /* Animación de carga */
        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="compras-container">
        <!-- Header -->
        <div class="compras-header">
            <h1>Facturas Pendientes de Revisión</h1>
            <div class="header-stats">
                📊 Total: <?= count($facturas) ?> factura(s)
            </div>
        </div>

        <!-- Alertas -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert success">
                <span>✅</span>
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert error">
                <span>❌</span>
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="filtros-bar">
            <div class="filtros-group">
                <a href="?controller=compras&action=revisionPendiente&estado=todas" 
                   class="filtro-btn <?= ($_GET['estado'] ?? '') === 'todas' ? 'active' : '' ?>">
                    Todas
                </a>
                <a href="?controller=compras&action=revisionPendiente&estado=reportada" 
                   class="filtro-btn <?= ($_GET['estado'] ?? '') === 'reportada' ? 'active' : '' ?>">
                    📋 Reportadas
                </a>
                <a href="?controller=compras&action=revisionPendiente&estado=revision_compras" 
                   class="filtro-btn <?= ($_GET['estado'] ?? '') === 'revision_compras' ? 'active' : '' ?>">
                    🔄 En Revisión
                </a>
                <a href="?controller=compras&action=revisionPendiente&estado=rechazada_compras" 
                   class="filtro-btn <?= ($_GET['estado'] ?? '') === 'rechazada_compras' ? 'active' : '' ?>">
                    ❌ Rechazadas
                </a>
            </div>
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="🔍 Buscar factura o proveedor..." onkeyup="filtrarTabla()">
            </div>
        </div>

        <!-- Tabla de facturas -->
        <div class="table-wrapper">
            <table class="data-table" id="facturasTable">
                <thead>
                    <tr>
                        <th>Fecha Reporte</th>
                        <th>Proveedor</th>
                        <th>N° Factura</th>
                        <th>Monto</th>
                        <th>Contraseña</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($facturas)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 50px;">
                                <div style="font-size: 1.2rem; color: #6c757d;">📭 No hay facturas pendientes de revisión</div>
                                <small style="color: #adb5bd;">Las nuevas facturas aparecerán aquí automáticamente</small>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($facturas as $f): ?>
                            <tr>
                                <td>
                                    <strong><?= date('d/m/Y', strtotime($f['fecha_emision'])) ?></strong>
                                    <br>
                                    <small style="color: #6c757d;"><?= date('H:i', strtotime($f['fecha_emision'] ?? 'now')) ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($f['proveedor_nombre']) ?></strong>
                                    <br>
                                    <small style="color: #6c757d;">Código: <?= $f['cardcode'] ?></small>
                                </td>
                                <td>
                                    <strong style="font-family: monospace; font-size: 0.85rem;">
                                        <?= htmlspecialchars($f['numero_factura']) ?>
                                    </strong>
                                </td>
                                <td>
                                    <strong style="color: #006400; font-size: 1rem;">
                                        Q <?= number_format($f['monto'], 2) ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php if (!empty($f['contrasena_pago']) && empty($f['contrasena_cancelada'])): ?>
                                        <span class="password-cell">
                                            🔑 <?= $f['contrasena_pago'] ?>
                                        </span>
                                    <?php elseif (!empty($f['contrasena_cancelada'])): ?>
                                        <span class="password-cell password-cancelada">
                                            ⚠️ ANULADA
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #999;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $f['estado'] ?>">
                                        <?php 
                                        $estados = [
                                            'reportada' => '📋 Reportada',
                                            'revision_compras' => '🔄 En Revisión',
                                            'aprobada_compras' => '✅ Aprobada',
                                            'rechazada_compras' => '❌ Rechazada'
                                        ];
                                        echo $estados[$f['estado']] ?? ucfirst($f['estado']);
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="index.php?controller=compras&action=revisarFactura&id=<?= $f['id'] ?>" 
                                       class="btn-revisar">
                                        🔍 Revisar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="compras-footer">
            <p>© <?= date('Y') ?> Agrocentro - Portal de Proveedores | Área de Compras</p>
        </div>
    </div>

    <script>
        // Función para filtrar la tabla por texto
        function filtrarTabla() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('facturasTable');
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                let textContent = '';
                
                // Obtener texto de las celdas de proveedor y factura
                const cells = row.getElementsByTagName('td');
                if (cells.length > 0) {
                    const proveedorText = cells[1]?.textContent.toLowerCase() || '';
                    const facturaText = cells[2]?.textContent.toLowerCase() || '';
                    textContent = proveedorText + ' ' + facturaText;
                }
                
                if (textContent.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>