<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Security;
use App\Core\Database;
use App\Core\Response;
use App\Services\AuditService;

final class AdminUsersController
{
    /**
     * GET /admin/users
     * User-Übersicht mit Rollen
     */
    public function index(): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.users.view');

        $users = Database::fetchAll(
            "SELECT
                u.id,
                u.username,
                u.email,
                u.created_at,
                u.last_seen,
                u.banned_at,

                r.id    AS role_id,
                r.name  AS role_name,
                r.level AS role_level,

                MAX(CASE WHEN oa.provider = 'discord' THEN 1 ELSE 0 END) AS has_discord,
                MAX(CASE WHEN oa.provider = 'twitch'  THEN 1 ELSE 0 END) AS has_twitch,
                MAX(CASE WHEN oa.provider = 'steam'   THEN 1 ELSE 0 END) AS has_steam
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             LEFT JOIN user_oauth_accounts oa ON oa.user_id = u.id
             GROUP BY u.id
             ORDER BY u.created_at DESC"
        ) ?? [];

        $roles = Database::fetchAll(
            "SELECT id, name, level
             FROM roles
             ORDER BY level DESC"
        ) ?? [];

        View::render('admin/users/index', [
            'title' => 'Benutzerverwaltung',
            'users' => $users,
            'roles' => $roles
        ]);
    }

    /**
     * GET /admin/users/{id}/details
     * AJAX - User Details für Side Panel
     */
    public function details(int $id): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.users.view');

        $user = Database::fetch(
            "SELECT
                u.username,
                u.email,
                u.last_seen,
                u.banned_at,
                u.created_at,

                MAX(CASE WHEN oa.provider = 'discord' THEN 1 ELSE 0 END) AS has_discord,
                MAX(CASE WHEN oa.provider = 'twitch'  THEN 1 ELSE 0 END) AS has_twitch,
                MAX(CASE WHEN oa.provider = 'steam'   THEN 1 ELSE 0 END) AS has_steam
             FROM users u
             LEFT JOIN user_oauth_accounts oa ON oa.user_id = u.id
             WHERE u.id = ?
             GROUP BY u.id
             LIMIT 1",
            [$id]
        );

        if (!$user) {
            Response::json(['error' => 'User not found'], 404);
            return;
        }

        $oauth = [];
        foreach (['discord', 'twitch', 'steam'] as $p) {
            if (!empty($user['has_' . $p])) {
                $oauth[] = strtoupper($p);
            }
        }

        Response::json([
            'username'  => $user['username'],
            'email'     => $user['email'] ?? '—',
            'status'    => $user['banned_at'] ? 'Gesperrt' : 'Aktiv',
            'oauth'     => $oauth,
            'last_seen' => $user['last_seen']
                ? date('d.m.Y H:i', strtotime($user['last_seen']))
                : '—',
            'created_at' => $user['created_at']
                ? date('d.m.Y H:i', strtotime($user['created_at']))
                : '—'
        ]);
    }

    /**
     * GET /admin/users/{id}/audit
     * AJAX - User Audit Logs
     */
    public function audit(int $id): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.users.view');
    
        $logs = AuditService::getForEntity('user', $id, 20);
    
        $out = [];
        foreach ($logs as $log) {
            $out[] = [
                'action'      => $log['action'],
                'description' => $log['description'] ?? $log['action'],
                'date'        => date('d.m.Y H:i', strtotime($log['created_at'])),
                'actor'       => $log['actor_username'] ?? 'System'
            ];
        }
    
        Response::json($out);
    }

    /**
     * POST /admin/users/update-role
     * Rolle eines Users ändern
     */
    public function updateRole(): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.users.role.update');
        Security::verifyCsrf();

        $userId = (int)($_POST['user_id'] ?? 0);
        $roleId = (int)($_POST['role_id'] ?? 0);

        if ($userId <= 0 || $roleId <= 0) {
            Response::error(400);
            return;
        }

        // User laden
        $target = Database::fetch(
            "SELECT
                u.id,
                u.username,
                r.level AS role_level
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.id = ?
             LIMIT 1",
            [$userId]
        );

        if (!$target) {
            Response::error(404);
            return;
        }

        // Neue Rolle laden
        $newRole = Database::fetch(
            "SELECT id, name, level
             FROM roles
             WHERE id = ?
             LIMIT 1",
            [$roleId]
        );

        if (!$newRole) {
            Response::error(400);
            return;
        }

        // Sicherheitsprüfung:
        // Man kann keine User mit höherem/gleichem Level ändern
        // Man kann keine Rolle vergeben, die höher als das eigene Level ist
        if ($target['role_level'] >= Security::roleLevel()
            || $newRole['level'] >= Security::roleLevel()) {
            Response::error(403);
            return;
        }

        // Rolle aktualisieren
        Database::execute(
            "UPDATE users SET role_id = ? WHERE id = ?",
            [$newRole['id'], $userId]
        );

        // Audit Log
        AuditService::log(
            'user.role.changed',
            'user',
            $userId,
            null,
            [
                'new_role' => $newRole['name'],
                'new_role_level' => $newRole['level']
            ]
        );

        Response::redirect('/admin/users');
    }

    /**
     * POST /admin/users/toggle-ban
     * User bannen/entbannen
     */
    public function toggleBan(): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.users.ban.toggle');
        Security::verifyCsrf();

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            Response::error(400);
            return;
        }

        // Selbst-Ban verhindern
        if ($userId === Security::userId()) {
            Response::error(403);
            return;
        }

        // User laden für Level-Check
        $target = Database::fetch(
            "SELECT u.id, r.level
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.id = ?
             LIMIT 1",
            [$userId]
        );

        if (!$target) {
            Response::error(404);
            return;
        }

        // Nur User mit niedrigerem Level bannen
        if ($target['level'] >= Security::roleLevel()) {
            Response::error(403);
            return;
        }

        // Ban togglen
        Database::execute(
            "UPDATE users
             SET banned_at = IF(banned_at IS NULL, NOW(), NULL),
                 banned_by = ?,
                 banned_reason = IF(banned_at IS NULL, 'Admin-Sperre', NULL)
             WHERE id = ?",
            [Security::userId(), $userId]
        );

        // Audit Log
        AuditService::log(
            'user.ban.toggled',
            'user',
            $userId
        );

        Response::redirect('/admin/users');
    }

    /**
     * POST /admin/users/unlink-oauth
     * OAuth-Verbindung eines Users entfernen
     */
    public function unlinkOAuth(): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.users.oauth.unlink');
        Security::verifyCsrf();

        $userId   = (int)($_POST['user_id'] ?? 0);
        $provider = $_POST['provider'] ?? '';

        if (
            $userId <= 0 ||
            !in_array($provider, ['discord', 'twitch', 'steam'], true)
        ) {
            Response::error(400);
            return;
        }

        // OAuth-Verbindung löschen
        Database::execute(
            "DELETE FROM user_oauth_accounts
             WHERE user_id = ? AND provider = ?",
            [$userId, $provider]
        );

        // Audit Log
        AuditService::log(
            'user.oauth.unlinked.admin',
            'user',
            $userId,
            ['provider' => $provider]
        );

        Response::redirect('/admin/users');
    }

    /**
     * POST /admin/users/delete
     * User komplett löschen (nur für Superadmin+)
     */
    public function delete(): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.users.delete');
        Security::verifyCsrf();

        $userId = (int)($_POST['id'] ?? 0);

        // Validierung
        if ($userId <= 0 || $userId === Security::userId()) {
            Response::error(403);
            return;
        }

        // User laden für Level-Check
        $target = Database::fetch(
            "SELECT u.id, u.username, r.level
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.id = ?
             LIMIT 1",
            [$userId]
        );

        if (!$target) {
            Response::error(404);
            return;
        }

        // Nur User mit niedrigerem Level löschen
        if ($target['level'] >= Security::roleLevel()) {
            Response::error(403);
            return;
        }

        // User löschen (Cascading Deletes sollten in DB konfiguriert sein)
        Database::execute(
            "DELETE FROM users WHERE id = ?",
            [$userId]
        );

        // Audit Log
        AuditService::log(
            'user.deleted',
            'user',
            $userId,
            null,
            ['username' => $target['username']]
        );

        Response::redirect('/admin/users');
    }
}