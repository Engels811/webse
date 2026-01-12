<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\Public\PublicStreamService;

final class PublicStreamController
{
    /**
     * GET /videos/streams
     */
    public function index(): void
    {
        $page = max(1, (int)($_GET['page'] ?? 1));

        [$streams, $total, $perPage, $stats] =
            (new PublicStreamService())->list($page);

        View::render('videos/streams', [
            'title'   => 'Stream-Archiv',
            'streams' => $streams,
            'stats'   => $stats,
            'page'    => $page,
            'pages'   => (int)ceil($total / $perPage),
        ]);
    }
}
