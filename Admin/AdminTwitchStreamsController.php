<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Services\Admin\TwitchStreamService;

final class AdminTwitchStreamsController
{
    public function index(): void
    {
        Security::requireAdmin();

        View::render('admin/twitch/streams', [
            'title'   => 'Twitch – Stream-Verlauf',
            'streams' => (new TwitchStreamService())->all(),
        ]);
    }
}
