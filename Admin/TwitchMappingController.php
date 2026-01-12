<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Services\Twitch\TwitchMappingService;

final class TwitchMappingController
{
    public function index(): void
    {
        Security::requirePermission(Permissions::TWITCH_MAPPING_MANAGE);

        $service = new TwitchMappingService();

        View::render('admin/twitch/pages/mapping', [
            'title'  => 'Twitch Mapping',
            'layout' => 'admin/twitch/layout',
            'roles'  => $service->roles(),
            'content'=> $service->content(),
        ]);
    }
}
