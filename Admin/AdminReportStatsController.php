<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Services\Admin\AdminReportStatsService;

final class AdminReportStatsController
{
    /**
     * GET /admin/reports/stats
     */
    public function index(): void
    {
        Security::requirePermission(Permissions::MODERATION_VIEW);

        $days = (int)($_GET['days'] ?? 30);
        if (!in_array($days, [7, 30, 90], true)) {
            $days = 30;
        }

        $stats = (new AdminReportStatsService())->topReasons($days);

        View::render('admin/reports/stats', [
            'title' => 'Report Statistiken',
            'stats' => $stats,
            'days'  => $days,
        ]);
    }
}
