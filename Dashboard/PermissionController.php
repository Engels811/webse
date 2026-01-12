<?php
declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Core\Security;
use App\Core\Response;
use App\Core\Database;
use App\Services\PermissionResolver;

final class PermissionController
{
    /**
     * GET /dashboard/me/permissions
     * Effektive Permissions + Rolle des eingeloggten Users
     */
    public function mine(): void
    {
        Security::requireLogin();

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        if ($userId <= 0) {
            Response::json([
                'role' => null,
                'effective_permissions' => [],
                'role_hash' => null
            ]);
            return;
        }

        /* =========================================================
           USER + ROLLE (KRITISCH)
        ========================================================= */
        $user = Database::fetch(
            "SELECT
                r.name  AS role_name,
                r.label AS role_label
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = ?
             LIMIT 1",
            [$userId]
        );

        /* =========================================================
           EFFECTIVE PERMISSIONS (ROLE + TWITCH)
        ========================================================= */
        $perms = PermissionResolver::resolveForUser($userId);

        $list = [];
        foreach ($perms as $p) {
            $list[] = [
                'key'          => (string)$p['name'],
                'label'        => (string)$p['label'],
                'category'     => (string)$p['category'],
                'source'       => (string)$p['source'], // role | twitch
                'source_label' => $p['source'] === 'twitch'
                    ? 'Twitch Abo'
                    : 'Rolle'
            ];
        }

        /* =========================================================
           STABILER HASH (nur Keys + Source)
        ========================================================= */
        $hashBase = array_map(
            fn($p) => $p['key'] . ':' . $p['source'],
            $list
        );
        sort($hashBase);

        Response::json([
            'role' => [
                'name'  => $user['role_name']  ?? 'user',
                'label' => $user['role_label'] ?? ucfirst($user['role_name'] ?? 'User')
            ],
            'effective_permissions' => $list,
            'role_hash' => md5(implode('|', $hashBase))
        ]);
    }
}
