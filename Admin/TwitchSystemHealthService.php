<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class TwitchSystemHealthService
{
    public function __construct(private PDO $db) {}

    public function runAll(): array
    {
        return [
            'base_path' => defined('BASE_PATH'),
            'config'    => $this->checkConfig(),
            'database'  => $this->checkDatabase(),
            'tables'    => $this->checkTables(),
            'cache'     => $this->checkCacheDir(),
            'service'   => class_exists(TwitchService::class),
        ];
    }

    private function checkConfig(): array
    {
        $path = BASE_PATH . '/app/config/twitch.php';

        if (!is_file($path)) {
            return ['ok' => false, 'message' => 'Config fehlt'];
        }

        $cfg = require $path;

        $missing = array_filter(
            ['client_id', 'client_secret', 'channel_name'],
            fn($k) => empty($cfg[$k])
        );

        return [
            'ok'      => empty($missing),
            'missing' => $missing
        ];
    }

    private function checkDatabase(): bool
    {
        try {
            $this->db->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkTables(): array
    {
        $tables = ['twitch_vods', 'twitch_live_status', 'notifications'];
        $missing = [];

        foreach ($tables as $table) {
            $stmt = $this->db->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() === 0) {
                $missing[] = $table;
            }
        }

        return [
            'ok' => empty($missing),
            'missing' => $missing
        ];
    }

    private function checkCacheDir(): bool
    {
        $dir = BASE_PATH . '/app/cache';
        return is_dir($dir) && is_writable($dir);
    }
}
