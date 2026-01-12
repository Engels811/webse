<?php
declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Database;
use App\Models\UserOAuthAccount;

final class DiscordCallbackController
{
    /**
     * GET /auth/discord/callback
     */
    public function handle(): void
    {
        if (
            empty($_GET['state']) ||
            $_GET['state'] !== ($_SESSION['oauth_state'] ?? null)
        ) {
            http_response_code(403);
            echo 'Ungültiger OAuth State';
            return;
        }

        unset($_SESSION['oauth_state']);

        $config = require BASE_PATH . '/app/config/oauth.php';

        $token = json_decode(
            file_get_contents(
                'https://discord.com/api/oauth2/token',
                false,
                stream_context_create([
                    'http' => [
                        'method'  => 'POST',
                        'header'  => 'Content-Type: application/x-www-form-urlencoded',
                        'content' => http_build_query([
                            'client_id'     => $config['discord']['client_id'],
                            'client_secret' => $config['discord']['client_secret'],
                            'grant_type'    => 'authorization_code',
                            'code'          => $_GET['code'],
                            'redirect_uri'  => $config['discord']['redirect_uri'],
                        ])
                    ]
                ])
            ),
            true
        );

        $accessToken = $token['access_token'] ?? null;
        if (!$accessToken) {
            echo 'Discord Token Fehler';
            return;
        }

        $user = json_decode(
            file_get_contents(
                'https://discord.com/api/users/@me',
                false,
                stream_context_create([
                    'http' => [
                        'header' => "Authorization: Bearer {$accessToken}"
                    ]
                ])
            ),
            true
        );

        $db = Database::get();

        $oauthData = [
            'provider'          => 'discord',
            'provider_user_id'  => $user['id'],
            'username'          => $user['username'],
            'email'             => $user['email'] ?? null,
            'avatar'            => $user['avatar'] ?? null,
        ];

        // 🔁 Hier kommt die bereits erklärte Account-Linking-Logik rein
        // (findByProvider → link → login)

        // Beispiel:
        $_SESSION['user_id'] = /* ermittelter User */;
        header('Location: /dashboard');
        exit;
    }
}
