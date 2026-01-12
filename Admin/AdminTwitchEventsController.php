<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Services\Admin\TwitchEventTimelineService;

final class AdminTwitchEventsController
{
    public function index(): void
    {
        Security::requireAdmin();

        $type  = $_GET['type']  ?? null;
        $range = $_GET['range'] ?? '24h';

        $service = new TwitchEventTimelineService();

        View::render('admin/twitch/events/index', [
            'title'  => 'Twitch – Event Timeline',
            'events' => $service->list($type, $range),
            'types'  => $service->types(),
            'activeType'  => $type,
            'activeRange' => $range,
        ]);
    }

    public function show(): void
    {
        Security::requireAdmin();

        $id = (int)($_GET['id'] ?? 0);

        View::render('admin/twitch/events/show', [
            'title' => 'Twitch – Event Details',
            'event' => (new TwitchEventTimelineService())->get($id),
        ]);
    }
}
