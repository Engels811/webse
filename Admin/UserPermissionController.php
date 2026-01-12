<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Security;
use App\Core\Database;
use App\Services\Permissions\EffectivePermissionService;

final class UserPermissionController
{
    /**
     * Route: /admin/users/{id}/permissions
     * Liefert JSON fürs Sidepanel
     */
    public function show(int $userId): void
    {
        Security::require('users.view');

        $user = Database::fetch(
            "SELECT u.id, u.username, r.label AS role_label
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? LIMIT 1",
            [$userId]
        );

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }

        $effective = EffectivePermissionService::forUser($userId);

        echo json_encode([
            'user' => $user,
            'effective_permissions' => $effective,
        ]);
    }
}
