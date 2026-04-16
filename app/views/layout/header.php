<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Proveedores Agrocentro</title>
    
    <!-- Ruta CORRECTA del CSS desde el servidor -->
    <link rel="stylesheet" href="/portal_proveedores/public/assets/css/style.css">
    
    <style>
        header {
            background: #006400;
            color: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .user-info {
            font-size: 1rem;
        }
        .user-info a {
            color: white;
            margin-left: 15px;
            text-decoration: none;
        }
        .user-info a:hover { 
            text-decoration: underline; 
        }
        main { 
            max-width: 1200px; 
            margin: 20px auto; 
            padding: 0 20px; 
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="logo">🌱 Agrocentro - Proveedores</div>
            <div class="user-info">
                <!-- Mostrar el username del usuario que inició sesión -->
                Bienvenido, <strong><?= htmlspecialchars($_SESSION['user']['username'] ?? 'Usuario') ?></strong>
                
                <?php if (!empty($_SESSION['user']['nombre'])): ?>
                    <span style="margin-left: 10px; opacity: 0.85;">
                        (<?= htmlspecialchars($_SESSION['user']['nombre']) ?>)
                    </span>
                <?php endif; ?>
                
                <span style="margin-left: 15px; opacity: 0.9;">
                    Rol: <?= ucfirst(str_replace('_', ' ', $_SESSION['user']['rol'] ?? '')) ?>
                </span>
                
                <a href="index.php?controller=auth&action=logout">Cerrar Sesión</a>
            </div>
        </nav>
    </header>
    <main>