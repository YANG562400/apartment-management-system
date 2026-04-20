<?php

require_once __DIR__ . '/app.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

if (!function_exists('send_app_email')) {
    function send_app_email(string $subject, string $htmlBody, ?string $toEmail = null, ?string $toName = null): bool
    {
        if (!app_config('smtp_enabled', false)) {
            return false;
        }

        $username = (string) app_config('smtp_username', '');
        $password = (string) app_config('smtp_password', '');
        $fromEmail = (string) app_config('smtp_from_email', '');
        $notifyEmail = $toEmail ?: (string) app_config('smtp_notify_email', '');

        if ($username === '' || $password === '' || $fromEmail === '' || $notifyEmail === '') {
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = (string) app_config('smtp_host', 'smtp.gmail.com');
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->Port = (int) app_config('smtp_port', 465);

            $secure = strtolower((string) app_config('smtp_secure', 'ssl'));
            if ($secure === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($secure === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            $mail->setFrom($fromEmail, (string) app_config('smtp_from_name', 'Apartment Management System'));
            $mail->addAddress($notifyEmail, $toName ?? '');
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->send();

            return true;
        } catch (Exception $e) {
            error_log('Mailer Error: ' . $mail->ErrorInfo);
            return false;
        }
    }
}
