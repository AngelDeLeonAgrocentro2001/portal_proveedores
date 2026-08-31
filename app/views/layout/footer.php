    </div><!-- /site-main -->

    <?php $mostrarPagosFooter = in_array($_SESSION['user']['rol'] ?? '', ['admin', 'consultas'], true); ?>

    <footer class="site-footer-wrap">
        <div class="site-footer-card">

            <div class="footer-top">
                <div class="footer-brand">
                    <img src="/portal_proveedores/public/assets/images/LogoPortaldeProveedores.png" alt="Portal de Proveedores">
                    <h2>Agricultura Próspera y<br>Sostenible para Todos</h2>
                    <p>Portal de Proveedores &mdash; Agrocentro</p>
                </div>

                <div class="footer-columns">
                    <div class="footer-col">
                        <h4>Contacto</h4>
                        <a href="mailto:soporte@agrocentro.com">✉️ soporte@agrocentro.com</a>
                    </div>

                    <div class="footer-col">
                        <h4>Portal</h4>
                        <a href="/portal_proveedores/public/index.php?controller=proveedor&action=dashboard">Dashboard</a>
                        <a href="/portal_proveedores/public/index.php?controller=proveedor&action=misFacturas">Mis Facturas</a>
                        <a href="/portal_proveedores/public/index.php?controller=proveedor&action=ordenesCompra">Órdenes de Compra</a>
                        <?php if ($mostrarPagosFooter): ?>
                        <a href="/portal_proveedores/public/index.php?controller=proveedor&action=pagos">Pagos</a>
                        <?php endif; ?>
                    </div>

                    <div class="footer-col">
                        <h4>Ayuda</h4>
                        <a href="mailto:soporte@agrocentro.com">Soporte Técnico</a>
                        <a href="#">Términos y Condiciones</a>
                    </div>
                </div>
            </div>

            <hr class="footer-divider">

            <div class="footer-bottom">
                <span>&copy; <?= date('Y') ?> <strong>Agrocentro</strong> &mdash; Portal de Proveedores. Todos los derechos reservados.</span>
                <a href="#">Política de Privacidad</a>
            </div>

        </div>
    </footer>

    <style>
        .site-footer-wrap {
                /* position: relative; */
    background: linear-gradient(135deg, #1d6f3c 0%, #0D7C66 100%);
    /* padding: 40px 20px; */
    flex-shrink: 0;
        }

        .site-footer-card {
            /* max-width: 1200px; */
            margin: 0 auto;
            background: #0E1E14;
            /* border-radius: 20px; */
            padding: 36px 40px;
        }

        .footer-top {
            display: flex;
            flex-wrap: wrap;
            gap: 32px;
            justify-content: space-between;
        }

        .footer-brand { max-width: 320px; }
        .footer-brand img { height: 44px; width: auto; margin-bottom: 14px; }
        .footer-brand h2 {
            color: #ffffff;
            font-size: 1.3rem;
            font-weight: 700;
            line-height: 1.3;
            margin: 0 0 8px 0;
        }
        .footer-brand p {
            color: rgba(255,255,255,0.5);
            font-size: 0.85rem;
            margin: 0;
        }

        .footer-columns {
            display: flex;
            flex-wrap: wrap;
            gap: 40px;
        }

        .footer-col { display: flex; flex-direction: column; gap: 10px; min-width: 150px; }
        .footer-col h4 {
            color: rgba(255,255,255,0.45);
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            margin: 0 0 4px 0;
        }
        .footer-col a {
            color: rgba(255,255,255,0.85);
            font-size: 0.88rem;
            text-decoration: none;
            transition: color 0.2s;
        }
        .footer-col a:hover { color: #4CAF50; }

        .footer-divider {
            border: none;
            border-top: 1px solid rgba(255,255,255,0.12);
            margin: 30px 0 20px 0;
        }

        .footer-bottom {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: rgba(255,255,255,0.5);
        }
        .footer-bottom strong { color: #4CAF50; }
        .footer-bottom a {
            color: rgba(255,255,255,0.5);
            text-decoration: none;
        }
        .footer-bottom a:hover { color: rgba(255,255,255,0.8); }

        @media (max-width: 640px) {
            .site-footer-card { padding: 28px 22px; }
            .footer-top { gap: 24px; }
            .footer-bottom { flex-direction: column; align-items: flex-start; }
        }
    </style>

</body>
</html>
