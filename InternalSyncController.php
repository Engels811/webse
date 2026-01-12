<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\GameTimeSyncService;

final class InternalSyncController
{
    public function syncGames(): void
    {
        // 🔐 TOKEN-SCHUTZ
        $token = $_GET['token'] ?? '';

        if ($token !== $_ENV['SYNC_TOKEN']) {
            http_response_code(403);
            exit('Forbidden');
        }

        GameTimeSyncService::run();

        echo 'OK – Game times synced';
        exit;
    }
}
