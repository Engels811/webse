<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Services\Admin\AdminNotificationStatsService;

final class AdminNotificationStatsController
{
    /**
     * GET /admin/notifications/stats
     */
    public function index(): void
    {
        Security::requirePermission(Permissions::NOTIFICATIONS_LOGS_VIEW);

        $days = (int)($_GET['days'] ?? 30);
        if (!in_array($days, [7, 30, 90], true)) {
            $days = 30;
        }

        $stats = (new AdminNotificationStatsService())->topTypes($days);

        View::render('admin/notifications/stats', [
            'title' => 'Notification Statistiken',
            'stats' => $stats,
            'days'  => $days,
        ]);
    }
}
