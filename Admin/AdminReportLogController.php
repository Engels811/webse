<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Services\Admin\AdminReportLogService;

final class AdminReportLogController
{
    /**
     * GET /admin/reports/logs
     */
    public function index(): void
    {
        Security::requirePermission(Permissions::MODERATION_VIEW);

        $filters = [
            'report_id' => $_GET['report_id'] ?? null,
            'action'    => $_GET['action'] ?? null,
            'actor'     => $_GET['actor'] ?? null,
            'from'      => $_GET['from'] ?? null,
            'to'        => $_GET['to'] ?? null,
        ];

        $logs = (new AdminReportLogService())->list($filters);

        View::render('admin/reports/logs/index', [
            'title'   => 'Report Audit-Log',
            'logs'    => $logs,
            'filters' => $filters,
        ]);
    }
}
