<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Security;
use App\Core\Response;
use App\Core\Database;

final class UserAuditController
{
    /**
     * GET /admin/users/{id}/audit
     */
    public function show(int $userId): void
    {
        Security::requireAdmin();

        $logs = Database::fetchAll(
            "SELECT
                event,
                context,
                created_at
             FROM audit_logs
             WHERE target_type = 'user'
               AND target_id = ?
             ORDER BY created_at DESC
             LIMIT 30",
            [$userId]
        );

        $out = [];
        foreach ($logs as $l) {
            $ctx = json_decode($l['context'] ?? '{}', true);

            $out[] = [
                'description' => $this->humanize($l['event'], $ctx),
                'date'        => date('d.m.Y H:i', strtotime($l['created_at']))
            ];
        }

        Response::json($out);
    }

    private function humanize(string $event, array $ctx): string
    {
        return match ($event) {
            'twitch.permission.sync' =>
                isset($ctx['tier'])
                    ? 'Twitch Abo Tier ' . ((int)$ctx['tier'] / 1000)
                    : 'Twitch Abo beendet',

            'user.avatar.updated' =>
                'Avatar geändert',

            default =>
                str_replace('.', ' ', $event),
        };
    }
}
