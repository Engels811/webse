<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\View;
use App\Core\Security;

final class AdminMailController extends BaseController
{
    /**
     * Rollen, die Admin-Mails sehen / senden dürfen
     */
    private array $mailRoles = [
        'support',
        'moderator',
        'admin',
        'superadmin',
        'owner'
    ];

    /* =====================================================
       SEITE: ADMIN MAILS
    ===================================================== */
    public function index(): void
    {
        Security::requireLogin();
        Security::require('mail.templates.manage'); // Berechtigungsprüfung

        $templates = Database::fetchAll(
            'SELECT id, name
             FROM mail_templates
             WHERE is_active = 1
             ORDER BY name ASC'
        ) ?? [];

        View::render('admin/mail/index', [
            'title'     => 'Admin-Mails',
            'templates' => $templates
        ]);
    }

    /* =====================================================
       AJAX: TEMPLATE LADEN (JSON)
    ===================================================== */
    public function template(): void
    {
        Security::requireLogin();
        Security::require('mail.templates.manage'); // Berechtigungsprüfung

        try {
            // 🔇 Kein Layout / kein HTML
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            header('Content-Type: application/json; charset=utf-8');

            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['error' => 'invalid_id']);
                exit;
            }

            $rows = Database::fetchAll(
                'SELECT subject, body
                 FROM mail_templates
                 WHERE id = ? AND is_active = 1
                 LIMIT 1',
                [$id]
            );

            $tpl = $rows[0] ?? null;

            if (!$tpl) {
                echo json_encode(['error' => 'template_not_found']);
                exit;
            }

            echo json_encode([
                'subject' => (string)$tpl['subject'],
                'body'    => (string)$tpl['body'],
            ], JSON_UNESCAPED_UNICODE);

            exit;

        } catch (\Throwable $e) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);

            echo json_encode([
                'error'   => 'internal_error',
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }

    /* =====================================================
       SENDEN: PHPMailer + SMTP (ohne Composer)
    ===================================================== */
    public function send(): void
    {
        Security::requireLogin();
        Security::require('mail.templates.manage'); // Berechtigungsprüfung
        Security::checkCsrf();

        // 🔇 Kein Layout / kein HTML
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/json; charset=utf-8');

        try {
            /* =========================
               INPUT
            ========================= */
            $recipient = trim($_POST['recipient'] ?? '');
            $subject   = trim($_POST['subject'] ?? '');
            $htmlBody  = trim($_POST['compiled_html'] ?? '');
            $senderId  = $_SESSION['user']['id'] ?? null;

            if ($recipient === '' || $subject === '' || $htmlBody === '') {
                echo json_encode([
                    'error'   => 'validation_failed',
                    'message' => 'Empfänger, Betreff oder Inhalt fehlt'
                ]);
                exit;
            }

            /* =========================
               EMPFÄNGER AUFLÖSEN
               (Username oder E-Mail)
            ========================= */
            if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $email = $recipient;
            } else {
                $email = Database::fetchColumn(
                    'SELECT email FROM users WHERE username = ? LIMIT 1',
                    [$recipient]
                );

                if (!$email) {
                    echo json_encode([
                        'error'   => 'recipient_not_found',
                        'message' => 'Empfänger nicht gefunden'
                    ]);
                    exit;
                }
            }

            /* =========================
               ENV (MAIL_* KONFIGURATION)
            ========================= */
            $env = $_ENV + $_SERVER;

            $host     = $env['MAIL_HOST'] ?? null;
            $port     = (int)($env['MAIL_PORT'] ?? 465);
            $user     = $env['MAIL_USERNAME'] ?? null;
            $pass     = $env['MAIL_PASSWORD'] ?? null;
            $secure   = $env['MAIL_ENCRYPTION'] ?? 'ssl';
            $from     = $env['MAIL_FROM_ADDRESS'] ?? $user;
            $fromName = $env['MAIL_FROM_NAME'] ?? 'Mailer';
            $replyTo  = $env['MAIL_REPLY_TO'] ?? null;
            $timeout  = (int)($env['MAIL_TIMEOUT'] ?? 30);
            $debug    = ($env['MAIL_DEBUG'] ?? 'false') === 'true';

            if (!$host || !$user || !$pass) {
                echo json_encode([
                    'error'   => 'smtp_not_configured',
                    'message' => 'MAIL_* Konfiguration fehlt'
                ]);
                exit;
            }

            /* =========================
               PHPMailer (manuell)
            ========================= */
            require_once BASE_PATH . '/app/Lib/PHPMailer/Exception.php';
            require_once BASE_PATH . '/app/Lib/PHPMailer/PHPMailer.php';
            require_once BASE_PATH . '/app/Lib/PHPMailer/SMTP.php';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            if ($debug) {
                $mail->SMTPDebug = 2;
            }

            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $user;
            $mail->Password   = $pass;
            $mail->Port       = $port;
            $mail->SMTPSecure = $secure;
            $mail->Timeout    = $timeout;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($from, $fromName);
            $mail->addAddress($email);

            if ($replyTo) {
                $mail->addReplyTo($replyTo);
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);

            /* =========================
               SENDEN
            ========================= */
            $mail->send();
            $status = 'sent';

        } catch (\Throwable $e) {
            $status = 'failed';
            $errorMessage = $e->getMessage();
        }

        /* =========================
           MAIL LOG
        ========================= */
        Database::execute(
            'INSERT INTO mail_logs
             (recipient, subject, body, sent_by, status, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())',
            [
                $email ?? $recipient,
                $subject ?? '',
                $htmlBody ?? '',
                $senderId,
                $status
            ]
        );

        /* =========================
           RESPONSE
        ========================= */
        if ($status === 'sent') {
            echo json_encode([
                'success' => true,
                'message' => 'Mail erfolgreich gesendet'
            ]);
        } else {
            echo json_encode([
                'error'   => 'mail_failed',
                'message' => $errorMessage ?? 'Unbekannter Fehler'
            ]);
        }

        exit;
    }
}
