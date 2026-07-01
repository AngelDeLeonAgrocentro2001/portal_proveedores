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
    <div class="relative z-10 bg-white rounded-2xl shadow-2xl w-full max-w-md px-10 py-12">

        <!-- Logo -->
        <div class="flex flex-col items-center mb-8">
            <img src="../public/assets/images/agrocentroLogo.png"
                 alt="Agrocentro" class="h-20 w-auto mb-3">
            <p class="text-lg font-bold text-gray-800 tracking-wide">Agrocentro</p>
            <p class="text-xs uppercase tracking-widest text-gray-400 mt-0.5">Portal de Proveedores</p>
        </div>

        <!-- Error -->
        <?php if (isset($error)): ?>
            <div class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
                <span>⚠️</span>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="index.php?controller=auth&action=login" class="space-y-4">

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                    Código de Proveedor
                </label>
                <input type="text" name="cardcode"
                       placeholder="Ej. P-00123"
                       required autofocus
                       class="w-full px-4 py-3 rounded-lg border border-gray-200 bg-gray-50 text-gray-800 text-sm
                              focus:outline-none focus:border-teal-600 focus:bg-white focus:ring-2 focus:ring-teal-600/10
                              transition-all duration-200">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                    Correo Electrónico
                </label>
                <input type="email" name="email"
                       placeholder="correo@empresa.com"
                       required
                       class="w-full px-4 py-3 rounded-lg border border-gray-200 bg-gray-50 text-gray-800 text-sm
                              focus:outline-none focus:border-teal-600 focus:bg-white focus:ring-2 focus:ring-teal-600/10
                              transition-all duration-200">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                    Contraseña
                </label>
                <input type="password" name="password"
                       placeholder="••••••••"
                       required
                       class="w-full px-4 py-3 rounded-lg border border-gray-200 bg-gray-50 text-gray-800 text-sm
                              focus:outline-none focus:border-teal-600 focus:bg-white focus:ring-2 focus:ring-teal-600/10
                              transition-all duration-200">
            </div>

            <button type="submit"
                    class="w-full mt-2 py-3.5 rounded-lg text-white font-bold text-sm tracking-wide
                           transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg active:translate-y-0"
                    style="background: linear-gradient(135deg, #0D7C66, #1d6f3c);">
                Ingresar al Portal
            </button>
        </form>

        <!-- Links -->
        <div class="mt-6 text-center text-xs text-gray-400 space-x-2">
            <a href="#" class="hover:text-teal-700 transition-colors font-medium" style="color:#0D7C66;">
                Olvidé mi contraseña
            </a>
            <span class="text-gray-300">|</span>
            <a href="#" class="hover:text-teal-700 transition-colors font-medium" style="color:#0D7C66;">
                Activar usuario
            </a>
        </div>

    </div>
</body>
</html>
