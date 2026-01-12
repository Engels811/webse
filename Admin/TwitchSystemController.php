<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Services\Twitch\TwitchSystemService;

final class TwitchSystemController
{
    public function index(): void
    {
        Security::requirePermission(Permissions::TWITCH_SYSTEM_MANAGE);

        $service = new TwitchSystemService();

        View::render('admin/twitch/pages/system', [
            'title'   => 'Twitch System',
            'layout'  => 'admin/twitch/layout',
            'status'  => $service->status(),
            'oauth'   => $service->oauth(),
            'scopes'  => $service->scopes(),
            'debug'   => $service->debug(),
        ]);
    }

    public function reset(): void
    {
        Security::requirePermission(Permissions::TWITCH_SYSTEM_MANAGE);
        Security::checkCsrf();

        (new TwitchSystemService())->reset();

        notify_ui('Twitch System zurückgesetzt', 'warning');
        header('Location: /admin/twitch/system');
        exit;
    }
}
