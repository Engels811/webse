<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\Public\TrendingService;

final class TrendingController
{
    /**
     * GET /trending
     */
    public function index(): void
    {
        $service = new TrendingService();

        View::render('trending/index', [
            'title'    => 'Trending',
            'trending' => $service->trending(),
            'popular'  => $service->popular(),
        ]);
    }
}
