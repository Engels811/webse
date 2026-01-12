<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Response;
use App\Core\Security;
use App\Core\Permissions;
use App\Services\Admin\AdminReportService;

final class AdminReportController
{
    /**
     * GET /admin/reports
     */
    public function index(): void
    {
        Security::requirePermission(Permissions::MODERATION_VIEW);

        View::render('admin/reports/index', [
            'title'   => 'Reports – Übersicht',
            'reports' => (new AdminReportService())->all(),
        ]);
    }

    /**
     * GET /admin/reports/show?id=1
     */
    public function show(): void
    {
        Security::requirePermission(Permissions::MODERATION_VIEW);

        $id = (int)($_GET['id'] ?? 0);

        View::render('admin/reports/show', [
            'title'  => 'Report ansehen',
            'report' => (new AdminReportService())->get($id),
        ]);
    }

    /**
     * POST /admin/reports/assign
     */
    public function assign(): void
    {
        Security::requirePermission(Permissions::MODERATION_ASSIGN);
        Security::checkCsrf();

        (new AdminReportService())->assign(
            reportId: (int)$_POST['id'],
            adminId: Security::userId()
        );

        Response::redirect('/admin/reports');
    }

    /**
     * POST /admin/reports/resolve
     */
    public function resolve(): void
    {
        Security::requirePermission(Permissions::MODERATION_RESOLVE);
        Security::checkCsrf();

        (new AdminReportService())->resolve(
            reportId: (int)$_POST['id'],
            status: $_POST['status'] ?? 'closed',
            action: $_POST['action'] ?? null
        );

        Response::redirect('/admin/reports');
    }
}
