<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Proveedores - Dashboard</title>
    <link rel="stylesheet" href="../../public/assets/css/style.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">Agrocentro - Proveedores</div>
            <div class="user-info">
                <?= htmlspecialchars($_SESSION['user']['nombre'] ?? 'Proveedor') ?> 
                <a href="index.php?controller=auth&action=logout">Cerrar Sesión</a>
            </div>
        </nav>
    </header>
    <main></main>