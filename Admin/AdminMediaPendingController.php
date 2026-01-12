<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Services\Admin\AdminMediaService;

final class AdminMediaPendingController
{
    /**
     * GET /admin/media/pending
     */
    public function index(): void
    {
        Security::requirePermission(Permissions::MEDIA_MODERATE);

        View::render('admin/media/pending', [
            'title'  => 'Media – Warteschlange',
            'videos' => (new AdminMediaService())->pending(),
        ]);
    }
}
