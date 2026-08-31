<?php
// app/models/MailerService.php
// Envío de correo vía SMTP (PHPMailer), usando el mismo patrón de require directo
// que ya usa este proyecto para TCPDF (sin pasar por vendor/autoload.php).
require_once BASE_PATH . 'vendor/phpmailer/phpmailer/src/Exception.php';
require_once BASE_PATH . 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once BASE_PATH . 'vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class MailerService {

    // Envía un correo, opcionalmente con un archivo adjunto. Devuelve true/false;
    // el detalle del error (si falla) queda en error_log, no se expone al usuario final.
    public static function enviarConAdjunto($destinatarioEmail, $destinatarioNombre, $asunto, $cuerpoHtml, $cuerpoTexto, $rutaAdjunto = null, $nombreAdjunto = null) {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Port = SMTP_PORT;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = 15;

            $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $mail->addAddress($destinatarioEmail, $destinatarioNombre ?: $destinatarioEmail);

            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body = $cuerpoHtml;
            $mail->AltBody = $cuerpoTexto;

            if ($rutaAdjunto && file_exists($rutaAdjunto)) {
                $mail->addAttachment($rutaAdjunto, $nombreAdjunto ?: basename($rutaAdjunto));
            }

            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            error_log("MailerService - Error enviando correo a $destinatarioEmail: " . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            error_log("MailerService - Excepción inesperada enviando correo a $destinatarioEmail: " . $e->getMessage());
            return false;
        }
    }
}
