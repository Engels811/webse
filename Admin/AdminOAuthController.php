<?php
namespace App\Controllers;

use App\Models\UserOAuthAccount;
use App\Core\Database;

class AdminOAuthController
{
    public function index(): void
    {
        $db = Database::get();
        $connections = UserOAuthAccount::all($db);
        require BASE_PATH . '/app/Views/admin/oauth_connections.php';
    }
}
