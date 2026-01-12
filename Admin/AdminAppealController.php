<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Database;

class AdminAppealController
{
    /**
     * GET /admin/appeals
     * Übersicht aller Einsprüche
     */
    public function index(): void
    {
        Security::requireAdmin();

        $appeals = Database::fetchAll(
            "SELECT
                ua.*,
                u.username
             FROM user_appeals ua
             LEFT JOIN users u ON u.id = ua.user_id
             ORDER BY ua.created_at DESC"
        );

        View::render('admin/appeals/index', [
            'title'   => 'Einsprüche',
            'appeals' => $appeals
        ]);
    }

    /**
     * POST /admin/appeals/{id}/approve
     * Einspruch annehmen
     */
    public function approve(int $id): void
    {
        Security::requireAdmin();
        Security::checkCsrf();

        $appeal = Database::fetch(
            "SELECT ua.*, u.username
             FROM user_appeals ua
             LEFT JOIN users u ON u.id = ua.user_id
             WHERE ua.id = ?
             LIMIT 1",
            [$id]
        );

        if (!$appeal) {
            header('Location: /admin/appeals');
            exit;
        }

        // Zugehörige Aktion deaktivieren (Ban / Mute etc.)
        if (!empty($appeal['action_id'])) {
            Database::execute(
                "UPDATE user_actions
                 SET active = 0
                 WHERE id = ?",
                [$appeal['action_id']]
            );
        }

        Database::execute(
            "UPDATE user_appeals
             SET status = 'approved',
                 resolved_by = ?,
                 resolved_at = NOW()
             WHERE id = ?",
            [
                $_SESSION['user']['username'],
                $id
            ]
        );

        UserNotification::create(
            $appeal['username'],
            'Einspruch angenommen',
            'Deine Sperre wurde aufgehoben.',
            'success'
        );

        $_SESSION['flash_success'] = 'Einspruch wurde angenommen.';
        header('Location: /admin/appeals');
        exit;
    }

    /**
     * POST /admin/appeals/{id}/reject
     * Einspruch ablehnen
     */
    public function reject(int $id): void
    {
        Security::requireAdmin();
        Security::checkCsrf();

        $appeal = Database::fetch(
            "SELECT ua.*, u.username
             FROM user_appeals ua
             LEFT JOIN users u ON u.id = ua.user_id
             WHERE ua.id = ?
             LIMIT 1",
            [$id]
        );

        if (!$appeal) {
            header('Location: /admin/appeals');
            exit;
        }

        Database::execute(
            "UPDATE user_appeals
             SET status = 'rejected',
                 resolved_by = ?,
                 resolved_at = NOW()
             WHERE id = ?",
            [
                $_SESSION['user']['username'],
                $id
            ]
        );

        UserNotification::create(
            $appeal['username'],
            'Einspruch abgelehnt',
            'Dein Einspruch wurde abgelehnt.',
            'error'
        );

        $_SESSION['flash_success'] = 'Einspruch wurde abgelehnt.';
        header('Location: /admin/appeals');
        exit;
    }
}
