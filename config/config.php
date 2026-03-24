<?php
// ========================
// CONFIGURACIÓN GENERAL
// ========================

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', 'Angel2001');        // ← tu nueva contraseña

// Base de datos del PORTAL de Proveedores
define('DB_PORTAL', 'portal_proveedores');

// Base de datos CAJAS_CHICAS (solo para leer DTE/SAT)
define('DB_CAJAS', 'cajas_chicas');

// ========================
// OTRAS CONFIGURACIONES ÚTILES
// ========================

define('BASE_URL', 'http://localhost/portal_proveedores/public/'); // Cambia si subes a servidor
define('UPLOAD_DIR', '../public/assets/uploads/');

define('SITE_NAME', 'Portal Proveedores Agrocentro');
define('DEBUG_MODE', true);   // Pon false cuando esté en producción
?>