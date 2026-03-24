<?php
// app/views/auth/login.php
if (isset($_SESSION['user'])) {
    header('Location: ../../public/index.php?controller=proveedor&action=dashboard');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Proveedores - Login</title>
    <link rel="stylesheet" href="../../public/assets/css/style.css">
    <style>
        body.login-body {
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #006400, #004d00);
            font-family: Arial, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            padding: 40px 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
            text-align: center;
        }
        .login-container h1 {
            color: #006400;
            margin-bottom: 10px;
        }
        .error {
            color: #d32f2f;
            background: #ffebee;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        input {
            width: 100%;
            padding: 14px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 16px;
        }
        button {
            width: 100%;
            padding: 14px;
            background: #006400;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 17px;
            cursor: pointer;
            margin-top: 10px;
        }
        button:hover {
            background: #004d00;
        }
    </style>
</head>
<body class="login-body">
    <div class="login-container">
        <h1>Portal de Proveedores</h1>
        <p style="margin-bottom:25px; color:#555;">Agrocentro</p>

        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="../../public/index.php?controller=auth&action=login">
            <input type="text" name="username" placeholder="Usuario" required autofocus>
            <input type="password" name="password" placeholder="Contraseña" required>
            <button type="submit">Ingresar al Portal</button>
        </form>

        <p style="margin-top:20px; font-size:14px; color:#666;">
            ¿Problemas para ingresar? Contacta al departamento de Proveedores
        </p>
    </div>
</body>
</html>