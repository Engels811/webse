<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;

final class SpotifyController
{
    public function aboutData(): void
    {
        header('Content-Type: application/json');

        echo json_encode([
            'success' => true,
            'data' => [
                'current_track' => Database::fetch("SELECT * FROM spotify_current_playing WHERE id=1"),
                'playlists' => Database::fetchAll("SELECT * FROM spotify_playlists WHERE is_active=1"),
                'recently_played' => Database::fetchAll("SELECT * FROM spotify_recently_played ORDER BY played_at DESC LIMIT 5")
            ]
        ]);
    }
}
