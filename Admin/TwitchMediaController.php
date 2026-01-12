<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Services\Twitch\TwitchMediaService;

final class TwitchMediaController
{
    public function index(): void
    {
        Security::requirePermission(Permissions::TWITCH_MEDIA_MANAGE);

        $service = new TwitchMediaService();

        View::render('admin/twitch/pages/media', [
            'title'  => 'Twitch Media',
            'layout' => 'admin/twitch/layout',
            'vods'   => $service->vods(),
            'clips'  => $service->clips(),
        ]);
    }

    public function sync(): void
    {
        Security::requirePermission(Permissions::TWITCH_MEDIA_MANAGE);
        Security::checkCsrf();

        (new TwitchMediaService())->sync();

        notify_ui('Twitch Media synchronisiert', 'success');
        header('Location: /admin/twitch/media');
        exit;
    }
}
