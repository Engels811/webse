<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class TwitchSystemRepairService
{
    public function __construct(private PDO $db) {}

    public function createCacheDir(): array
    {
        $dir = BASE_PATH . '/app/cache';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return [
            'success' => is_writable($dir),
            'message' => 'Cache-Verzeichnis geprüft/erstellt'
        ];
    }

    public function createMissingTables(): array
    {
        $sql = file_get_contents(BASE_PATH . '/app/database/twitch.sql');
        $this->db->exec($sql);

        return [
            'success' => true,
            'message' => 'Twitch Tabellen erstellt'
        ];
    }

    public function createDefaultConfig(): array
    {
        $path = BASE_PATH . '/app/config/twitch.php';

        if (file_exists($path)) {
            return ['success' => false, 'message' => 'Config existiert bereits'];
        }

        file_put_contents($path, <<<PHP
<?php
return [
    'client_id'     => '',
    'client_secret' => '',
    'channel_name'  => '',
];
PHP);

        return [
            'success' => true,
            'message' => 'Standard Twitch Config erstellt'
        ];
    }
}
