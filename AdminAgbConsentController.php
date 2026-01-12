<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Security;
use App\Core\Database;

final class AdminAgbConsentController
{
    /* =========================================================
       ROLE GUARD – NUR ROLLEN
       Zugriff: admin, superadmin, owner
    ========================================================= */

    private function requireAdmin(): void
    {
        if (empty($_SESSION['user']) || empty($_SESSION['user']['role'])) {
            http_response_code(403);
            View::render('errors/403', ['title' => 'Zugriff verweigert']);
            exit;
        }

        $role = $_SESSION['user']['role'];

        if (!in_array($role, ['admin', 'superadmin', 'owner'], true)) {
            http_response_code(403);
            View::render('errors/403', ['title' => 'Zugriff verweigert']);
            exit;
        }
    }

    /* =========================================================
       INDEX – AGB-ZUSTIMMUNGEN
    ========================================================= */

    public function index(): void
    {
        $this->requireAdmin();

        $consents = Database::fetchAll(
            "SELECT
                username,
                email,
                agb_version AS version,
                agb_accepted_at AS accepted_at
             FROM users
             WHERE agb_accepted_at IS NOT NULL
             ORDER BY agb_accepted_at DESC"
        ) ?? [];

        View::render('admin/agb/consents', [
            'title'    => 'AGB-Zustimmungen',
            'consents' => $consents
        ]);
    }
}
