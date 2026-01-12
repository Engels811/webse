<?php
declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Security;

final class TwitchController
{
    /**
     * GET /auth/twitch
     */
    public function redirect(): void
    {

        $config = require BASE_PATH . '/app/config/oauth.php';
        $twitch = $config['twitch'];

        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state_twitch'] = $state;

        $params = [
            'client_id'     => $twitch['client_id'],
            'redirect_uri'  => $twitch['redirect_uri'],
            'response_type' => 'code',
            'scope'         => $twitch['scope'],
            'state'         => $state,
        ];

        header('Location: '.$twitch['authorize_url'].'?'.http_build_query($params));
        exit;
    }
}
