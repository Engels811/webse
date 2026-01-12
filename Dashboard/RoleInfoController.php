<?php
declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Core\Security;
use App\Core\View;
use App\Core\Database;
use App\Services\PermissionResolver;

final class RoleInfoController
{
    public function show(): void
    {
        Security::requireLogin();

        $userId = (int)($_SESSION['user']['id'] ?? 0);

        $role = Database::fetch(
            "SELECT r.id, r.name, r.label, r.level, r.description
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = ?",
            [$userId]
        );

        $permissions = PermissionResolver::resolveForUser($userId);

        View::render('dashboard/role', [
            'title'       => 'Meine Rolle',
            'role'        => $role,
            'permissions' => $permissions
        ]);
    }
}
