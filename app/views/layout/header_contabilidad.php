<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Proveedores — Contabilidad</title>
    <link rel="stylesheet" href="/portal_proveedores/public/assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #f3f4f6; min-height: 100vh; }

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

        /* Estilos específicos de contabilidad */
        .contabilidad-container { max-width: 1400px; margin: 24px auto; padding: 0 20px; }
        .badge-tipo { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .7rem; font-weight: bold; }
        .tipo-transporte { background: #0D7C66; color: white; }
        .tipo-material   { background: #7DA13D; color: white; }
        .estadisticas-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .estadistica-card {
            background: white; padding: 20px; border-radius: 12px; text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,.08); border-top: 4px solid #0D7C66;
        }
        .estadistica-number { font-size: 2rem; font-weight: bold; color: #0D7C66; }
        .search-box { background: white; padding: 25px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
        .factura-detalle { background: #f7fdf8; padding: 20px; border-radius: 10px; margin-bottom: 30px; border-left: 5px solid #0D7C66; }
        .btn-contabilidad { background: #1d6f3c; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; font-family: inherit; }
        .btn-pagar        { background: #4caf50; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; font-family: inherit; }
        .modal {
            display: none; position: fixed; z-index: 1000; inset: 0;
            background: rgba(14,30,20,.65); backdrop-filter: blur(4px);
        }
        .modal-content {
            background: white; margin: 5% auto; padding: 28px;
            border-radius: 12px; width: 90%; max-width: 600px;
            box-shadow: 0 8px 32px rgba(0,0,0,.2);
        }
        .close { float: right; font-size: 1.5rem; cursor: pointer; color: #888; }

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
                    <span class="site-logo-sub">Contabilidad — Gestión de Pagos</span>
                </div>
            </a>
            <div class="site-userbar">
                <span class="username"><?= htmlspecialchars($_SESSION['user']['username'] ?? 'Usuario') ?></span>
                <span class="role-pill">Contabilidad</span>
                <a href="index.php?controller=auth&action=logout" class="logout-btn">Cerrar Sesión</a>
            </div>
        </div>
    </header>

    <div class="contabilidad-container">

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            /* Wrap .data-table y .table-container con scroll horizontal */
            document.querySelectorAll('.data-table').forEach(function (table) {
                if (table.parentElement.classList.contains('table-scroll-wrapper')) return;
                var wrapper = document.createElement('div');
                wrapper.className = 'table-scroll-wrapper';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            });
        });
    </script>
