<?php
declare(strict_types=1);

namespace App\Controllers\Webhooks;

use App\Core\Response;
use App\Core\Database;
use App\Services\Provider\Twitch\TwitchSignatureValidator;
use App\Services\Provider\Twitch\TwitchEventProcessor;
use RuntimeException;

final class TwitchWebhookController
{
    /**
     * POST /webhooks/twitch
     */
    public function handle(): void
    {
        $rawBody = file_get_contents('php://input');
        $headers = $this->getHeaders();

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            Response::json(['error' => 'Invalid payload'], 400);
            return;
        }

        // =====================================================
        // CHALLENGE (EventSub Verification)
        // =====================================================
        if (($payload['subscription']['status'] ?? null) === 'webhook_callback_verification_pending') {
            Response::text($payload['challenge'] ?? '', 200);
            return;
        }

        // =====================================================
        // SIGNATURE VALIDATION
        // =====================================================
        try {
            TwitchSignatureValidator::validate($headers, $rawBody);
        } catch (RuntimeException $e) {
            Response::json(['error' => 'Unauthorized'], 401);
            return;
        }

        $eventId = $headers['Twitch-Eventsub-Message-Id'] ?? null;
        if (!$eventId) {
            Response::json(['error' => 'Missing event id'], 400);
            return;
        }

        // =====================================================
        // DEDUPLICATION (HARD)
        // =====================================================
        $exists = Database::fetch(
            'SELECT id FROM provider_event_dedup
             WHERE provider = ? AND external_event_id = ?
             LIMIT 1',
            ['twitch', $eventId]
        );

        if ($exists) {
            Response::json(['status' => 'duplicate'], 200);
            return;
        }

        Database::execute(
            'INSERT INTO provider_event_dedup
                (provider, external_event_id, processed_at)
             VALUES (?, ?, NOW())',
            ['twitch', $eventId]
        );

        // =====================================================
        // AUDIT LOG
        // =====================================================
        Database::execute(
            'INSERT INTO provider_events
                (provider, event_type, external_event_id, payload, received_at)
             VALUES (?, ?, ?, ?, NOW())',
            [
                'twitch',
                $payload['subscription']['type'] ?? 'unknown',
                $eventId,
                json_encode($payload, JSON_THROW_ON_ERROR),
            ]
        );

        // =====================================================
        // PROCESS
        // =====================================================
        (new TwitchEventProcessor())->process($payload);

        Response::json(['status' => 'ok'], 200);
    }

    private function getHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }
}
