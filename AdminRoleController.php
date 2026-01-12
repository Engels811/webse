<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

final class AdminRoleController
{
    /* =========================================================
       ROLE GUARD – NUR HÖCHSTE RECHTE
       Zugriff: superadmin | owner
    ========================================================= */

    private function requireSuperadmin(): void
    {
        if (empty($_SESSION['user']) || empty($_SESSION['user']['role'])) {
            http_response_code(403);
            View::render('errors/403', ['title' => 'Zugriff verweigert']);
            exit;
        }

        $role = $_SESSION['user']['role'];

        if (!in_array($role, ['superadmin', 'owner'], true)) {
            http_response_code(403);
            View::render('errors/403', ['title' => 'Zugriff verweigert']);
            exit;
        }
    }

    /* =========================================================
       EDIT – ROLLE BEARBEITEN
    ========================================================= */

    public function edit(int $roleId): void
    {
        $this->requireSuperadmin();

        $role = Database::fetch(
            "SELECT * FROM roles WHERE id = ?",
            [$roleId]
        );

        if (!$role) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Rolle nicht gefunden']);
            return;
        }

        // Hinweis: Permissions bleiben DB-seitig bestehen,
        // auch wenn sie aktuell nicht mehr aktiv genutzt werden
        $perms = Database::fetchAll(
            "SELECT * FROM permissions ORDER BY name ASC"
        ) ?? [];

        $assigned = Database::fetchAll(
            "SELECT permission_id
             FROM role_permissions
             WHERE role_id = ?",
            [$roleId]
        ) ?? [];

        View::render('admin/role_permissions', [
            'title'     => 'Rollenrechte bearbeiten',
            'role'      => $role,
            'perms'     => $perms,
            'assigned'  => array_column($assigned, 'permission_id')
        ]);
    }

    /* =========================================================
       SAVE – ROLLENRECHTE SPEICHERN
    ========================================================= */

    public function save(): void
    {
        $this->requireSuperadmin();
        Security::checkCsrf();

        $roleId = (int)($_POST['role_id'] ?? 0);

        if ($roleId <= 0) {
            http_response_code(400);
            exit('Ungültige Rolle');
        }

        Database::execute(
            "DELETE FROM role_permissions WHERE role_id = ?",
            [$roleId]
        );

        foreach ($_POST['permissions'] ?? [] as $permId) {
            Database::execute(
                "INSERT INTO role_permissions (role_id, permission_id)
                 VALUES (?, ?)",
                [$roleId, (int)$permId]
            );
        }

        $_SESSION['flash_success'] = 'Rollenrechte wurden gespeichert.';
        header('Location: /admin/roles');
        exit;
    }
}
