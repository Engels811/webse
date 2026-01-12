<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Security;
use App\Core\Response;
use App\Core\Database;
use PDO;

final class AdminRolesController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::get();
    }

    /* =========================================================
       📊 ROLLENÜBERSICHT
    ========================================================= */

    public function index(): void
    {
        Security::requirePermission('roles.view');

        // Rollen laden
        $stmt = $this->db->query("
            SELECT
                r.id,
                r.name,
                r.label,
                r.level,
                r.color,
                r.description,
                COUNT(DISTINCT u.id) AS user_count,
                COUNT(DISTINCT pr.permission_id) AS permission_count
            FROM roles r
            LEFT JOIN users u ON u.role_id = r.id
            LEFT JOIN permission_role pr ON pr.role_id = r.id
            GROUP BY r.id, r.name, r.label, r.level, r.color, r.description
            ORDER BY r.level ASC
        ");

        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($roles as &$role) {
            $role['color'] = $role['color'] ?? '#6b7280';
            $role['label'] = $role['label'] ?? $role['name'];
            $role['description'] = $role['description'] ?? '';
            $role['permission_count'] = (int)($role['permission_count'] ?? 0);
            $role['user_count'] = (int)($role['user_count'] ?? 0);
        }
        unset($role);

        // Permissions laden
        $stmt = $this->db->query("
            SELECT 
                id,
                name,
                label,
                description,
                category
            FROM permissions
            ORDER BY category ASC, name ASC
        ");

        $allPermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $permissions = [];
        foreach ($allPermissions as $perm) {
            $category = $perm['category'] ?? 'Allgemein';
            if (!isset($permissions[$category])) {
                $permissions[$category] = [];
            }
            $permissions[$category][] = $perm;
        }

        // Zugewiesene Permissions laden
        $stmt = $this->db->query("
            SELECT 
                role_id,
                permission_id
            FROM permission_role
        ");

        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $assignedMap = [];
        foreach ($assignments as $assign) {
            $key = $assign['role_id'] . '_' . $assign['permission_id'];
            $assignedMap[$key] = true;
        }

        View::render('admin/roles/index', [
            'title' => 'Rollenverwaltung',
            'roles' => $roles,
            'permissions' => $permissions,
            'assignedMap' => $assignedMap
        ]);
    }

    /* =========================================================
       🔄 PERMISSION TOGGLE (AJAX) - FIXED VERSION
    ========================================================= */

    public function togglePermission(): void
    {
        // WICHTIG: Content-Type MUSS als erstes gesetzt werden
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            // 1. Nur POST erlauben
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode([
                    'success' => false,
                    'message' => 'Nur POST erlaubt'
                ]);
                return;
            }

            // 2. Berechtigung prüfen
            if (!Security::can('roles.manage')) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Keine Berechtigung'
                ]);
                return;
            }

            // 3. CSRF validieren - WICHTIG!
            // Falls du checkCsrf() verwendest:
            Security::checkCsrf();
            
            // ODER falls du validateCSRF() verwendest:
            // Security::validateCSRF();

            // 4. Parameter holen und validieren
            $roleId = (int)($_POST['role_id'] ?? 0);
            $permissionId = (int)($_POST['permission_id'] ?? 0);
            $value = (int)($_POST['value'] ?? -1);

            // Debug-Log (kannst du später entfernen)
            error_log("Toggle Request - Role: $roleId, Perm: $permissionId, Value: $value");

            if ($roleId <= 0 || $permissionId <= 0 || !in_array($value, [0, 1], true)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Ungültige Parameter',
                    'debug' => [
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'value' => $value
                    ]
                ]);
                return;
            }

            // 5. Rolle existiert?
            $stmt = $this->db->prepare("SELECT id FROM roles WHERE id = ?");
            $stmt->execute([$roleId]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Rolle nicht gefunden'
                ]);
                return;
            }

            // 6. Permission existiert?
            $stmt = $this->db->prepare("SELECT id FROM permissions WHERE id = ?");
            $stmt->execute([$permissionId]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Permission nicht gefunden'
                ]);
                return;
            }

            // 7. Toggle Operation
            if ($value === 1) {
                // Hinzufügen
                $stmt = $this->db->prepare("
                    INSERT IGNORE INTO permission_role (role_id, permission_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$roleId, $permissionId]);
                $action = 'zugewiesen';
            } else {
                // Entfernen
                $stmt = $this->db->prepare("
                    DELETE FROM permission_role
                    WHERE role_id = ? AND permission_id = ?
                ");
                $stmt->execute([$roleId, $permissionId]);
                $action = 'entfernt';
            }

            // 8. Erfolg
            echo json_encode([
                'success' => true,
                'message' => "Permission erfolgreich {$action}",
                'data' => [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'value' => $value
                ]
            ]);

        } catch (\PDOException $e) {
            error_log("DB Error in togglePermission: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Datenbankfehler'
            ]);
        } catch (\Exception $e) {
            error_log("Error in togglePermission: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Serverfehler: ' . $e->getMessage()
            ]);
        }
    }

    /* =========================================================
       ➕ ROLLE ERSTELLEN
    ========================================================= */

    public function create(): void
    {
        Security::requirePermission('roles.create');

        View::render('admin/roles/create', [
            'title' => 'Neue Rolle erstellen'
        ]);
    }

    public function store(): void
    {
        Security::requirePermission('roles.create');
        Security::checkCsrf();

        $name  = trim($_POST['name'] ?? '');
        $label = trim($_POST['label'] ?? '');
        $level = (int)($_POST['level'] ?? 0);
        $color = trim($_POST['color'] ?? '#6b7280');
        $description = trim($_POST['description'] ?? '');

        if ($name === '' || $label === '') {
            notify_ui('Rollenname und Label sind erforderlich', 'error');
            Response::redirect('/admin/roles/create');
            return;
        }

        $stmt = $this->db->prepare("SELECT id FROM roles WHERE name = ?");
        $stmt->execute([$name]);

        if ($stmt->fetch()) {
            notify_ui('Rollenname existiert bereits', 'error');
            Response::redirect('/admin/roles/create');
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO roles (name, label, level, color, description)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $label, $level, $color, $description]);

        notify_ui('Rolle erfolgreich erstellt', 'success');
        Response::redirect('/admin/roles');
    }

    /* =========================================================
       ✏️ ROLLE BEARBEITEN
    ========================================================= */

    public function edit(int $id): void
    {
        Security::requirePermission('roles.edit');

        $stmt = $this->db->prepare("
            SELECT id, name, label, level, color, description 
            FROM roles 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$role) {
            notify_ui('Rolle nicht gefunden', 'error');
            Response::redirect('/admin/roles');
            return;
        }

        $role['color'] = $role['color'] ?? '#6b7280';
        $role['label'] = $role['label'] ?? $role['name'];
        $role['description'] = $role['description'] ?? '';

        View::render('admin/roles/edit', [
            'title' => 'Rolle bearbeiten',
            'role'  => $role
        ]);
    }

    public function update(int $id): void
    {
        Security::requirePermission('roles.edit');
        Security::checkCsrf();

        $name  = trim($_POST['name'] ?? '');
        $label = trim($_POST['label'] ?? '');
        $level = (int)($_POST['level'] ?? 0);
        $color = trim($_POST['color'] ?? '#6b7280');
        $description = trim($_POST['description'] ?? '');

        if ($name === '' || $label === '') {
            notify_ui('Rollenname und Label sind erforderlich', 'error');
            Response::redirect("/admin/roles/{$id}/edit");
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE roles
            SET name = ?, label = ?, level = ?, color = ?, description = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $label, $level, $color, $description, $id]);

        notify_ui('Rolle aktualisiert', 'success');
        Response::redirect("/admin/roles/{$id}/edit");
    }

    /* =========================================================
       🗑️ ROLLE LÖSCHEN
    ========================================================= */

    public function delete(int $id): void
    {
        Security::requirePermission('roles.delete');
        Security::checkCsrf();

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
        $stmt->execute([$id]);

        if ((int)$stmt->fetchColumn() > 0) {
            notify_ui('Rolle kann nicht gelöscht werden – Benutzer vorhanden', 'error');
            Response::redirect('/admin/roles');
            return;
        }

        $this->db->prepare("DELETE FROM permission_role WHERE role_id = ?")->execute([$id]);
        $this->db->prepare("DELETE FROM roles WHERE id = ?")->execute([$id]);

        notify_ui('Rolle gelöscht', 'success');
        Response::redirect('/admin/roles');
    }
}