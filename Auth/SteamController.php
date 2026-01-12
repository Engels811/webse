<?php
declare(strict_types=1);

namespace App\Controllers\Auth;

final class SteamController
{
    public function redirect(): void
    {
        $config = require BASE_PATH . '/app/config/oauth.php';

        $params = [
            'openid.ns'         => 'http://specs.openid.net/auth/2.0',
            'openid.mode'       => 'checkid_setup',
            'openid.return_to'  => $config['steam']['redirect_uri'],
            'openid.realm'      => $config['steam']['realm'],
            'openid.identity'   => 'http://specs.openid.net/auth/2.0/identifier_select',
            'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
        ];

        header('Location: '.$config['steam']['openid_url'].'?'.http_build_query($params));
        exit;
    }
}
