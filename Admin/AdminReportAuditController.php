<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Services\Admin\AdminReportAuditService;

final class AdminReportAuditController
{
    /**
     * GET /admin/reports/audit
     */
    public function index(): void
    {
        Security::requirePermission(Permissions::AUDIT_VIEW);

        $filters = [
            'actor'  => $_GET['actor'] ?? null,
            'action' => $_GET['action'] ?? null,
            'from'   => $_GET['from'] ?? null,
            'to'     => $_GET['to'] ?? null,
        ];

        $logs = (new AdminReportAuditService())->list($filters);

        View::render('admin/reports/audit', [
            'title'   => 'Report Audit-Log',
            'logs'    => $logs,
            'filters' => $filters,
        ]);
    }
}
