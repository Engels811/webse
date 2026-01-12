<?php
declare(strict_types=1);

namespace App\Controllers\Admin; // ✅ WICHTIG

use App\Core\Database;
use App\Core\Security;

final class RolePermissionAjaxController
{
    public function toggle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // ❗ ABSOLUTER FRÜH-EXIT
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'message' => 'Invalid method']);
            exit;
        }

        if (
            empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
        ) {
            echo json_encode(['ok' => false, 'message' => 'Not AJAX']);
            exit;
        }

        Security::verifyCsrf($_POST['csrf_token'] ?? null);

        if (!Security::can('roles.manage')) {
            echo json_encode(['ok' => false, 'message' => 'Forbidden']);
            exit;
        }

        $roleId       = (int)($_POST['role_id'] ?? 0);
        $permissionId = (int)($_POST['permission_id'] ?? 0);
        $value        = (int)($_POST['value'] ?? -1);

        if ($roleId <= 0 || $permissionId <= 0 || !in_array($value, [0, 1], true)) {
            echo json_encode(['ok' => false, 'message' => 'Bad data']);
            exit;
        }

        if ($value === 1) {
            Database::execute(
                "INSERT INTO permission_role (permission_id, role_id)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE permission_id = permission_id",
                [$permissionId, $roleId]
            );
        } else {
            Database::execute(
                "DELETE FROM permission_role
                 WHERE permission_id = ? AND role_id = ?",
                [$permissionId, $roleId]
            );
        }

        echo json_encode(['ok' => true]);
        exit;
    }
}
