<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Response;
use App\Core\Security;
use App\Core\Permissions;
use App\Services\Admin\AdminMediaStatsApiService;

final class AdminMediaStatsApiController
{
    /**
     * GET /admin/media/stats/api/views-per-day?days=30
     */
    public function viewsPerDay(): void
    {
        Security::requirePermission(Permissions::MEDIA_VIEW);

        $days = max(1, min(365, (int)($_GET['days'] ?? 30)));

        Response::json(
            (new AdminMediaStatsApiService())->viewsPerDay(
                days: $days
            )
        );
    }

    /**
     * GET /admin/media/stats/api/top-videos?days=7&limit=10
     */
    public function topVideos(): void
    {
        Security::requirePermission(Permissions::MEDIA_VIEW);

        $days  = max(1, min(365, (int)($_GET['days'] ?? 7)));
        $limit = max(1, min(50,  (int)($_GET['limit'] ?? 10)));

        Response::json(
            (new AdminMediaStatsApiService())->topVideos(
                days: $days,
                limit: $limit
            )
        );
    }
}
