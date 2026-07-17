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
    <title>Portal Proveedores — Agrocentro</title>
    <link rel="icon" type="image/x-icon" href="../public/assets/images/LogoPortaldeProveedores.png">
    <link rel="shortcut icon" href="../public/assets/images/LogoPortaldeProveedores.png">
    <link rel="stylesheet" href="../public/assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary:      '#1d6f3c',
                        'primary-dark':'#155a30',
                        teal:         '#0D7C66',
                        bright:       '#4CAF50',
                        'dark-bg':    '#0E1E14',
                    }
                }
            }
        }
    </script>
</head>

<body class="min-h-screen flex items-center justify-center p-5 relative overflow-hidden"
      style="background: linear-gradient(135deg, #0E1E14 0%, #1d6f3c 55%, #0a3d22 100%);">

    <!-- Decorative circles -->
    <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full opacity-10"
         style="background: radial-gradient(circle, #4CAF50, transparent)"></div>
    <div class="absolute -bottom-20 -left-20 w-72 h-72 rounded-full opacity-10"
         style="background: radial-gradient(circle, #0D7C66, transparent)"></div>

    <!-- Card -->
    <div class="relative z-10 w-full max-w-4xl rounded-[2rem] shadow-2xl overflow-hidden flex flex-col md:flex-row"
         style="background:#0E1E14; min-height: 620px;">

        <!-- Left photo panel -->
        <div class="relative hidden md:block md:w-[46%] shrink-0 overflow-hidden">
            <img src="../public/assets/images/imagenlogin.jpg" alt="Agrocentro"
                 class="absolute inset-0 w-full h-full object-cover">
            <div class="absolute inset-0" style="background: linear-gradient(180deg, rgba(14,30,20,0.15) 0%, rgba(14,30,20,0.05) 40%, rgba(14,30,20,0.55) 100%);"></div>

            <!-- brand badge -->
            <div class="absolute top-7 left-7 flex items-center gap-2.5 z-10 bg-white/90 backdrop-blur-sm rounded-xl px-3 py-2 shadow-sm">
                <img src="../public/assets/images/agrocentroLogo.png" alt="Agrocentro" class="h-7 w-auto">
                <div class="leading-tight">
                    <p class="text-[11px] font-extrabold text-gray-700 tracking-wide">AGROCENTRO</p>
                    <p class="text-[9px] text-gray-400 uppercase tracking-widest">Portal de Proveedores</p>
                </div>
            </div>

            <!-- tagline -->
            <div class="absolute bottom-7 left-7 right-7 z-10">
                <p class="text-white text-lg font-bold leading-snug drop-shadow">
                    Agricultura Próspera y<br>Sostenible para Todos
                </p>
            </div>
        </div>

        <!-- Right form panel -->
        <div class="flex-1 px-8 py-10 sm:px-12 sm:py-12 flex flex-col justify-center"
             style="background: linear-gradient(165deg, #16351F 0%, #0E1E14 100%);">

            <!-- mobile-only brand (left panel hidden below md) -->
            <div class="flex md:hidden items-center gap-2.5 mb-8">
                <img src="../public/assets/images/agrocentroLogo.png" alt="Agrocentro" class="h-8 w-auto">
                <div class="leading-tight">
                    <p class="text-xs font-extrabold text-white/90 tracking-wide">AGROCENTRO</p>
                    <p class="text-[9px] text-white/40 uppercase tracking-widest">Portal de Proveedores</p>
                </div>
            </div>

            <h1 class="text-3xl font-extrabold text-white">Iniciar Sesión</h1>
            <p class="text-sm text-white/45 mt-1 mb-8">Ingresa tus credenciales para acceder al portal</p>

            <!-- Error -->
            <?php if (isset($error)): ?>
                <div class="mb-5 flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-300 rounded-lg px-4 py-3 text-sm">
                    <span>⚠️</span>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" action="index.php?controller=auth&action=login" class="space-y-4">

                <div>
                    <label class="block text-xs font-semibold text-white/50 uppercase tracking-wider mb-1.5">
                        Código de Proveedor
                    </label>
                    <input type="text" name="cardcode"
                           placeholder="Ej. P-00123"
                           required autofocus
                           class="w-full px-4 py-3 rounded-lg border border-white/10 bg-white/5 text-white text-sm placeholder-white/30
                                  focus:outline-none focus:border-bright focus:bg-white/10 focus:ring-2 focus:ring-bright/20
                                  transition-all duration-200">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-white/50 uppercase tracking-wider mb-1.5">
                        Correo Electrónico
                    </label>
                    <input type="email" name="email"
                           placeholder="correo@empresa.com"
                           required
                           class="w-full px-4 py-3 rounded-lg border border-white/10 bg-white/5 text-white text-sm placeholder-white/30
                                  focus:outline-none focus:border-bright focus:bg-white/10 focus:ring-2 focus:ring-bright/20
                                  transition-all duration-200">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-semibold text-white/50 uppercase tracking-wider">
                            Contraseña
                        </label>
                        <a href="#" class="text-[11px] font-medium text-bright/80 hover:text-bright transition-colors">
                            ¿Olvidé mi contraseña?
                        </a>
                    </div>
                    <input type="password" name="password"
                           placeholder="••••••••"
                           required
                           class="w-full px-4 py-3 rounded-lg border border-white/10 bg-white/5 text-white text-sm placeholder-white/30
                                  focus:outline-none focus:border-bright focus:bg-white/10 focus:ring-2 focus:ring-bright/20
                                  transition-all duration-200">
                </div>

                <button type="submit"
                        class="w-full mt-2 py-3.5 rounded-full text-white font-bold text-sm tracking-wide
                               transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-bright/20 active:translate-y-0"
                        style="background: linear-gradient(135deg, #0D7C66, #4CAF50);">
                    Ingresar al Portal
                </button>
            </form>

            <!-- Links -->
            <p class="mt-6 text-center text-xs text-white/40">
                ¿No tienes cuenta?
                <a href="#" class="font-semibold text-bright/90 hover:text-bright transition-colors">Activar usuario</a>
            </p>

            <div class="mt-6 pt-5 border-t border-white/10 flex items-center justify-between text-[11px] text-white/30">
                <a href="#" class="hover:text-white/60 transition-colors">Términos y condiciones</a>
                <span class="text-right">
                    ¿Problemas para ingresar?<br class="hidden sm:block">
                    <a href="mailto:soporte@agrocentro.com" class="text-white/50 hover:text-white/70 transition-colors">soporte@agrocentro.com</a>
                </span>
            </div>
        </div>
    </div>
</body>
</html>
