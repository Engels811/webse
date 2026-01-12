<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

final class AdminModerationController
{
    /* =========================================================
       ROLE GUARD – MODERATION
       Zugriff: support | moderator | admin | superadmin | owner
    ========================================================= */

    private function requireModerator(): void
    {
        if (empty($_SESSION['user']) || empty($_SESSION['user']['role'])) {
            http_response_code(403);
            View::render('errors/403', [
                'title' => 'Zugriff verweigert'
            ]);
            exit;
        }

        $role = $_SESSION['user']['role'];

        // Moderationszugriff (Team)
        if (!in_array($role, ['support', 'moderator', 'admin', 'superadmin', 'owner'], true)) {
            http_response_code(403);
            View::render('errors/403', [
                'title' => 'Zugriff verweigert'
            ]);
            exit;
        }
    }

    /* =========================================================
       INDEX – MODERATION DASHBOARD
    ========================================================= */

    public function index(): void
    {
        $this->requireModerator();

        $logs = Database::fetchAll(
            'SELECT
                l.*,
                u.username AS moderator
             FROM forum_moderation_log l
             JOIN users u ON u.id = l.moderator_id
             ORDER BY l.created_at DESC
             LIMIT 200'
        ) ?? [];

        $deletedPosts = Database::fetchAll(
            'SELECT
                p.id,
                p.thread_id,
                p.content,
                p.deleted_at,
                u.username
             FROM forum_posts p
             JOIN users u ON u.id = p.user_id
             WHERE p.is_deleted = 1
             ORDER BY p.deleted_at DESC'
        ) ?? [];

        View::render('admin/moderation/index', [
            'title'        => 'Moderations-Panel',
            'logs'         => $logs,
            'deletedPosts' => $deletedPosts
        ]);
    }
}
