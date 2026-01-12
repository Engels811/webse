<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Core\Response;
use App\Services\Twitch\TwitchEventSubService;
use App\Services\Twitch\TwitchEventSubLogService;

final class TwitchEventSubController
{
    /**
     * GET /admin/twitch/eventsub
     * Zentrale EventSub-Verwaltung + Monitor
     */
    public function index(): void
    {
        Security::requirePermission(Permissions::TWITCH_EVENTSUB_MANAGE);

        $eventSubService = new TwitchEventSubService();
        $logService      = new TwitchEventSubLogService();

        View::render('admin/twitch/pages/eventsub', [
            'title'         => 'Twitch EventSub',
            'layout'        => 'admin/twitch/layout',

            // Status / Config
            'status'        => $eventSubService->status(),
            'subscriptions' => $eventSubService->subscriptions(),

            // 🔔 MONITOR
            'logs'          => $logService->latest(), // letzte X Events
        ]);
    }

    /**
     * POST /admin/twitch/eventsub/retry
     * EventSub neu initialisieren (Subscriptions neu anlegen)
     */
    public function retry(): void
    {
        Security::requirePermission(Permissions::TWITCH_EVENTSUB_MANAGE);
        Security::checkCsrf();

        (new TwitchEventSubService())->retryAll();

        notify_ui('EventSub erneut initialisiert', 'success');
        header('Location: /admin/twitch/eventsub');
        exit;
    }

    /**
     * GET /admin/twitch/eventsub/payload?id=123
     * Payload eines Events (für Modal / Drawer)
     */
    public function payload(): void
    {
        Security::requirePermission(Permissions::TWITCH_EVENTSUB_MANAGE);

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            Response::json(['error' => 'Invalid ID'], 400);
            return;
        }

        $event = (new TwitchEventSubLogService())->find($id);

        if (!$event) {
            Response::json(['error' => 'Not found'], 404);
            return;
        }

        // bewusst RAW ausgeben (JSON im Frontend formatieren)
        header('Content-Type: application/json; charset=utf-8');
        echo $event['payload'];
        exit;
    }
}
