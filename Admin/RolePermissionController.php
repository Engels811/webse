<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Response;
use App\Core\Security;

final class RolePermissionController
{
    /**
     * POST /admin/roles/permissions/toggle
     * 
     * PROBLEM: Permissions werden nicht in DB gespeichert
     * LÖSUNG: Verbesserte Validierung und Error-Handling
     */
    public function toggle(): void
    {
        Security::requireOwner();
        Security::checkCsrf();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::json(['ok' => false, 'message' => 'Invalid method'], 405);
            return;
        }

        $roleId = (int)($_POST['role_id'] ?? 0);
        $permId = (int)($_POST['permission_id'] ?? 0);
        $value  = (int)($_POST['value'] ?? -1);

        // Validierung
        if ($roleId <= 0 || $permId <= 0 || !in_array($value, [0, 1], true)) {
            Response::json([
                'ok' => false,
                'message' => 'Ungültige Parameter',
                'debug' => [
                    'role_id' => $roleId,
                    'permission_id' => $permId,
                    'value' => $value
                ]
            ], 400);
            return;
        }

        $db = Database::getPdo();

        try {
            // Prüfe ob Rolle existiert
            $roleExists = $db->prepare("SELECT id FROM roles WHERE id = ?");
            $roleExists->execute([$roleId]);
            if (!$roleExists->fetch()) {
                Response::json(['ok' => false, 'message' => 'Rolle nicht gefunden'], 404);
                return;
            }

            // Prüfe ob Permission existiert
            $permExists = $db->prepare("SELECT id FROM permissions WHERE id = ?");
            $permExists->execute([$permId]);
            if (!$permExists->fetch()) {
                Response::json(['ok' => false, 'message' => 'Permission nicht gefunden'], 404);
                return;
            }

            if ($value === 1) {
                // ➕ Permission zuweisen
                $stmt = $db->prepare("
                    INSERT INTO permission_role (permission_id, role_id, created_at)
                    VALUES (?, ?, NOW())
                    ON DUPLICATE KEY UPDATE permission_id = permission_id
                ");
                $result = $stmt->execute([$permId, $roleId]);
                
                if (!$result) {
                    throw new \Exception('Insert failed: ' . implode(', ', $stmt->errorInfo()));
                }
                
                $action = 'assigned';
            } else {
                // ➖ Permission entfernen
                $stmt = $db->prepare("
                    DELETE FROM permission_role
                    WHERE permission_id = ? AND role_id = ?
                ");
                $result = $stmt->execute([$permId, $roleId]);
                
                if (!$result) {
                    throw new \Exception('Delete failed: ' . implode(', ', $stmt->errorInfo()));
                }
                
                $action = 'removed';
            }

            // Verifiziere die Änderung
            $verify = $db->prepare("
                SELECT COUNT(*) as count 
                FROM permission_role 
                WHERE permission_id = ? AND role_id = ?
            ");
            $verify->execute([$permId, $roleId]);
            $verifyResult = $verify->fetch(\PDO::FETCH_ASSOC);
            
            $expectedCount = ($value === 1) ? 1 : 0;
            $actualCount = (int)$verifyResult['count'];
            
            if ($actualCount !== $expectedCount) {
                throw new \Exception("Verification failed. Expected {$expectedCount}, got {$actualCount}");
            }

            // Log the action
            $this->logPermissionChange($roleId, $permId, $action);

            Response::json([
                'ok' => true,
                'permission_id' => $permId,
                'role_id' => $roleId,
                'value' => $value,
                'action' => $action,
                'verified' => true,
                'message' => 'Permission erfolgreich ' . ($value === 1 ? 'zugewiesen' : 'entfernt')
            ]);

        } catch (\Throwable $e) {
            Response::json([
                'ok' => false,
                'message' => 'Datenbankfehler',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * GET /admin/roles/permissions/verify/{roleId}
     * 
     * Verifiziere alle Permissions einer Rolle
     */
    public function verify(int $roleId): void
    {
        Security::requireOwner();

        $db = Database::getPdo();
        
        $stmt = $db->prepare("
            SELECT 
                pr.id,
                pr.permission_id,
                pr.role_id,
                p.name as permission_name,
                p.label as permission_label,
                pr.created_at
            FROM permission_role pr
            JOIN permissions p ON pr.permission_id = p.id
            WHERE pr.role_id = ?
            ORDER BY p.category, p.name
        ");
        $stmt->execute([$roleId]);
        $permissions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        Response::json([
            'ok' => true,
            'role_id' => $roleId,
            'permissions' => $permissions,
            'count' => count($permissions)
        ]);
    }

    private function logPermissionChange(int $roleId, int $permId, string $action): void
    {
        try {
            $userId = $_SESSION['user']['id'] ?? 0;
            
            Database::execute("
                INSERT INTO audit_logs (
                    user_id,
                    action,
                    entity_type,
                    entity_id,
                    details,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ", [
                $userId,
                "role_permission_{$action}",
                'role',
                $roleId,
                json_encode(['permission_id' => $permId])
            ]);
        } catch (\Throwable $e) {
            // Log failure shouldn't break the main operation
            error_log("Failed to log permission change: " . $e->getMessage());
        }
    }
}
