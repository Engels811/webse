<?php
declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Database;

final class TwitchCallbackController
{
    /**
     * GET /auth/twitch/callback
     */
    public function handle(): void
    {
        // Session MUSS existieren
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // State prüfen
        if (
            empty($_GET['code']) ||
            empty($_GET['state']) ||
            ($_GET['state'] !== ($_SESSION['oauth_state_twitch'] ?? null))
        ) {
            http_response_code(400);
            echo 'Ungültiger Twitch OAuth State';
            exit;
        }

        unset($_SESSION['oauth_state_twitch']);

        // User MUSS eingeloggt sein (Account-Linking)
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        if ($userId <= 0) {
            header('Location: /login');
            exit;
        }

        $config = require BASE_PATH . '/app/config/oauth.php';
        $twitch = $config['twitch'];

        /* ===============================
           Access Token holen
        =============================== */
        $token = json_decode(
            file_get_contents(
                $twitch['token_url'],
                false,
                stream_context_create([
                    'http' => [
                        'method'  => 'POST',
                        'header'  => 'Content-Type: application/x-www-form-urlencoded',
                        'content' => http_build_query([
                            'client_id'     => $twitch['client_id'],
                            'client_secret' => $twitch['client_secret'],
                            'code'          => $_GET['code'],
                            'grant_type'    => 'authorization_code',
                            'redirect_uri'  => $twitch['redirect_uri'],
                        ])
                    ]
                ])
            ),
            true
        );

        $accessToken = $token['access_token'] ?? null;
        if (!$accessToken) {
            http_response_code(400);
            echo 'Twitch Token Fehler';
            exit;
        }

        /* ===============================
           Twitch User abrufen
        =============================== */
        $response = json_decode(
            file_get_contents(
                $twitch['user_url'],
                false,
                stream_context_create([
                    'http' => [
                        'header' => [
                            "Authorization: Bearer {$accessToken}",
                            "Client-Id: {$twitch['client_id']}"
                        ]
                    ]
                ])
            ),
            true
        );

        $user = $response['data'][0] ?? null;
        if (!$user || empty($user['id'])) {
            http_response_code(400);
            echo 'Twitch User nicht abrufbar';
            exit;
        }

        /* ===============================
           DB speichern (LINK)
        =============================== */
        Database::execute(
            "INSERT INTO user_oauth_accounts (user_id, provider, provider_user_id)
             VALUES (?, 'twitch', ?)
             ON DUPLICATE KEY UPDATE provider_user_id = VALUES(provider_user_id)",
            [$userId, $user['id']]
        );

        $_SESSION['flash_success'] = 'Twitch erfolgreich verknüpft.';
        header('Location: /dashboard/profile');
        exit;
    }
}
