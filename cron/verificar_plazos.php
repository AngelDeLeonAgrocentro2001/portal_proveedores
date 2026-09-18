<?php
// cron/verificar_plazos.php
//
// Acuerdo (cierre de mes): "Los proveedores podrán ingresar facturas hasta el último día del
// mes. Los gerentes (Compras) tendrán 2 días hábiles adicionales para autorizar. Si después de
// esos 2 días la factura sigue sin autorizarse, pasa a rechazada y se notifica al proveedor
// (correo + aviso en la aplicación)."
//
// Este script se ejecuta una vez al día vía cron/Tarea Programada — no hay nada equivalente ya
// en el proyecto, es la primera tarea programada del portal. Revisa las facturas que todavía
// están pendientes de decisión de Compras y, si ya pasó la fecha límite de cierre de mes
// (FacturaModel::fechaLimiteCierreMes), las rechaza automáticamente con el mismo mecanismo que
// usa AdminController::rechazarFacturaCompras() (libera el/los DTE en cajas_chicas para que el
// proveedor pueda volver a reportar), y envía el correo de aviso al proveedor. El aviso "en la
// aplicación" es el banner que ya se muestra en su dashboard consultando rechazo_automatico=1
// (no requiere nada más de este script).
//
// Uso manual / prueba: php cron/verificar_plazos.php
//
// Producción (Linux) — agregar a crontab, una vez al día por la madrugada:
//   0 6 * * * /usr/bin/php /var/www/portal_proveedores/cron/verificar_plazos.php >> /var/www/portal_proveedores/logs/cron_verificar_plazos.log 2>&1
//
// Windows (Tarea Programada, solo si se quiere probar en el servidor local):
//   schtasks /create /tn "PortalProveedores - Verificar plazos" /tr "php C:\xampp\htdocs\portal_proveedores\cron\verificar_plazos.php" /sc daily /st 06:00

define('BASE_PATH', dirname(__DIR__) . '/');

require_once BASE_PATH . 'config/config.php';
require_once BASE_PATH . 'database/DatabasePortal.php';
require_once BASE_PATH . 'database/DatabaseCajas.php';
require_once BASE_PATH . 'app/models/FacturaModel.php';
require_once BASE_PATH . 'app/models/MailerService.php';

