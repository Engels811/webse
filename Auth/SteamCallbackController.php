<?php
declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Database;
use App\Services\AuditService;

final class SteamCallbackController
{
    public function handle(): void
    {
        if (empty($_GET['openid_claimed_id'])) {
            die('Steam Login fehlgeschlagen');
        }

        preg_match('#/id/(\d+)#', $_GET['openid_claimed_id'], $m);
        $steamId = $m[1] ?? null;
        if (!$steamId) die('Ungültige Steam ID');

        $config = require BASE_PATH . '/app/config/oauth.php';

        $json = json_decode(
            file_get_contents(
                $config['steam']['profile_api'] .
                '?key='.$config['steam']['api_key'].
                '&steamids='.$steamId
            ),
            true
        );

        $player = $json['response']['players'][0] ?? null;
        if (!$player) die('Steam Profil nicht gefunden');

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        if ($userId <= 0) die('Nicht eingeloggt');

        Database::execute(
            "INSERT INTO user_oauth_accounts
             (user_id, provider, provider_user_id,
              steam_persona_name, steam_avatar, steam_profile_url)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                steam_persona_name = VALUES(steam_persona_name),
                steam_avatar = VALUES(steam_avatar),
                steam_profile_url = VALUES(steam_profile_url)",
            [
                $userId,
                'steam',
                $steamId,
                $player['personaname'],
                $player['avatarfull'],
                $player['profileurl'],
            ]
        );

        AuditService::log('user.oauth.steam.linked','user',$userId,null,null);

        $_SESSION['flash_success'] = 'Steam Konto verknüpft.';
        header('Location: /dashboard/profile');
        exit;
    }
}
