<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Security;
use App\Core\Response;
use App\Services\PermissionResolver;

final class UserPermissionWidgetController
{
    /**
     * GET /admin/ajax/users/{id}/permissions
     */
    public function load(int $userId): void
    {
        Security::requireAdmin();

        $perms = PermissionResolver::resolveWithReasons($userId);

        Response::json($perms);
    }
}
