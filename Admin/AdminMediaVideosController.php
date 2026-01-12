<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Services\Admin\AdminMediaListService;
use App\Services\Support\Paginator;

final class AdminMediaVideosController
{
    /**
     * GET /admin/media/videos
     */
    public function index(): void
    {
        Security::requirePermission(Permissions::MEDIA_VIEW);

        $page = max(1, (int)($_GET['page'] ?? 1));

        // Filter normalisieren (leere Strings → null)
        $filters = [
            'q'      => ($_GET['q']      ?? '') !== '' ? $_GET['q']      : null,
            'source' => ($_GET['source'] ?? '') !== '' ? $_GET['source'] : null,
            'status' => ($_GET['status'] ?? '') !== '' ? $_GET['status'] : null,
        ];

        [$videos, $total, $perPage] =
            (new AdminMediaListService())->list($filters, $page);

        View::render('admin/media/videos', [
            'title'  => 'Alle Videos',
            'videos' => $videos,
            'page'   => $page,
            'pages'  => Paginator::pages($total, $perPage),
        ]);
    }
}
