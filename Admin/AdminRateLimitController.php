<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Services\Admin\AdminRateLimitService;

final class AdminRateLimitController
{
    /**
     * GET /admin/system/ratelimits
     */
    public function index(): void
    {
        Security::requirePermission('system_view');

        View::render('admin/system/ratelimits', [
            'title'   => 'Rate-Limits',
            'entries' => (new AdminRateLimitService())->recent(),
        ]);
    }
}
