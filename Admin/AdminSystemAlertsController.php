<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Services\System\AlertService;

final class AdminSystemAlertsController
{
    /**
     * GET /admin/system/alerts
     */
    public function index(): void
    {
        Security::requirePermission(Permissions::SYSTEM_VIEW ?? Permissions::MEDIA_VIEW);

        View::render('admin/system/alerts', [
            'title'  => 'System-Alerts',
            'alerts' => (new AlertService())->recent(),
        ]);
    }
}
