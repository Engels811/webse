<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Services\Admin\AdminMediaStatsService;

final class AdminMediaStatsController
{
    /**
     * GET /admin/media/stats
     */
    public function index(): void
    {
        Security::requirePermission(Permissions::MEDIA_VIEW);

        $statsService = new AdminMediaStatsService();

        View::render('admin/media/stats', [
            'title' => 'Media – Statistiken',
            'stats' => $statsService->overview(),
        ]);
    }
}
