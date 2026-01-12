<?php
declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Core\Security;
use App\Core\View;
use App\Core\Database;

final class SecurityController
{
    /**
     * GET /dashboard/security
     * Sicherheitsübersicht des Users
     */
    public function index(): void
    {
        // 🔐 EINZIGER Login-Gatekeeper
        Security::requireLogin();

        $userId = Security::userId();

        /* =========================
           AKTIVE SESSIONS
        ========================= */
        $sessions = Database::fetchAll(
            'SELECT
                session_id,
                ip_address AS ip,
                user_agent,
                created_at
             FROM login_logs
             WHERE user_id = ?
               AND success = 1
             ORDER BY created_at DESC
             LIMIT 10',
            [$userId]
        ) ?? [];

        /* =========================
           LOGIN-HISTORIE
        ========================= */
        $loginHistory = Database::fetchAll(
            'SELECT created_at
             FROM login_logs
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT 15',
            [$userId]
        ) ?? [];

        /* =========================
           GEMERKTE GERÄTE
        ========================= */
        $devices = Database::fetchAll(
            'SELECT id, created_at
             FROM trusted_devices
             WHERE user_id = ?
             ORDER BY created_at DESC',
            [$userId]
        ) ?? [];

        /* =========================
           USER EXTRA (2FA ETC.)
        ========================= */
        $userExtra = Database::fetch(
            'SELECT twofa_enabled, login_alerts_enabled
             FROM users
             WHERE id = ?',
            [$userId]
        ) ?? [];

        /* =========================
           VIEW
        ========================= */
        View::render('dashboard/security', [
            'title'        => 'Sicherheit',
            'user'         => array_merge(Security::user(), $userExtra),
            'sessions'     => $sessions,
            'loginHistory' => $loginHistory,
            'devices'      => $devices,
        ]);
    }
}
