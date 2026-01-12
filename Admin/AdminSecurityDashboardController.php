<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Core\Database;

final class AdminSecurityDashboardController
{
    /**
     * GET /admin/system/security
     */
    public function index(): void
    {
        Security::requirePermission(Permissions::SYSTEM_VIEW ?? Permissions::MEDIA_VIEW);

        $stats = [
            'alerts24h' => (int)Database::fetch(
                "SELECT COUNT(*) c FROM system_alerts
                 WHERE created_at >= NOW() - INTERVAL 1 DAY"
            )['c'],

            'autoBlocks24h' => (int)Database::fetch(
                "SELECT COUNT(*) c FROM ip_blocks_auto
                 WHERE created_at >= NOW() - INTERVAL 1 DAY"
            )['c'],

            'blacklistedIps' => (int)Database::fetch(
                "SELECT COUNT(*) c FROM ip_lists WHERE list='blacklist'"
            )['c'],

            'cidrBlocks' => (int)Database::fetch(
                "SELECT COUNT(*) c FROM ip_cidr_blocks"
            )['c'],
        ];

        View::render('admin/system/security_dashboard', [
            'title' => 'Security Dashboard',
            'stats' => $stats,
        ]);
    }
}
