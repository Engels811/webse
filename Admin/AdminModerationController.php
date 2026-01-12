<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Security;
use App\Core\View;
use App\Services\UserNotification;

final class AdminModerationController
{
    public function index(): void
    {
        Security::requireModerator();

        $stats = [
            'total_bans'   => (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM user_actions WHERE action_type = 'ban' AND active = 1"
            ),
            'total_mutes'  => (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM user_actions WHERE action_type = 'mute' AND active = 1"
            ),
            'total_warns'  => (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM user_actions WHERE action_type = 'warn'"
            ),
            'open_reports' => (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM reports WHERE status = 'open'"
            ),
            'open_appeals' => (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM user_appeals WHERE status = 'open'"
            ),
        ];

        $recentActions = Database::fetchAll(
            "SELECT ua.*, u.username
             FROM user_actions ua
             LEFT JOIN users u ON u.id = ua.user_id
             ORDER BY ua.created_at DESC
             LIMIT 20"
        ) ?? [];

        View::render('admin/moderation/index', [
            'title'   => 'Moderation',
            'stats'   => $stats,
            'actions' => $recentActions,
        ]);
    }

    public function actions(): void
    {
        Security::requireModerator();

        $type   = $_GET['type'] ?? '';
        $status = $_GET['status'] ?? '';

        $where  = [];
        $params = [];

        if ($type !== '') {
            $where[]  = 'action_type = ?';
            $params[] = $type;
        }

        if ($status === 'active') {
            $where[] = 'active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'active = 0';
        }

        $sql = "
            SELECT ua.*, u.username
            FROM user_actions ua
            LEFT JOIN users u ON u.id = ua.user_id
        ";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY ua.created_at DESC LIMIT 200';

        $actions = Database::fetchAll($sql, $params) ?? [];

        View::render('admin/moderation/actions', [
            'title'   => 'Moderationsaktionen',
            'actions' => $actions,
            'filter'  => [
                'type'   => $type,
                'status' => $status,
            ],
        ]);
    }

    public function createAction(): void
    {
        Security::requireModerator();

        View::render('admin/moderation/action-form', [
            'title'  => 'Aktion erstellen',
            'action' => null,
        ]);
    }

    public function storeAction(): void
    {
        Security::requireModerator();
        Security::checkCsrf();

        $username = trim($_POST['username'] ?? '');
        $type     = $_POST['action_type'] ?? '';
        $reason   = trim($_POST['reason'] ?? '');
        $duration = isset($_POST['duration']) ? (int) $_POST['duration'] : null;

        if ($username === '' || $type === '' || $reason === '') {
            $_SESSION['flash_error'] = 'Alle Felder sind erforderlich.';
            header('Location: /admin/moderation/actions/create');
            exit;
        }

        $user = Database::fetch(
            "SELECT id FROM users WHERE username = ?",
            [$username]
        );

        if (!$user) {
            $_SESSION['flash_error'] = 'Benutzer nicht gefunden.';
            header('Location: /admin/moderation/actions/create');
            exit;
        }

        $expiresAt = null;
        if ($duration && in_array($type, ['ban', 'mute'], true)) {
            $expiresAt = date('Y-m-d H:i:s', time() + ($duration * 86400));
        }

        Database::execute(
            "INSERT INTO user_actions
             (user_id, action_type, reason, duration_days, expires_at, created_by, active)
             VALUES (?, ?, ?, ?, ?, ?, 1)",
            [
                $user['id'],
                $type,
                $reason,
                $duration,
                $expiresAt,
                $_SESSION['user']['username'],
            ]
        );

        if ($type === 'ban') {
            Database::execute(
                "UPDATE users SET account_locked = 1 WHERE id = ?",
                [$user['id']]
            );
        }

        UserNotification::create(
            $username,
            'Moderationsaktion',
            "Du hast eine {$type}-Aktion erhalten: {$reason}",
            'warning'
        );

        $_SESSION['flash_success'] = 'Aktion wurde erstellt.';
        header('Location: /admin/moderation/actions');
        exit;
    }

    public function deactivateAction(): void
    {
        Security::requireModerator();
        Security::checkCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            exit;
        }

        $action = Database::fetch(
            "SELECT ua.*, u.username
             FROM user_actions ua
             LEFT JOIN users u ON u.id = ua.user_id
             WHERE ua.id = ?",
            [$id]
        );

        if (!$action) {
            http_response_code(404);
            exit;
        }

        Database::execute(
            "UPDATE user_actions SET active = 0 WHERE id = ?",
            [$id]
        );

        if ($action['action_type'] === 'ban') {
            $activeBans = Database::fetch(
                "SELECT COUNT(*) AS count
                 FROM user_actions
                 WHERE user_id = ? AND action_type = 'ban' AND active = 1",
                [$action['user_id']]
            )['count'] ?? 0;

            if ($activeBans === 0) {
                Database::execute(
                    "UPDATE users SET account_locked = 0 WHERE id = ?",
                    [$action['user_id']]
                );
            }
        }

        UserNotification::create(
            $action['username'],
            'Sperre aufgehoben',
            'Deine Sperre wurde aufgehoben.',
            'success'
        );

        $_SESSION['flash_success'] = 'Aktion wurde deaktiviert.';
        header('Location: /admin/moderation/actions');
        exit;
    }
}
