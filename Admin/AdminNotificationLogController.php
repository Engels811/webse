<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Services\Admin\AdminNotificationLogService;

final class AdminNotificationLogController
{
    /**
     * GET /admin/notifications/logs
     */
    public function index(): void
    {
        Security::requirePermission(Permissions::NOTIFICATIONS_LOGS_VIEW);

        $filters = [
            'type'   => $_GET['type'] ?? null,
            'user'   => $_GET['user'] ?? null,
            'from'   => $_GET['from'] ?? null,
            'to'     => $_GET['to'] ?? null,
        ];

        $logs = (new AdminNotificationLogService())->list($filters);

        View::render('admin/notifications/index', [
            'title' => 'Notification Audit-Log',
            'logs'  => $logs,
            'filters' => $filters,
        ]);
    }
}
