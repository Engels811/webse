<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\Twitch\TwitchCalendarService;

final class TwitchScheduleController
{
    public function index(): void
    {
        View::render('twitch/schedule', [
            'title'   => 'Streaming-Plan',
            'streams' => (new TwitchCalendarService())->listUpcoming(),
        ]);
    }
}
