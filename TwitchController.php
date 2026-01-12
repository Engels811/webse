<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\Twitch\TwitchStatusService;
use App\Services\Twitch\TwitchCalendarService;
use App\Services\Twitch\TwitchLivePageService;

final class TwitchController
{
    public function live(): void
    {
        $channel = 'engels811';

        $parent = $_SERVER['HTTP_HOST']
            ?? parse_url($_ENV['BASE_URL'] ?? '', PHP_URL_HOST)
            ?? 'localhost';

        $liveStatus = TwitchStatusService::getPublic();
        $isLive     = !empty($liveStatus['is_live']);

        View::render('twitch/live', [
            'title'        => 'Live auf Twitch',
            'channel'      => $channel,
            'parent'       => $parent,
            'liveStatus'   => $liveStatus,
            'isLive'       => $isLive,
            'offlineVod'   => TwitchLivePageService::getOfflineVodEmbed($parent),
            'glowColor'    => TwitchLivePageService::getGlowColor($liveStatus['game'] ?? null),
            'twitchBanner' => $liveStatus['profile_banner'] ?? null,
            'schedule'     => TwitchCalendarService::listPublic(),
            'currentPage'  => 'live'
        ]);
    }

    public function calendar(): void
    {
        View::render('twitch/calendar', [
            'title'       => 'Streaming-Plan',
            'schedule'    => TwitchCalendarService::listPublic(),
            'currentPage' => 'live'
        ]);
    }
}
