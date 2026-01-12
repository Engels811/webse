<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Database;

final class AdminAuditController
{
    public function index(): void
    {
        Security::requireAdmin();

        $action = trim($_GET['action'] ?? '');
        $user   = trim($_GET['user'] ?? '');
        $target = trim($_GET['target'] ?? '');

        $where  = [];
        $params = [];

        if ($action !== '') {
            $where[]  = 'action LIKE ?';
            $params[] = "%{$action}%";
        }

        if ($user !== '') {
            $where[]  = 'actor_username LIKE ?';
            $params[] = "%{$user}%";
        }

        if ($target !== '') {
            $where[]  = 'target_type = ?';
            $params[] = $target;
        }

        $sql = 'SELECT * FROM audit_logs';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY created_at DESC LIMIT 200';

        $logs = Database::fetchAll($sql, $params) ?? [];

        View::render('admin/audit/index', [
            'title'  => 'Audit-Log',
            'logs'   => $logs,
            'filter' => [
                'action' => $action,
                'user'   => $user,
                'target' => $target
            ]
        ]);
    }

    public function view(int $id): void
    {
        Security::requireAdmin();

        $log = Database::fetch(
            'SELECT * FROM audit_logs WHERE id = ?',
            [$id]
        );

        if (!$log) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        View::render('admin/audit/view', [
            'title' => 'Audit-Detail',
            'log'   => $log
        ]);
    }
}
