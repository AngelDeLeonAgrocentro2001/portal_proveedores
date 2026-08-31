<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Proveedores — Finanzas</title>
    <link rel="stylesheet" href="/portal_proveedores/public/assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        html { height: 100%; }
        body {
            background: #f3f4f6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .site-header {
            position: sticky; top: 0; left: 0; right: 0; width: 100%;
            z-index: 50; background-color: #1d6f3c;
            box-shadow: 0 2px 12px rgba(0,0,0,0.25);
        }
        .site-header-inner {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px; padding: 10px 20px; width: 100%;
        }
        .site-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; flex-shrink: 0; }
        .site-logo img { height: 42px; width: auto; }
        .site-logo-text { display: flex; flex-direction: column; line-height: 1.25; }
        .site-logo-name { color: #fff; font-weight: 700; font-size: 1rem; }
        .site-logo-sub  { color: #bbf7d0; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 1.5px; }
        .site-userbar   { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; font-size: 0.85rem; color: #fff; }
        .site-userbar .username { font-weight: 700; color: #bbf7d0; }
        .site-userbar .role-pill {
            background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25);
            color: rgba(255,255,255,0.85); font-size: 0.72rem; padding: 3px 10px;
            border-radius: 999px; font-weight: 600;
        }
        .site-userbar .logout-btn {
            background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.25);
            color: #fff; font-size: 0.78rem; font-weight: 700; padding: 6px 14px;
            border-radius: 7px; text-decoration: none; transition: background 0.2s; white-space: nowrap;
        }
        .site-userbar .logout-btn:hover { background: rgba(255,255,255,0.3); }

        /* Estilos específicos de finanzas */
        .finanzas-container { max-width: 1400px; margin: 24px auto; padding: 0 20px; width: 100%; flex: 1 0 auto; }
        .card-pago  { background: #e8f5e9; border-left: 5px solid #0D7C66; }
        .badge-semana { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .75rem; font-weight: bold; }
        .semana-este    { background: #4caf50; color: white; }
        .semana-proximo { background: #ff9800; color: white; }
        .btn-finanzas-aprobar  { background: #1d6f3c; color: white; border: none; padding: 8px 18px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn-finanzas-rechazar { background: #c62828; color: white; border: none; padding: 8px 18px; border-radius: 6px; cursor: pointer; font-weight: 600; }

        @media (max-width: 640px) {
            .site-header-inner { padding: 8px 14px; }
            .site-logo img { height: 34px; }
            .site-logo-sub { display: none; }
            .site-userbar .role-pill { display: none; }
            .site-userbar .logout-btn { padding: 5px 10px; font-size: 0.73rem; }
        }
    </style>
</head>
<body>

    <header class="site-header">
        <div class="site-header-inner">
            <a href="/portal_proveedores/public/index.php?controller=proveedor&action=dashboard" class="site-logo">
                <img src="/portal_proveedores/public/assets/images/agrocentroLogo.png" alt="Agrocentro">
                <div class="site-logo-text">
                    <span class="site-logo-name">Agrocentro</span>
                    <span class="site-logo-sub">Finanzas — Autorización de Pagos</span>
                </div>
            </a>
            <div class="site-userbar">
                <span class="username"><?= htmlspecialchars($_SESSION['user']['username'] ?? 'Usuario') ?></span>
                <span class="role-pill">Finanzas</span>
                <a href="index.php?controller=auth&action=logout" class="logout-btn">Cerrar Sesión</a>
            </div>
        </div>
    </header>

    <div class="finanzas-container">

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.data-table').forEach(function (table) {
                if (table.parentElement.classList.contains('table-scroll-wrapper')) return;
                var wrapper = document.createElement('div');
                wrapper.className = 'table-scroll-wrapper';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            });
        });
    </script>
