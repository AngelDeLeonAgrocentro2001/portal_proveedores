<?php
if (isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'index.php?controller=proveedor&action=dashboard');
    exit;
}

// El link de "olvidé mi contraseña" puede venir tanto del login de proveedor como del de
// personal interno (?origin=staff, ver login_staff.php) — hay que volver al login correcto,
// no siempre al de proveedor.
$loginUrl = ($_GET['origin'] ?? '') === 'staff'
    ? 'index.php?controller=auth&action=loginStaff'
    : 'index.php?controller=auth&action=login';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña — Portal Proveedores Agrocentro</title>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>assets/images/LogoPortaldeProveedores.png">
    <link rel="shortcut icon" href="<?= BASE_URL ?>assets/images/LogoPortaldeProveedores.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
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
    <div class="relative z-10 w-full max-w-md rounded-[2rem] shadow-2xl overflow-hidden px-8 py-10 sm:px-12 sm:py-12"
         style="background: linear-gradient(165deg, #16351F 0%, #0E1E14 100%);">

        <div class="flex items-center gap-2.5 mb-8">
            <img src="<?= BASE_URL ?>assets/images/agrocentroLogo.png" alt="Agrocentro" class="h-8 w-auto">
            <div class="leading-tight">
                <p class="text-xs font-extrabold text-white/90 tracking-wide">AGROCENTRO</p>
                <p class="text-[9px] text-white/40 uppercase tracking-widest">Portal de Proveedores</p>
            </div>
        </div>

        <h1 class="text-2xl font-extrabold text-white">Recuperar Contraseña</h1>
        <p class="text-sm text-white/45 mt-1 mb-8">Ingresa tu correo electrónico y te ayudaremos a recuperar tu cuenta</p>

        <div id="errorMessage" class="mb-4 hidden items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-300 rounded-lg px-4 py-3 text-sm">
            <span></span>
        </div>
        <div id="successMessage" class="mb-4 hidden items-center gap-3 bg-bright/10 border border-bright/30 text-bright rounded-lg px-4 py-3 text-sm">
            <span></span>
        </div>

        <form id="resetForm" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-white/50 uppercase tracking-wider mb-1.5">
                    Correo Electrónico
                </label>
                <input type="email" name="email" id="email"
                       placeholder="correo@empresa.com"
                       required autofocus
                       class="w-full px-4 py-3 rounded-lg border border-white/10 bg-white/5 text-white text-sm placeholder-white/30
                              focus:outline-none focus:border-bright focus:bg-white/10 focus:ring-2 focus:ring-bright/20
                              transition-all duration-200">
            </div>

            <button type="submit"
                    class="w-full mt-2 py-3.5 rounded-full text-white font-bold text-sm tracking-wide
                           transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-bright/20 active:translate-y-0"
                    style="background: linear-gradient(135deg, #0D7C66, #4CAF50);">
                Verificar Email
            </button>
        </form>

        <p class="mt-6 text-center text-xs text-white/40">
            <a href="<?= $loginUrl ?>" class="font-semibold text-bright/90 hover:text-bright transition-colors">← Volver al login</a>
        </p>
    </div>

    <!-- Modal para nueva contraseña -->
    <div id="passwordModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm p-5">
        <div class="w-full max-w-md rounded-2xl shadow-2xl p-8"
             style="background: linear-gradient(165deg, #16351F 0%, #0E1E14 100%);">
            <h2 class="text-xl font-extrabold text-white mb-1">Nueva Contraseña</h2>
            <p class="text-sm text-white/45 mb-6">Correo verificado — define tu nueva contraseña.</p>

            <div id="modalError" class="mb-4 hidden items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-300 rounded-lg px-4 py-3 text-sm"><span></span></div>
            <div id="modalSuccess" class="mb-4 hidden items-center gap-3 bg-bright/10 border border-bright/30 text-bright rounded-lg px-4 py-3 text-sm"><span></span></div>

            <input type="hidden" id="userEmail">

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-white/50 uppercase tracking-wider mb-1.5">Nueva Contraseña</label>
                    <input type="password" id="newPassword" placeholder="Mínimo 6 caracteres"
                           class="w-full px-4 py-3 rounded-lg border border-white/10 bg-white/5 text-white text-sm placeholder-white/30
                                  focus:outline-none focus:border-bright focus:bg-white/10 focus:ring-2 focus:ring-bright/20 transition-all duration-200">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-white/50 uppercase tracking-wider mb-1.5">Confirmar Contraseña</label>
                    <input type="password" id="confirmPassword" placeholder="Repite la contraseña"
                           class="w-full px-4 py-3 rounded-lg border border-white/10 bg-white/5 text-white text-sm placeholder-white/30
                                  focus:outline-none focus:border-bright focus:bg-white/10 focus:ring-2 focus:ring-bright/20 transition-all duration-200">
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="button" id="cancelModal"
                        class="flex-1 py-3 rounded-full text-white/70 font-semibold text-sm bg-white/5 border border-white/10 hover:bg-white/10 transition-all duration-200">
                    Cancelar
                </button>
                <button type="button" id="savePassword"
                        class="flex-1 py-3 rounded-full text-white font-bold text-sm transition-all duration-200 hover:-translate-y-0.5"
                        style="background: linear-gradient(135deg, #0D7C66, #4CAF50);">
                    Guardar
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const resetForm = document.getElementById('resetForm');
            const passwordModal = document.getElementById('passwordModal');
            const userEmail = document.getElementById('userEmail');
            const newPassword = document.getElementById('newPassword');
            const confirmPassword = document.getElementById('confirmPassword');
            const savePassword = document.getElementById('savePassword');
            const cancelModal = document.getElementById('cancelModal');
            const errorMessage = document.getElementById('errorMessage');
            const successMessage = document.getElementById('successMessage');
            const modalError = document.getElementById('modalError');
            const modalSuccess = document.getElementById('modalSuccess');

            resetForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const email = document.getElementById('email').value.trim();
                if (!email) {
                    showBox(errorMessage, 'Por favor ingresa tu email');
                    return;
                }

                try {
                    const formData = new FormData();
                    formData.append('email', email);

                    const response = await fetch('index.php?controller=auth&action=resetPassword', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();

                    if (result.success) {
                        userEmail.value = email;
                        showBox(successMessage, 'Email verificado correctamente');
                        setTimeout(() => {
                            passwordModal.classList.remove('hidden');
                            passwordModal.classList.add('flex');
                        }, 800);
                    } else {
                        showBox(errorMessage, result.error || 'Error al verificar el email');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showBox(errorMessage, 'Error de conexión. Intenta de nuevo.');
                }
            });

            savePassword.addEventListener('click', async function () {
                const email = userEmail.value;
                const password = newPassword.value;
                const confirm = confirmPassword.value;

                if (!password || !confirm) {
                    showBox(modalError, 'Por favor completa ambos campos');
                    return;
                }
                if (password.length < 6) {
                    showBox(modalError, 'La contraseña debe tener al menos 6 caracteres');
                    return;
                }
                if (password !== confirm) {
                    showBox(modalError, 'Las contraseñas no coinciden');
                    return;
                }

                try {
                    const formData = new FormData();
                    formData.append('email', email);
                    formData.append('new_password', password);

                    const response = await fetch('index.php?controller=auth&action=changePassword', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();

                    if (result.success) {
                        showBox(modalSuccess, result.message || '✅ Contraseña actualizada exitosamente.');
                        setTimeout(() => {
                            window.location.href = '<?= $loginUrl ?>';
                        }, 2500);
                    } else {
                        showBox(modalError, result.error || 'Error al actualizar la contraseña');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showBox(modalError, 'Error de conexión. Intenta de nuevo.');
                }
            });

            cancelModal.addEventListener('click', function () {
                passwordModal.classList.add('hidden');
                passwordModal.classList.remove('flex');
                clearModalFields();
            });

            passwordModal.addEventListener('click', function (e) {
                if (e.target === passwordModal) {
                    passwordModal.classList.add('hidden');
                    passwordModal.classList.remove('flex');
                    clearModalFields();
                }
            });

            function showBox(box, message) {
                box.querySelector('span').textContent = message;
                box.classList.remove('hidden');
                box.classList.add('flex');
            }

            function clearModalFields() {
                newPassword.value = '';
                confirmPassword.value = '';
                modalError.classList.add('hidden');
                modalSuccess.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
