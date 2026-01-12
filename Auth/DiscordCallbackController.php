<?php
declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Database;
use App\Services\Audit\AuditService;

final class DiscordCallbackController
{
    /**
     * GET /auth/discord/callback
     */
    public function handle(): void
    {
        /* =========================================================
           STATE + CODE PRÜFEN
        ========================================================= */
        if (
            empty($_GET['code']) ||
            empty($_GET['state']) ||
            $_GET['state'] !== ($_SESSION['oauth_state_discord'] ?? null)
        ) {
            http_response_code(400);
            exit('Ungültiger Discord OAuth State');
        }

        unset($_SESSION['oauth_state_discord']);

        $config  = require BASE_PATH . '/app/config/oauth.php';
        $discord = $config['discord'];

        /* =========================================================
           ACCESS TOKEN HOLEN
        ========================================================= */
        $tokenResponse = file_get_contents(
            $discord['token_url'],
            false,
            stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => http_build_query([
                        'client_id'     => $discord['client_id'],
                        'client_secret' => $discord['client_secret'],
                        'grant_type'    => 'authorization_code',
                        'code'          => $_GET['code'],
                        'redirect_uri'  => $discord['redirect_uri'],
                    ])
                ]
            ])
        );

        $token = json_decode($tokenResponse ?: '', true);
        $accessToken = $token['access_token'] ?? null;

        if (!$accessToken) {
            http_response_code(400);
            exit('Discord Token Fehler');
        }

        /* =========================================================
           DISCORD USER (MIT E-MAIL)
        ========================================================= */
        $userResponse = file_get_contents(
            $discord['user_url'],
            false,
            stream_context_create([
                'http' => [
                    'header' => "Authorization: Bearer {$accessToken}"
                ]
            ])
        );

        $discordUser = json_decode($userResponse ?: '', true);

        if (empty($discordUser['id']) || empty($discordUser['email'])) {
            http_response_code(400);
            exit('Discord User oder E-Mail fehlt');
        }

        $discordId = (string)$discordUser['id'];
        $email     = strtolower(trim($discordUser['email']));
        $username  = $discordUser['username'] ?? 'discord_user';

        /* =========================================================
           FALL 1: USER EXISTIERT (E-MAIL MATCH)
        ========================================================= */
        $existingUser = Database::fetch(
            "SELECT
                u.id,
                u.username,
                u.email,
                u.role_id,
                r.level AS role_level
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.email = ?
             LIMIT 1",
            [$email]
        );

        if ($existingUser) {

            Database::execute(
                "INSERT INTO user_oauth_accounts
                 (user_id, provider, provider_user_id)
                 VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE provider_user_id = VALUES(provider_user_id)",
                [
                    (int)$existingUser['id'],
                    'discord',
                    $discordId
                ]
            );

            session_regenerate_id(true);

            $_SESSION['user'] = [
                'id'         => (int)$existingUser['id'],
                'username'   => $existingUser['username'],
                'email'      => $existingUser['email'],
                'role_id'    => (int)$existingUser['role_id'],
                'role_level' => (int)($existingUser['role_level'] ?? 0),
            ];

            AuditService::log(
                (int)$existingUser['id'],
                'user.oauth.discord.linked_by_email',
                'user',
                (int)$existingUser['id'],
                ['discord_id' => $discordId]
            );

            header('Location: /dashboard');
            exit;
        }

        /* =========================================================
           FALL 2: NEUER USER
        ========================================================= */
        Database::execute(
            "INSERT INTO users (username, email, created_at)
             VALUES (?, ?, NOW())",
            [$username, $email]
        );

        $userId = (int)Database::lastInsertId();

        Database::execute(
            "INSERT INTO user_oauth_accounts
             (user_id, provider, provider_user_id)
             VALUES (?,?,?)",
            [$userId, 'discord', $discordId]
        );

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'         => $userId,
            'username'   => $username,
            'email'      => $email,
            'role_id'    => 0,
            'role_level' => 0,
        ];

        AuditService::log(
            $userId,
            'user.oauth.discord.register',
            'user',
            $userId,
            ['discord_id' => $discordId]
        );

        header('Location: /dashboard');
        exit;
    }
}
