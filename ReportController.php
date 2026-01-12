<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Security;
use App\Core\ClientIp;
use App\Core\RateLimiter;
use App\Models\Report;
use App\Services\Domain\NotificationService;

final class ReportController
{
    /**
     * POST /reports/create
     */
    public function create(): void
    {
        Security::requireLogin();
        Security::checkCsrf();

        $contentType = $_POST['content_type'] ?? '';
        $contentId   = (int)($_POST['content_id'] ?? 0);
        $reason      = $_POST['reason'] ?? '';
        $message     = trim($_POST['message'] ?? '');

        /* =====================
           VALIDATION
        ===================== */
        if ($contentId <= 0 || $contentType === '' || $reason === '') {
            Response::json([
                'success' => false,
                'error'   => 'invalid_input'
            ]);
            return;
        }

        $allowedTypes = [
            'media_video',
            'comment',
            'gallery_image',
            'forum_post',
        ];

        $allowedReasons = [
            'spam',
            'beleidigung',
            'hate',
            'nsfw',
            'copyright',
            'fake',
            'other',
        ];

        if (
            !in_array($contentType, $allowedTypes, true) ||
            !in_array($reason, $allowedReasons, true)
        ) {
            Response::json([
                'success' => false,
                'error'   => 'invalid_type'
            ]);
            return;
        }

        /* =====================
           RATE LIMIT
        ===================== */
        $ip = ClientIp::get();

        if (!RateLimiter::hit('report-create', 5, 300, ['ip' => $ip])) {
            Response::json([
                'success' => false,
                'error'   => 'rate_limited'
            ]);
            return;
        }

        /* =====================
           CREATE REPORT
        ===================== */
        $reportId = Report::create(
            contentType: $contentType,
            contentId: $contentId,
            reason: $reason,
            message: $message,
            userId: Security::userId(),
            ip: $ip
        );

        /* =====================
           🔔 ADMIN LIVE NOTIFY
        ===================== */
        (new NotificationService())->emitReportCreated(
            contentType: $contentType,
            contentId: $contentId,
            reason: $reason
        );

        Response::json([
            'success' => true,
            'report_id' => $reportId
        ]);
    }
}
