<?php
// mail-smtp.php — sends email via PHPMailer, talking to Gmail's SMTP
// server. No Composer needed: this includes PHPMailer's three source
// files directly from the PHPMailer/ folder that ships alongside this
// project (see mail-config.php for where those files need to sit).

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Send a single HTML email via Gmail's SMTP server, using PHPMailer.
 *
 * @return array{0: bool, 1: string} [success, error message ('' on success)]
 */
function smtp_send(string $toEmail, string $toName, string $subject, string $htmlBody): array
{
    $mail = new PHPMailer(true); // true = throw exceptions on error, caught below

    try {
        // ---- Server settings ----
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = str_replace(' ', '', SMTP_APP_PASSWORD); // Google shows it with spaces; strip them
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // implicit TLS, matches port 465
        $mail->CharSet    = 'UTF-8';

        // ---- Message ----
        $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;

        $mail->send();
        return [true, ''];
    } catch (PHPMailerException $e) {
        return [false, 'PHPMailer error: ' . $mail->ErrorInfo];
    }
}
