<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitar Registro - Portal Proveedores</title>
    <link rel="stylesheet" href="/portal_proveedores/public/assets/css/style.css">
    <style>
        body { background: #f0f8f0; }
        .solicitud-container { max-width: 800px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); }
        h1 { color: #006400; text-align: center; }
        .form-row { display: flex; gap: 20px; margin-bottom: 20px; }
        .form-group { flex: 1; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #333; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; }
        .file-input { padding: 8px; background: #f9f9f9; }
        .btn-submit { background: #006400; color: white; padding: 14px; width: 100%; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; }
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 6px; }
        .alert.error { background: #f8d7da; color: #721c24; }
        .tipos-info { background: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 25px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="solicitud-container">
        <h1>📝 Solicitar Registro como Proveedor</h1>
        <p style="text-align: center; color: #666;">Complete el formulario para iniciar el proceso de autorización</p>
        
        <?php if (isset($error)): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="tipos-info">
            <strong>📌 Tipos de Proveedor:</strong><br>
            • <strong>Normal</strong>: Productos generales (aprobación solo por Compras)<br>
            • <strong>Estratégico</strong>: Proveedores clave (aprobación por Compras + Finanzas)<br>
            • <strong>Ocasional</strong>: Compras esporádicas (aprobación rápida)<br>
            • <strong>Servicios</strong>: Servicios profesionales (aprobación por Compras)
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group">
                    <label>Código de Proveedor *</label>
                    <input type="text" name="cardcode" required placeholder="Ej: PR0001">
                </div>
                <div class="form-group">
                    <label>NIT *</label>
                    <input type="text" name="nit" required placeholder="Ej: 1234567-8">
                </div>
            </div>
            
            <div class="form-group">
                <label>Nombre o Razón Social *</label>
                <input type="text" name="nombre" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono">
                </div>
                <div class="form-group">
                    <label>Correo Electrónico</label>
                    <input type="email" name="email">
                </div>
            </div>
            
            <div class="form-group">
                <label>Dirección</label>
                <textarea name="direccion" rows="2"></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Tipo de Proveedor *</label>
                    <select name="tipo_proveedor" required>
                        <option value="normal">Normal</option>
                        <option value="estratégico">Estratégico</option>
                        <option value="ocasional">Ocasional</option>
                        <option value="servicios">Servicios</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Límite de Crédito (Q)</label>
                    <input type="number" name="limite_credito" step="0.01" value="0">
                </div>
            </div>
            
            <div class="form-group">
                <label>Observaciones adicionales</label>
                <textarea name="observaciones" rows="2" placeholder="Información relevante para la autorización..."></textarea>
            </div>
            
            <h3 style="margin-top: 25px;">📎 Documentos Requeridos (PDF)</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label>RTU (Registro Tributario)</label>
                    <input type="file" name="pdf_rtu" accept=".pdf" class="file-input">
                </div>
                <div class="form-group">
                    <label>Patente de Comercio</label>
                    <input type="file" name="pdf_patente" accept=".pdf" class="file-input">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Cédula del Representante</label>
                    <input type="file" name="pdf_cedula" accept=".pdf" class="file-input">
                </div>
                <div class="form-group">
                    <label>Carta de Presentación</label>
                    <input type="file" name="pdf_carta_presentacion" accept=".pdf" class="file-input">
                </div>
            </div>
            
            <button type="submit" class="btn-submit">Enviar Solicitud</button>
        </form>
        
        <p style="text-align: center; margin-top: 20px;">
            <a href="index.php?controller=auth&action=login" style="color: #006400;">← Volver al inicio de sesión</a>
        </p>
    </div>
</body>
</html>