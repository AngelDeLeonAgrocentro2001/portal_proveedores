<?php


// ========== BASE DE DATOS LOCAL ==========
define('DB_HOST_LOCAL', '127.0.0.1');
define('DB_USER_LOCAL', 'root');
define('DB_PASS_LOCAL', 'Angel2001');

// ========== BASE DE DATOS PRODUCCIÓN (REMOTA) ==========
define('DB_HOST_PROD', '127.0.0.1');  
define('DB_USER_PROD', 'root');           
define('DB_PASS_PROD', 'agrotransporte2025'); 

// ========== NOMBRES DE BASES DE DATOS ==========
define('DB_PORTAL', 'portal_proveedores');
define('DB_CAJAS',  'cajas_chicas');    

// ========== CONFIGURACIÓN GENERAL ==========
define('BASE_URL', 'http://localhost:8080/portal_proveedores/public/');
define('DEBUG_MODE', true);
define('SITE_NAME', 'Portal Proveedores Agrocentro');

error_reporting(E_ALL);
ini_set('display_errors', DEBUG_MODE ? 1 : 0);