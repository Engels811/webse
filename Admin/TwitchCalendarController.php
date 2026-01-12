<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Services\Twitch\TwitchCalendarService;

final class TwitchCalendarController
{
    public function index(): void
    {
        Security::requirePermission(Permissions::TWITCH_CALENDAR_MANAGE);

        View::render('admin/twitch/pages/calendar', [
            'title'   => 'Streaming-Plan',
            'layout'  => 'admin/twitch/layout',
            'streams' => (new TwitchCalendarService())->listAdmin(),
        ]);
    }
}