function verificarPlazosCierreMes()
{
    $pdo = DatabasePortal::getInstance()->getPdo();

    echo '[' . date('Y-m-d H:i:s') . "] Iniciando verificación de plazos de fin de mes...\n";

    // Facturas todavía pendientes de decisión de Compras (ni aprobadas ni rechazadas).
    $stmt = $pdo->prepare("
        SELECT f.id, f.numero_factura, f.fecha_emision, f.cardcode, f.id_usuario, p.nit, p.nombre as proveedor_nombre
        FROM facturas f
        JOIN proveedores p ON f.cardcode = p.cardcode
        WHERE f.estado IN ('reportada', 'validada', 'revision_compras')
    ");
    $stmt->execute();
    $pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalRechazadas = 0;

    foreach ($pendientes as $factura) {
        $fechaLimite = FacturaModel::fechaLimiteCierreMes($factura['fecha_emision']);
        if (date('Y-m-d') <= $fechaLimite) {
            continue; // todavía dentro de plazo, no se toca
        }

        echo "  Rechazando factura #{$factura['id']} ({$factura['numero_factura']}) — venció el $fechaLimite\n";

        $pdo->beginTransaction();
        try {
            $motivo = 'Rechazada automáticamente: no fue autorizada por Compras dentro de los 2 días '
                . 'hábiles posteriores al cierre de mes (venció el ' . date('d/m/Y', strtotime($fechaLimite)) . ').';

            // Liberar el DTE principal, igual que en el rechazo manual (AdminController::
            // rechazarFacturaCompras), para que el proveedor pueda volver a reportarlo.
            $partes = explode(' ', trim($factura['numero_factura']), 2);
            $serie = trim($partes[0] ?? '');
            $numero_dte = trim($partes[1] ?? $factura['numero_factura']);
            if ($serie && $numero_dte && !empty($factura['nit'])) {
                try {
                    $dbCajas = DatabaseCajas::getInstance()->getPdo();
                    $stmtDte = $dbCajas->prepare("UPDATE dte SET usado = 'X' WHERE nit_emisor = ? AND serie = ? AND numero_dte = ?");
                    $stmtDte->execute([$factura['nit'], $serie, $numero_dte]);
                } catch (Exception $e) {
                    error_log("verificar_plazos: error liberando DTE de factura {$factura['id']}: " . $e->getMessage());
                }
            }

            // Liberar facturas adicionales (doble factura), mismo patrón que el rechazo manual.
            $stmtAd = $pdo->prepare("SELECT * FROM facturas_adicionales WHERE factura_id = ?");
            $stmtAd->execute([$factura['id']]);
            $adicionales = $stmtAd->fetchAll(PDO::FETCH_ASSOC);
            foreach ($adicionales as $adicional) {
                if (!empty($adicional['numero_dte']) && !empty($adicional['serie']) && !empty($adicional['nit_proveedor'])) {
                    try {
                        $dbCajas = DatabaseCajas::getInstance()->getPdo();
                        $stmtDteAd = $dbCajas->prepare("UPDATE dte SET usado = 'X' WHERE nit_emisor = ? AND serie = ? AND numero_dte = ?");
                        $stmtDteAd->execute([$adicional['nit_proveedor'], $adicional['serie'], $adicional['numero_dte']]);
                    } catch (Exception $e) {
                        error_log("verificar_plazos: error liberando DTE adicional de factura {$factura['id']}: " . $e->getMessage());
                    }
                }
                try {
                    $stmtUpdAd = $pdo->prepare("UPDATE facturas_adicionales SET liberada = 1, fecha_liberacion = NOW(), motivo_liberacion = ? WHERE id = ? AND liberada = 0");
                    $stmtUpdAd->execute([$motivo, $adicional['id']]);
                } catch (Exception $e) {
                    error_log("verificar_plazos: error marcando factura adicional liberada (factura {$factura['id']}): " . $e->getMessage());
                }
            }

            $stmtRechazo = $pdo->prepare("
                UPDATE facturas
                SET estado = 'rechazada_compras',
                    contrasena_pago = NULL,
                    contrasena_cancelada = 1,
                    motivo_cancelacion = ?,
                    fecha_cancelacion = NOW(),
                    rechazado_por = 'Sistema (automático)',
                    fecha_rechazo = NOW(),
                    motivo_rechazo = ?,
                    rechazo_automatico = 1
                WHERE id = ?
            ");
            $stmtRechazo->execute([$motivo, $motivo, $factura['id']]);

            $pdo->commit();
            $totalRechazadas++;

            notificarProveedorRechazoAutomatico($pdo, $factura);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("verificar_plazos: error rechazando factura {$factura['id']}: " . $e->getMessage());
            echo "  ERROR rechazando factura #{$factura['id']}: " . $e->getMessage() . "\n";
        }
    }

    echo '[' . date('Y-m-d H:i:s') . "] Verificación terminada. Facturas rechazadas: $totalRechazadas.\n";
}

// Correo al proveedor cuando su factura se rechaza automáticamente por vencimiento de plazo —
// el aviso "en la aplicación" es el banner del dashboard (ProveedorController::dashboard()),
// que se calcula aparte consultando rechazo_automatico=1, no requiere nada de este script.
function notificarProveedorRechazoAutomatico($pdo, $factura)
{
    try {
        $email = null;
        $nombre = $factura['proveedor_nombre'];

        if (!empty($factura['id_usuario'])) {
            $stmtU = $pdo->prepare("SELECT email, username FROM usuarios WHERE id = ?");
            $stmtU->execute([$factura['id_usuario']]);
            $u = $stmtU->fetch(PDO::FETCH_ASSOC);
            if ($u) {
                $email = $u['email'];
                $nombre = $u['username'];
            }
        }
        if (!$email) {
            // Respaldo: el usuario administrador de ese proveedor.
            $stmtU = $pdo->prepare("SELECT email, username FROM usuarios WHERE cardcode = ? AND rol = 'admin' LIMIT 1");
            $stmtU->execute([$factura['cardcode']]);
            $u = $stmtU->fetch(PDO::FETCH_ASSOC);
            if ($u) {
                $email = $u['email'];
                $nombre = $u['username'];
            }
        }
        if (!$email) {
            error_log("verificar_plazos: no se encontró email para notificar al proveedor de la factura {$factura['id']}");
            return;
        }

        $urlPortal = BASE_URL . 'index.php?controller=proveedor&action=misFacturas';
        $asunto = '❌ Factura rechazada por vencimiento de plazo — ' . $factura['numero_factura'];
        $cuerpoHtml = "
            <div style='font-family: Arial, sans-serif; max-width:600px; margin:0 auto; color:#333;'>
                <h2 style='color:#c0392b;'>Factura rechazada automáticamente</h2>
                <p>Hola <strong>" . htmlspecialchars($nombre) . "</strong>,</p>
                <p>Tu factura <strong>" . htmlspecialchars($factura['numero_factura']) . "</strong> fue rechazada automáticamente porque no fue autorizada dentro de los 2 días hábiles posteriores al cierre de mes.</p>
                <p>Puedes volver a reportarla desde el portal:</p>
                <p><a href='" . htmlspecialchars($urlPortal) . "' style='color:#0D7C66;'>Ir a Mis Facturas</a></p>
                <div style='margin-top:20px; padding-top:20px; border-top:1px solid #ddd; font-size:12px; color:#666;'>
                    <p>Este es un mensaje automático, por favor no respondas.</p>
                    <p>Agrocentro &copy; " . date('Y') . "</p>
                </div>
            </div>
        ";
        $cuerpoTexto = "Hola $nombre,\n\nTu factura {$factura['numero_factura']} fue rechazada automáticamente porque no fue autorizada dentro de los 2 días hábiles posteriores al cierre de mes.\n\nPuedes volver a reportarla en: $urlPortal";

        MailerService::enviarConAdjunto($email, $nombre, $asunto, $cuerpoHtml, $cuerpoTexto);
    } catch (Exception $e) {
        error_log("verificar_plazos: error notificando al proveedor (factura {$factura['id']}): " . $e->getMessage());
    }
}

verificarPlazosCierreMes();
