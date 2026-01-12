<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Security;
use App\Core\Response;
use App\Core\Permissions;
use App\Models\Notification;
use App\Core\Session;

final class AdminNotificationLiveController
{
    /**
     * GET /admin/api/notifications/live
     */
    public function poll(): void
    {
        Security::requirePermission(Permissions::NOTIFICATIONS_LOGS_VIEW);

        $userId = Session::userId();

        $items = Notification::unreadForUser($userId);

        Response::json([
            'count' => count($items),
            'items' => array_slice($items, 0, 5),
        ]);
    }
}
