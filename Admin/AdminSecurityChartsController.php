<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Database;
use App\Core\Response;

final class AdminSecurityChartsController
{
    public function index(): void
    {
        Security::requirePermission('security.view');

        $data = [
            'rate_limits_7d' => $this->getRateLimitData(7),
            'blocks_30d' => $this->getBlockData(30),
            'alerts_summary' => $this->getAlertsSummary(),
        ];

        View::render('admin/security/charts', [
            'title' => 'Security Charts',
            'data' => $data
        ]);
    }

    private function getRateLimitData(int $days): array
    {
        try {
            return Database::fetchAll("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as hits
                FROM rate_limit_hits
                WHERE created_at > (NOW() - INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ", [$days]) ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function getBlockData(int $days): array
    {
        try {
            return Database::fetchAll("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as blocks
                FROM auto_blocks
                WHERE created_at > (NOW() - INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ", [$days]) ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function getAlertsSummary(): array
    {
        try {
            return Database::fetchAll("
                SELECT 
                    severity,
                    COUNT(*) as count
                FROM system_alerts
                WHERE status = 'active'
                GROUP BY severity
            ") ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
