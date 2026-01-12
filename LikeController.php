<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Security;
use App\Core\Response;
use App\Core\ClientIp;
use App\Core\RateLimiter;
use App\Core\RateKey;
use App\Models\Like;

final class LikeController
{
    /**
     * POST /likes/toggle
     */
    public function toggle(): void
    {
        Security::requireLogin();
        Security::checkCsrf();

        $type     = (string)($_POST['type'] ?? '');
        $targetId = (int)($_POST['id'] ?? 0);

        // Erlaubte Targets
        if (!in_array($type, ['media_video', 'comment'], true) || $targetId <= 0) {
            Response::json(['success' => false]);
            return;
        }

        $ip     = ClientIp::get();
        $userId = (int)Security::userId();

        /* =====================
           RATE-LIMIT (Likes)
        ===================== */
        $rateKey = RateKey::for(
            scope: 'like-toggle-' . $type,
            userId: $userId,
            ip: $ip
        );

        $isPrivileged =
            Security::can('admin.access') ||
            Security::can('system.manage');

        if (
            !$isPrivileged &&
            !RateLimiter::hit(
                key: $rateKey,
                limit: 20,
                seconds: 60,
                context: ['ip' => $ip]
            )
        ) {
            Response::json(['success' => false, 'rate_limited' => true]);
            return;
        }

        /* =====================
           TOGGLE LIKE
        ===================== */
        $liked = Like::toggle(
            type: $type,
            targetId: $targetId,
            userId: $userId
        );

        Response::json([
            'success' => true,
            'liked'   => $liked,
            'count'   => Like::count($type, $targetId),
        ]);
    }
}
