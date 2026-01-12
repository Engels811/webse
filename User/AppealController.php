<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Security;
use App\Core\Response;
use App\Models\Appeal;

final class AppealController
{
    /**
     * POST /appeal/store
     */
    public function store(): void
    {
        // 🔐 Login erzwingen
        Security::requireLogin();

        // 🛡️ CSRF prüfen
        Security::verifyCsrf();

        $actionId = (int)($_POST['action_id'] ?? 0);
        $message  = trim($_POST['message'] ?? '');

        if ($actionId <= 0 || $message === '') {
            Response::redirect('/profile');
            return;
        }

        $user = Security::user();

        Appeal::create(
            $user['username'],
            $actionId,
            $message
        );

        Response::redirect('/profile');
    }
}
