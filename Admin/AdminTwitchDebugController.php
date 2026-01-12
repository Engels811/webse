<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Security;
use App\Core\View;
use App\Services\Twitch\TwitchFeature;
use App\Services\Twitch\TwitchTokenRepository;

final class AdminTwitchDebugController
{
    /**
     * GET /admin/system/twitch
     */
    public function index(): void
    {
        Security::requireAdmin(); // oder requireOwner()

        $token = null;
        if (TwitchFeature::enabled()) {
            $token = TwitchTokenRepository::get();
        }

        View::render('admin/system/twitch', [
            'title'   => 'System · Twitch Debug',
            'enabled' => TwitchFeature::enabled(),
            'token'   => $token,
        ]);
    }
}
