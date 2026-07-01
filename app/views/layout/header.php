<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Proveedores — Agrocentro</title>
    <link rel="stylesheet" href="/portal_proveedores/public/assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary:   '#1d6f3c',
                        teal:      '#0D7C66',
                        bright:    '#4CAF50',
                        'dark-bg': '#0E1E14',
                    }
                }
            }
        }
    </script>
    <style>
        /* Layout base — sin flex en body para evitar conflictos con sticky */
        body { background: #f3f4f6; min-height: 100vh; }

        /* Header full-width sticky */
        .site-header {
            position: sticky;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            z-index: 50;
            background-color: #1d6f3c;
            box-shadow: 0 2px 12px rgba(0,0,0,0.25);
        }

        .site-header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            padding: 10px 20px;
            width: 100%;
        }

        /* Logo */
        .site-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            flex-shrink: 0;
        }
        .site-logo img { height: 42px; width: auto; }
        .site-logo-text { display: flex; flex-direction: column; line-height: 1.25; }
        .site-logo-name {
            color: #ffffff;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: 0.3px;
        }
        .site-logo-sub {
            color: #bbf7d0;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        /* User bar */
        .site-userbar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 0.85rem;
            color: #ffffff;
        }
        .site-userbar .greeting { opacity: 0.75; }
        .site-userbar .username { font-weight: 700; color: #bbf7d0; }
        .site-userbar .nombre   { opacity: 0.6; font-size: 0.78rem; }
        .site-userbar .role-pill {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            color: rgba(255,255,255,0.85);
            font-size: 0.72rem;
            padding: 3px 10px;
            border-radius: 999px;
            font-weight: 600;
        }
        .site-userbar .logout-btn {
            background: rgba(255,255,255,0.18);
            border: 1px solid rgba(255,255,255,0.25);
            color: #ffffff;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 7px;
            text-decoration: none;
            transition: background 0.2s;
            white-space: nowrap;
        }
        .site-userbar .logout-btn:hover { background: rgba(255,255,255,0.3); }

        /* Main content area */
        .site-main { padding: 24px 20px; width: 100%; }

        /* Responsive */
        @media (max-width: 640px) {
            .site-header-inner { padding: 8px 14px; gap: 8px; }
            .site-logo img { height: 34px; }
            .site-logo-sub { display: none; }
            .site-userbar .greeting,
            .site-userbar .nombre { display: none; }
            .site-userbar .role-pill { display: none; }
            .site-userbar .logout-btn { padding: 5px 10px; font-size: 0.73rem; }
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <header class="site-header">
        <div class="site-header-inner">

            <a href="/portal_proveedores/public/index.php?controller=proveedor&action=dashboard"
               class="site-logo">
                <img src="/portal_proveedores/public/assets/images/agrocentroLogo.png" alt="Agrocentro">
                <div class="site-logo-text">
                    <span class="site-logo-name">Agrocentro</span>
                    <span class="site-logo-sub">Portal de Proveedores</span>
                </div>
            </a>

            <div class="site-userbar">
                <span class="greeting">Bienvenido,</span>
                <span class="username"><?= htmlspecialchars($_SESSION['user']['username'] ?? 'Usuario') ?></span>

                <?php if (!empty($_SESSION['user']['nombre'])): ?>
                    <span class="nombre">(<?= htmlspecialchars($_SESSION['user']['nombre']) ?>)</span>
                <?php endif; ?>

                <span class="role-pill">
                    <?= ucfirst(str_replace('_', ' ', $_SESSION['user']['rol'] ?? '')) ?>
                </span>

                <a href="/portal_proveedores/public/index.php?controller=auth&action=logout"
                   class="logout-btn">Cerrar Sesión</a>
            </div>

        </div>
    </header>

    <!-- MAIN -->
    <div class="site-main">

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
