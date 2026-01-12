<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\View;
use App\Core\Security;
use Throwable;

final class AdminMailLogController extends BaseController
{
    /**
     * Anzeige der Mail-Logs für Admins
     */
    public function index(): void
    {
        // Benutzer muss eingeloggt sein und die erforderliche Berechtigung haben
        Security::requireLogin();
        Security::requirePermission('mail.logs.view'); // Berechtigung für Mail-Logs anzeigen

        // Versuche, die Mail-Logs abzurufen
        try {
            $logs = Database::fetchAll(
                'SELECT 
                    ml.id,
                    ml.recipient,
                    ml.subject,
                    ml.body,
                    ml.status,
                    ml.created_at,
                    COALESCE(u.username, "System") AS sender
                 FROM mail_logs ml
                 LEFT JOIN users u ON u.id = ml.sent_by
                 ORDER BY ml.created_at DESC
                 LIMIT 200'
            );
        } catch (Throwable $e) {
            // Falls ein Fehler auftritt, zeige eine generische Abfrage ohne Benutzerinformationen
            $logs = Database::fetchAll(
                'SELECT
                    id,
                    recipient,
                    subject,
                    body,
                    status,
                    created_at,
                    "System" AS sender
                 FROM mail_logs
                 ORDER BY created_at DESC
                 LIMIT 200'
            );
        }

        // Rendere die Logs im Admin-Bereich
        View::render('admin/mail_logs/index', [
            'title' => 'Mail-Logs',
            'logs'  => $logs
        ]);
    }
}
