<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Services\Twitch\TwitchVodService;

final class TwitchVodController
{
    public function index(): void
    {
        Security::requirePermission(Permissions::TWITCH_VIEW);

        $service = new TwitchVodService();

        View::render('admin/twitch/pages/vods', [
            'title'  => 'Twitch VODs',
            'layout' => 'admin/twitch/layout',
            'vods'   => $service->list(),
        ]);
    }

    public function sync(): void
    {
        Security::requirePermission(Permissions::TWITCH_MANAGE);

        (new TwitchVodService())->syncFromTwitch();

        notify_ui('VODs wurden synchronisiert', 'success');
        header('Location: /admin/twitch/vods');
        exit;
    }
}
