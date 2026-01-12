<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

final class AdminAuditController
{
    /* =========================================================
       ACCESS GUARD
    ========================================================= */

    private function guard(): void
    {
        if (empty($_SESSION['user'])) {
            http_response_code(403);
            View::render('errors/403', ['title' => 'Zugriff verweigert']);
            exit;
        }
    }

    /* =========================================================
       LISTE + FILTER
    ========================================================= */

    public function index(): void
    {
        $this->guard();

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
            'title' => 'Audit-Log',
            'logs'  => $logs,
            'filter'=> [
                'action' => $action,
                'user'   => $user,
                'target' => $target
            ]
        ]);
    }

    /* =========================================================
       DETAIL / DIFF VIEW
    ========================================================= */

    public function view(int $id): void
    {
        $this->guard();

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
