<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Security;
use App\Core\Response;
use App\Core\Database;
use App\Services\Twitch\TwitchStatusService;

final class TwitchAjaxController
{
    /* =====================================================
       CALENDAR UPDATE (bestehend)
    ===================================================== */

    public function updateCalendar(): void
    {
        Security::requirePermission('twitch.calendar.manage');
        Security::checkCsrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            Response::json(['ok' => false, 'error' => 'Invalid ID']);
            return;
        }

        $status = $_POST['status'] ?? 'active';

        Database::execute(
            "UPDATE twitch_stream_schedule
             SET
               is_cancelled   = ?,
               is_rescheduled = ?,
               is_special     = ?,
               is_hidden      = ?,
               is_override    = 1,
               override_note  = ?,
               override_by    = ?,
               override_at    = NOW()
             WHERE id = ?",
            [
                $status === 'cancelled'   ? 1 : 0,
                $status === 'rescheduled' ? 1 : 0,
                $status === 'special'     ? 1 : 0,
                isset($_POST['is_hidden']) ? 1 : 0,
                trim($_POST['override_note'] ?? '') ?: null,
                $_SESSION['user']['id'] ?? null,
                $id
            ]
        );

        Response::json(['ok' => true]);
    }

    /* =====================================================
       1️⃣ LIVE INDICATOR
    ===================================================== */

    public function liveIndicator(): void
    {
        Security::requirePermission('twitch.admin.access');

        $live = TwitchStatusService::getPublic();

        Response::json([
            'is_live' => !empty($live['is_live']),
            'title'   => $live['title'] ?? null,
            'game'    => $live['game'] ?? null,
            'viewers' => $live['viewers'] ?? 0,
        ]);
    }

    /* =====================================================
       2️⃣ MINI TIMELINE (heute + 7 Tage)
    ===================================================== */

    public function timeline(): void
    {
        Security::requirePermission('twitch.admin.access');

        $rows = Database::fetchAll(
            "SELECT
                id,
                title,
                start_time,
                is_cancelled,
                is_rescheduled,
                is_special
             FROM twitch_stream_schedule
             WHERE start_time >= CURDATE()
               AND start_time < DATE_ADD(CURDATE(), INTERVAL 7 DAY)
             ORDER BY start_time ASC"
        ) ?? [];

        Response::json($rows);
    }

    /* =====================================================
       3️⃣ AUDIT DETAILS (für Drawer)
    ===================================================== */

    public function auditDetails(): void
    {
        Security::requirePermission('twitch.calendar.manage');

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            Response::json(null);
            return;
        }

        $row = Database::fetch(
            "SELECT
                s.override_note,
                s.override_at,
                u.username AS override_user
             FROM twitch_stream_schedule s
             LEFT JOIN users u ON u.id = s.override_by
             WHERE s.id = ?
             LIMIT 1",
            [$id]
        );

        Response::json($row ?: null);
    }
}
