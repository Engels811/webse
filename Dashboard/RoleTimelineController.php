<?php
declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Core\Security;
use App\Core\View;
use App\Core\Database;

final class RoleTimelineController
{
    public function index(): void
    {
        Security::requireLogin();

        $userId = (int)($_SESSION['user']['id'] ?? 0);

        $timeline = Database::fetchAll(
            "SELECT
                h.created_at,
                r_old.label AS old_role,
                r_new.label AS new_role,
                u.username AS changed_by
             FROM user_role_history h
             LEFT JOIN roles r_old ON r_old.id = h.old_role_id
             JOIN roles r_new ON r_new.id = h.new_role_id
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.user_id = ?
             ORDER BY h.created_at DESC",
            [$userId]
        ) ?? [];

        View::render('dashboard/role_timeline', [
            'title'    => 'Rollenverlauf',
            'timeline' => $timeline
        ]);
    }
}
