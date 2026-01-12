<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Security;
use App\Core\View;
use App\Core\Database;
use App\Services\PermissionResolver;

final class UserPermissionExplainController
{
    /**
     * GET /admin/users/{id}/permissions
     */
    public function show(int $userId): void
    {
        Security::requireAdmin();

        $user = Database::fetch(
            "SELECT id, username, email FROM users WHERE id = ?",
            [$userId]
        );

        if (!$user) {
            View::renderError('errors/404', [], 404);
            return;
        }

        $permissions = PermissionResolver::resolveWithReasons($userId);

        View::render('admin/users/permissions', [
            'title'       => 'Berechtigungen – ' . $user['username'],
            'user'        => $user,
            'permissions' => $permissions
        ]);
    }
}
