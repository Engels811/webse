<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

final class SpotifyController
{
    /**
     * Öffentliche Spotify-Seite
     * GET /spotify
     */
    public function index(): void
    {
        View::render('spotify/index', [
            'title' => 'Spotify – Was Engels811 hört'
        ]);
    }
}
