<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/PHPMailerPHPAutoload.php';

/**
 * Send an account-security alert using the optional SMTP transport.
 *
 * @return array{ok:bool,error?:string}
 */
function send_security_alert_email(string $recipient, string $subject, string $message): array
{
    if (
        SECURITY_MAIL_HOST === ''
        || SECURITY_MAIL_FROM === ''
        || SECURITY_MAIL_USERNAME === ''
        || SECURITY_MAIL_PASSWORD === ''
        || !filter_var($recipient, FILTER_VALIDATE_EMAIL)
        || !filter_var(SECURITY_MAIL_FROM, FILTER_VALIDATE_EMAIL)
    ) {
        error_log('Security alert email not sent: SMTP is not configured or an email address is invalid.');
        return ['ok' => false, 'error' => 'Security alert email is not configured.'];
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SECURITY_MAIL_HOST;
        $mail->Port = SECURITY_MAIL_PORT;
        $mail->SMTPAuth = true;
        $mail->Username = SECURITY_MAIL_USERNAME;
        $mail->Password = SECURITY_MAIL_PASSWORD;
        if (SECURITY_MAIL_ENCRYPTION === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif (SECURITY_MAIL_ENCRYPTION === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif (SECURITY_MAIL_ENCRYPTION !== '') {
            error_log('Security alert email not sent: unsupported SMTP encryption setting.');
            return ['ok' => false, 'error' => 'Security alert email encryption is misconfigured.'];
        }
        $mail->CharSet = PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $mail->setFrom(SECURITY_MAIL_FROM, SECURITY_MAIL_FROM_NAME);
        $mail->addAddress($recipient);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $mail->Body = '<!doctype html><html lang="en"><body style="margin:0;background:#f4f5fb;padding:32px 16px;font-family:Arial,sans-serif;color:#171827">'
            . '<main style="max-width:560px;margin:auto;background:#fff;border:1px solid #e5e7f0;border-radius:16px;padding:28px">'
            . '<p style="margin:0 0 18px;color:#5146c8;font-weight:bold">' . $escape(APP_NAME) . ' security</p>'
            . '<h1 style="margin:0 0 12px;font-size:22px">' . $escape($subject) . '</h1>'
            . '<p style="margin:0;line-height:1.65;color:#4b5065">' . nl2br($escape($message)) . '</p>'
            . '<p style="margin:24px 0 0;color:#6b7084;font-size:13px">If you did not make this change, secure your account and contact Nerasky support.</p>'
            . '</main></body></html>';
        $mail->AltBody = $subject . "\n\n" . $message
            . "\n\nIf you did not make this change, secure your account and contact Nerasky support.";
        $mail->send();
        return ['ok' => true];
    } catch (PHPMailer\PHPMailer\Exception $error) {
        error_log('Security alert email delivery failed: ' . $error->getMessage());
        return ['ok' => false, 'error' => 'Security alert email could not be sent.'];
    }
}
