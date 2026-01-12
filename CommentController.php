<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Security;
use App\Core\Response;
use App\Core\RateLimiter;
use App\Core\ClientIp;
use App\Core\RateKey;
use App\Models\Comment;

final class CommentController
{
    /**
     * POST /comments/create
     */
    public function create(): void
    {
        Security::requireLogin();
        Security::checkCsrf();

        $mediaVideoId = (int)($_POST['media_video_id'] ?? 0);
        $content      = trim($_POST['content'] ?? '');

        if ($mediaVideoId <= 0 || $content === '') {
            Response::redirect('/videos');
            return;
        }

        /* =====================
           RATE-LIMIT (Kommentare)
        ===================== */
        $ip     = ClientIp::get();
        $userId = (int)Security::userId();

        $rateKey = RateKey::for(
            scope: 'comment-create',
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
                limit: 10,
                seconds: 60,
                context: ['ip' => $ip]
            )
        ) {
            Response::redirect('/videos/show?id=' . $mediaVideoId);
            return;
        }

        /* =====================
           CREATE COMMENT
        ===================== */
        Comment::create(
            mediaVideoId: $mediaVideoId,
            userId: $userId,
            content: $content
        );

        Response::redirect('/videos/show?id=' . $mediaVideoId);
    }
}
