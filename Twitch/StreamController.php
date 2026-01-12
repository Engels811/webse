<?php
declare(strict_types=1);

namespace App\Controllers\Twitch;

use App\Core\View;
use App\Services\Twitch\LivePageService;
use App\Services\Twitch\TwitchFeature;
use App\Services\Twitch\TwitchStatusService;
use Throwable;

final class StreamController
{
    /**
     * GET /twitch/stream
     * Öffentliche Live-Seite (HTML) – IMMER sichtbar
     */
    public function index(): void
    {
        // Daten aus dem Service (LIVE / OFFLINE)
        $page = LivePageService::getPageData();

        // 🔒 WICHTIG: liveStatus ist IMMER ein Array
        $liveStatus = [
            'is_live' => (bool)($page['is_live'] ?? false),
            'game'    => $page['game'] ?? 'Live',
            'viewers' => (int)($page['viewers'] ?? 0),
        ];

        View::render('twitch/stream', [
            'title'      => 'Live Stream',
            'liveStatus' => $liveStatus,

            // Defaults – verhindern weiße Seiten
            'channel'    => 'engels811',
            'parent'     => $_SERVER['HTTP_HOST'] ?? 'localhost',
            'offlineVod' => null,
            'glowColor'  => '#2ecc71',
            'schedule'   => [],
        ]);
    }

    /**
     * GET /live
     * Plaintext-Status für OBS / JS / Cron
     */
    public function status(): void
    {
        if (!TwitchFeature::enabled()) {
            echo 'OFFLINE';
            return;
        }

        try {
            $status = TwitchStatusService::getPublic();
            echo (!empty($status['is_live'])) ? 'LIVE' : 'OFFLINE';
        } catch (Throwable) {
            echo 'OFFLINE';
        }
    }
}
