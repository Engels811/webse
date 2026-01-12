<?php
declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Security;

final class DiscordController
{
    public function redirect(): void
    {
        $config = require BASE_PATH . '/app/config/oauth.php';
        $discord = $config['discord'];

        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state_discord'] = $state;

        header('Location: ' . $discord['authorize_url'] . '?' . http_build_query([
            'client_id'     => $discord['client_id'],
            'redirect_uri'  => $discord['redirect_uri'],
            'response_type' => 'code',
            'scope'         => $discord['scope'],
            'state'         => $state,
        ]));
        exit;
    }
}
