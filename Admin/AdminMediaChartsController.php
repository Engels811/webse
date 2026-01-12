<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Response;
use App\Core\Security;
use App\Core\Permissions;
use App\Services\Admin\AdminMediaChartsService;

final class AdminMediaChartsController
{
    /**
     * GET /admin/api/media/views?days=14
     */
    public function views(): void
    {
        Security::requirePermission(Permissions::MEDIA_VIEW);

        $days = max(1, min(90, (int)($_GET['days'] ?? 14)));

        Response::json(
            (new AdminMediaChartsService())->viewsPerDay($days)
        );
    }

    /**
     * GET /admin/api/media/uploads?days=14
     */
    public function uploads(): void
    {
        Security::requirePermission(Permissions::MEDIA_VIEW);

        $days = max(1, min(90, (int)($_GET['days'] ?? 14)));

        Response::json(
            (new AdminMediaChartsService())->uploadsPerDay($days)
        );
    }
}
