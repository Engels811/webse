<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Database;
use App\Core\ClientIp;
use App\Core\IpGuard;
use App\Core\RateLimiter;
use App\Core\BotDetector;
use App\Core\RateKey;
use App\Core\Security;
use App\Models\MediaVideo;
use App\Models\Comment;
use App\Services\Public\VideoViewService;
use App\Services\Support\TagService;

final class VideoController
{
    /**
     * GET /videos
     */
    public function index(): void
    {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 12;

        $filters = [
            'q'      => $_GET['q'] ?? null,
            'source' => $_GET['source'] ?? null,
            'tag'    => $_GET['tag'] ?? null,
        ];

        // =====================
        // DATA + COUNT
        // =====================
        [$videos, $total] = MediaVideo::listWithCount(
            filters: $filters,
            offset: ($page - 1) * $perPage,
            limit: $perPage
        );

        $pages = (int)ceil($total / $perPage);

        View::render('videos/index', [
            'title'   => 'Videos',
            'videos'  => $videos,
            'sources' => MediaVideo::sources(),
            'page'    => $page,
            'pages'   => $pages,
        ]);
    }

    /**
     * GET /videos/show?id=123
     */
    public function show(): void
    {
        /* =====================
           1️⃣ IP + GUARD
        ===================== */
        $ip = ClientIp::get();
        if (!IpGuard::allow($ip)) {
            http_response_code(403);
            return;
        }

        /* =====================
           2️⃣ ID
        ===================== */
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(404);
            return;
        }

        /* =====================
           3️⃣ VIDEO
        ===================== */
        $video = MediaVideo::get($id);
        if (!$video || $video['status'] !== 'approved') {
            http_response_code(404);
            return;
        }

        /* =====================
           4️⃣ USER / BOT
        ===================== */
        $userId = (int)(Security::userId() ?? 0);
        $ua     = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $isBot  = BotDetector::isBot($ua);

        /* =====================
           5️⃣ RATE KEY
        ===================== */
        $rateKey = RateKey::for(
            scope: $isBot ? 'media-video-show-bot' : 'media-video-show',
            userId: $userId,
            ip: $ip
        );

        /* =====================
           6️⃣ RATE LIMIT
        ===================== */
        $isPrivileged =
            Security::can('system.manage') ||
            Security::can('admin.access');

        $allowed = $isPrivileged
            ? true
            : RateLimiter::hit(
                key: $rateKey,
                limit: $isBot ? 30 : 120,
                seconds: 60,
                context: ['ip' => $ip]
            );

        /* =====================
           7️⃣ VIEW TRACKING
        ===================== */
        if ($allowed) {
            (new VideoViewService())->track($id, $ip);
        }

        /* =====================
           8️⃣ RENDER
        ===================== */
        View::render('videos/show', [
            'video'    => $video,
            'comments' => Comment::approvedForVideo($id),
            'tags'     => (new TagService())->forVideo($id),
            'author'   => !empty($video['user_id'])
                ? Database::fetch(
                    "SELECT username FROM users WHERE id = ?",
                    [$video['user_id']]
                )
                : null,
        ]);
    }
}
