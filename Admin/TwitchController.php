<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Services\Admin\TwitchStatusService;
use App\Services\Admin\TwitchEventSubService;
use App\Services\Admin\TwitchMappingService;
use App\Services\Admin\TwitchVodsService;
use App\Services\Admin\TwitchDebugService;

final class TwitchController
{
    public function index(): void
    {
        Security::requirePermission(Permissions::ADMIN_TWITCH_ACCESS);

        View::render('admin/twitch/index', [
            'title'     => 'Twitch Verwaltung',
            'status'    => (new TwitchStatusService())->status(),
            'eventsub'  => (new TwitchEventSubService())->overview(),
            'mapping'   => (new TwitchMappingService())->overview(),
            'streams'   => (new TwitchVodsService())->overview(),
            'debug'     => (new TwitchDebugService())->overview(),
        ]);
    }
}
