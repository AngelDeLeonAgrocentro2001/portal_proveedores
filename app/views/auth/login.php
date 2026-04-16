<?php
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
    <title>Portal Proveedores Agrocentro</title>
    <link rel="stylesheet" href="../../public/assets/css/style.css">
    <style>
        .login-body {
            background: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
        }
        .login-box {
            background: white;
            padding: 40px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 420px;
        }
        input {
            width: 100%;
            padding: 14px 16px;
            margin: 12px 0;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            background: #f9f9f9;
        }
        input:focus {
            outline: none;
            border-color: #006400;
            background: white;
        }
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(to right, #006400, #008000);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 17px;
            cursor: pointer;
            margin-top: 15px;
        }
        button:hover {
            background: linear-gradient(to right, #004d00, #006400);
        }
        .links {
            margin-top: 25px;
            text-align: center;
            font-size: 14px;
        }
        .links a {
            color: #006400;
            text-decoration: none;
            margin: 0 10px;
        }
        .error { color: #d32f2f; text-align: center; margin: 15px 0; }
    </style>
</head>
<body class="login-body">
    <div class="login-box">
        <h2 style="text-align:center; color:#006400; margin-bottom:25px;">Portal de Proveedores</h2>

        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php?controller=auth&action=login">
            <input type="text" name="cardcode" placeholder="Ingrese Código de proveedor" required autofocus>
            <input type="email" name="email" placeholder="Ingrese Correo Electrónico" required>
            <input type="password" name="password" placeholder="Ingrese Contraseña" required>
            <button type="submit">Ingresar</button>
        </form>

        <div class="links">
            <a href="#">Olvide mi contraseña</a> | 
            <a href="#">Activar Usuario</a>
        </div>
    </div>
</body>
</html>