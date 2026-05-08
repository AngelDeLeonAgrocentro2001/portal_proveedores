<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud Enviada - Portal Proveedores</title>
    <link rel="stylesheet" href="/portal_proveedores/public/assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #006400 0%, #004d00 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
        }
        .success-container {
            background: white;
            max-width: 550px;
            width: 90%;
            padding: 40px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            animation: fadeIn 0.5s ease;
        }
        .success-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        h1 {
            color: #006400;
            margin-bottom: 15px;
        }
        .message-box {
            background: #e8f5e9;
            padding: 20px;
            border-radius: 10px;
            margin: 25px 0;
            text-align: left;
        }
        .message-box p {
            margin: 10px 0;
            line-height: 1.6;
            color: #2e7d32;
        }
        .steps {
            text-align: left;
            margin: 20px 0;
            padding-left: 20px;
        }
        .steps li {
            margin: 10px 0;
            color: #555;
        }
        .btn-home {
            display: inline-block;
            background: #006400;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
            transition: background 0.3s;
        }
        .btn-home:hover {
            background: #004d00;
        }
        .ref-number {
            font-family: monospace;
            font-size: 1.2rem;
            background: #f5f5f5;
            padding: 10px;
            border-radius: 6px;
            margin: 15px 0;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">✅</div>
        <h1>¡Solicitud Enviada!</h1>
        
        <div class="message-box">
            <p><strong>📋 Tu solicitud de registro ha sido recibida correctamente.</strong></p>
            <p>El área de Compras revisará la documentación y te contactará en un plazo máximo de <strong>3 días hábiles</strong>.</p>
        </div>
        
        <div class="steps">
            <p><strong>📌 Próximos pasos:</strong></p>
            <ul>
                <li>📎 Revisión de documentos por el área de Compras</li>
                <li>👥 Evaluación del perfil del proveedor</li>
                <li>✉️ Notificación por correo electrónico del resultado</li>
                <li>🔐 Si es aprobado, recibirás tus credenciales de acceso</li>
            </ul>
        </div>
        
        <div class="ref-number">
            📌 Guarda tu código de referencia: <strong><?= date('YmdHis') ?></strong>
        </div>
        
        <a href="index.php?controller=auth&action=login" class="btn-home">
            ← Volver al Inicio de Sesión
        </a>
        
        <p style="margin-top: 25px; font-size: 12px; color: #999;">
            Si no recibes respuesta en 3 días, contacta a compras@agrocentro.com
        </p>
    </div>
</body>
</html>