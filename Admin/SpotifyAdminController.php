<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Database;
use App\Services\Spotify\SpotifySyncService;
use App\Models\SpotifyTarget;
use PDO;

final class SpotifyAdminController
{
    /**
     * GET /admin/spotify
     */
    public function index(): void
    {
        Security::requireAdmin();

        $pdo = Database::getInstance();

        /* =========================
           SETTINGS
        ========================= */
        $settingsStmt = $pdo->query("
            SELECT setting_key, setting_value
            FROM spotify_settings
        ");
        $settingsRaw = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);
        $settings = array_column($settingsRaw, 'setting_value', 'setting_key');

        /* =========================
           SYNC LOGS
        ========================= */
        $logsStmt = $pdo->query("
            SELECT *
            FROM spotify_sync_log
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

        /* =========================
           TARGETS (ZIEL-SYSTEM)
        ========================= */
        $targets = SpotifyTarget::all();

        View::render('admin/spotify/index', [
            'settings' => $settings,
            'logs'     => $logs,
            'targets'  => $targets
        ]);
    }

    /**
     * POST /admin/spotify/connect
     * Spotify OAuth starten
     */
    public function connect(): void
    {
        Security::requireAdmin();
        Security::checkCsrf();

        $clientId     = trim($_POST['client_id'] ?? '');
        $clientSecret = trim($_POST['client_secret'] ?? '');

        if ($clientId === '' || $clientSecret === '') {
            die('Client ID oder Client Secret fehlt');
        }

        $pdo = Database::getInstance();

        $stmt = $pdo->prepare("
            INSERT INTO spotify_settings (setting_key, setting_value)
            VALUES (:k1, :v1), (:k2, :v2)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->execute([
            ':k1' => 'client_id',
            ':v1' => $clientId,
            ':k2' => 'client_secret',
            ':v2' => $clientSecret
        ]);

        $redirectUri = 'https://engels811-ttv.de/admin/spotify/callback';

        $query = http_build_query([
            'client_id'     => $clientId,
            'response_type' => 'code',
            'redirect_uri'  => $redirectUri,
            'scope'         => implode(' ', [
                'user-read-playback-state',
                'user-read-recently-played',
                'playlist-read-private'
            ])
        ]);

        header('Location: https://accounts.spotify.com/authorize?' . $query);
        exit;
    }

    /**
     * GET /admin/spotify/callback
     */
    public function callback(): void
    {
        Security::requireAdmin();

        $code = $_GET['code'] ?? null;
        if (!$code) {
            die('Kein OAuth-Code erhalten');
        }

        $pdo = Database::getInstance();

        $settingsStmt = $pdo->query("
            SELECT setting_key, setting_value
            FROM spotify_settings
        ");
        $settingsRaw = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);
        $settings = array_column($settingsRaw, 'setting_value', 'setting_key');

        if (empty($settings['client_id']) || empty($settings['client_secret'])) {
            die('Spotify Client-Daten fehlen');
        }

        $ch = curl_init('https://accounts.spotify.com/api/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => 'https://engels811-ttv.de/admin/spotify/callback'
            ]),
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode(
                    $settings['client_id'] . ':' . $settings['client_secret']
                )
            ]
        ]);

        $response = json_decode((string)curl_exec($ch), true);
        curl_close($ch);

        if (empty($response['refresh_token'])) {
            die('Kein Refresh Token von Spotify erhalten');
        }

        $stmt = $pdo->prepare("
            INSERT INTO spotify_settings (setting_key, setting_value)
            VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->execute([
            ':k' => 'refresh_token',
            ':v' => $response['refresh_token']
        ]);

        header('Location: /admin/spotify');
        exit;
    }

    /**
     * POST /admin/spotify/sync
     */
    public function sync(): void
    {
        Security::requireAdmin();
        Security::checkCsrf();

        (new SpotifySyncService())->sync();

        header('Location: /admin/spotify');
        exit;
    }

    /**
     * POST /admin/spotify/targets/toggle
     */
    public function toggleTarget(): void
    {
        Security::requireAdmin();
        Security::checkCsrf();

        $key = $_POST['key'] ?? '';

        if ($key !== '') {
            SpotifyTarget::toggle($key);
        }

        header('Location: /admin/spotify');
        exit;
    }
}
