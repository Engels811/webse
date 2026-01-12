<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Services\Twitch\TwitchStatusService;
use App\Services\Twitch\TwitchCalendarService;

final class TwitchAdminController
{
    public function index(): void
    {
        Security::requirePermission(Permissions::TWITCH_VIEW);

        $statusService   = new TwitchStatusService();
        $calendarService = new TwitchCalendarService();

        View::render('admin/twitch/pages/dashboard', [
            'title'        => 'Twitch Dashboard',
            'layout'       => 'admin/twitch/layout',
            'liveStatus'   => $statusService->getLiveStatus(),
            'nextStream'   => $calendarService->nextUpcoming(),
        ]);
    }
}
