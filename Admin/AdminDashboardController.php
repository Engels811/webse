<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Database;
use App\Models\Ticket;
use App\Core\CronLogger;

final class AdminDashboardController
{
    /* =====================================================
       ADMIN HAUPT-DASHBOARD
       (für Admin-Menü-Kacheln inkl. Tickets)
    ===================================================== */
    public function index(): void
    {
        Security::requireAdmin();

        /* =========================
           BASIS-STATS
        ========================= */
        $stats = [];

        $stats['users']            = (int) Database::fetchColumn("SELECT COUNT(*) FROM users");
        $stats['forum_threads']    = (int) Database::fetchColumn("SELECT COUNT(*) FROM forum_threads");
        $stats['forum_posts']      = (int) Database::fetchColumn("SELECT COUNT(*) FROM forum_posts");
        $stats['gallery_items']    = (int) Database::fetchColumn("SELECT COUNT(*) FROM gallery_items");
        $stats['games']            = (int) Database::fetchColumn("SELECT COUNT(*) FROM games");
        $stats['game_categories']  = (int) Database::fetchColumn("SELECT COUNT(*) FROM game_categories");
        $stats['partners']         = (int) Database::fetchColumn("SELECT COUNT(*) FROM partners");
        $stats['playlists']        = (int) Database::fetchColumn("SELECT COUNT(*) FROM playlists");
        $stats['hardware_items']   = (int) Database::fetchColumn("SELECT COUNT(*) FROM hardware_items");
        $stats['hardware_setups']  = (int) Database::fetchColumn("SELECT COUNT(*) FROM hardware_setups");
        $stats['twitch_vods']      = (int) Database::fetchColumn("SELECT COUNT(*) FROM twitch_vods");

        /* =========================
           MAIL & SYSTEM STATS
        ========================= */
        // Mail Templates (DB + Dateien)
        try {
            $dbTemplates = (int) Database::fetchColumn("SELECT COUNT(*) FROM mail_templates WHERE is_active = 1");
            $stats['mail_templates'] = $dbTemplates;
        } catch (\Exception $e) {
            $stats['mail_templates'] = 0;
        }

        // Mail Logs
        try {
            $stats['mail_logs'] = (int) Database::fetchColumn("SELECT COUNT(*) FROM mail_logs");
        } catch (\Exception $e) {
            $stats['mail_logs'] = 0;
        }

        // Rollen
        try {
            $stats['roles'] = (int) Database::fetchColumn("SELECT COUNT(*) FROM roles");
        } catch (\Exception $e) {
            $stats['roles'] = 5; // Fallback
        }

        // AGB Zustimmungen
        try {
            $stats['agb_consents'] = (int) Database::fetchColumn("SELECT COUNT(DISTINCT user_id) FROM agb_consents");
        } catch (\Exception $e) {
            $stats['agb_consents'] = 0;
        }

        // Offene Reports
        try {
            $stats['reports_open'] = (int) Database::fetchColumn("SELECT COUNT(*) FROM reports WHERE status = 'open'");
        } catch (\Exception $e) {
            $stats['reports_open'] = 0;
        }

        // Moderations-Aktionen (letzte 30 Tage)
        try {
            $stats['mod_actions'] = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM moderation_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
        } catch (\Exception $e) {
            $stats['mod_actions'] = 0;
        }

        // Aktivitäts-Logs (letzte 7 Tage)
        try {
            $stats['activity_logs'] = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );
        } catch (\Exception $e) {
            $stats['activity_logs'] = 0;
        }

        // Debug Logs
        try {
            $stats['debug_logs'] = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM debug_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );
        } catch (\Exception $e) {
            $stats['debug_logs'] = 0;
        }

        // APIs
        try {
            $stats['apis_total'] = (int) Database::fetchColumn("SELECT COUNT(*) FROM api_configs");
            $stats['apis_active'] = (int) Database::fetchColumn("SELECT COUNT(*) FROM api_configs WHERE is_active = 1");
            $stats['api_keys'] = (int) Database::fetchColumn("SELECT COUNT(*) FROM api_keys WHERE is_active = 1");
        } catch (\Exception $e) {
            $stats['apis_total'] = 0;
            $stats['apis_active'] = 0;
            $stats['api_keys'] = 0;
        }

        // Tasks
        try {
            $stats['tasks_total'] = (int) Database::fetchColumn("SELECT COUNT(*) FROM tasks");
            $stats['tasks_running'] = (int) Database::fetchColumn("SELECT COUNT(*) FROM tasks WHERE status = 'running'");
            $stats['tasks_due'] = (int) Database::fetchColumn("SELECT COUNT(*) FROM tasks WHERE due_at <= NOW() AND status = 'pending'");
        } catch (\Exception $e) {
            $stats['tasks_total'] = 0;
            $stats['tasks_running'] = 0;
            $stats['tasks_due'] = 0;
        }

        // Files
        try {
            $stats['total_files'] = (int) Database::fetchColumn("SELECT COUNT(*) FROM files WHERE type = 'file'");
            $stats['total_folders'] = (int) Database::fetchColumn("SELECT COUNT(*) FROM files WHERE type = 'folder'");
            
            $totalSize = (int) Database::fetchColumn("SELECT SUM(size) FROM files WHERE type = 'file'");
            $stats['total_size'] = round($totalSize / 1024 / 1024, 2) . ' MB';
        } catch (\Exception $e) {
            $stats['total_files'] = 0;
            $stats['total_folders'] = 0;
            $stats['total_size'] = '0 MB';
        }

        // System Health
        $stats['system_health'] = 100;
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            if ($load[0] > 4) $stats['system_health'] = 75;
            if ($load[0] > 8) $stats['system_health'] = 50;
        }

        // Ticket Archive
        try {
            $stats['tickets_archived'] = (int) Database::fetchColumn("SELECT COUNT(*) FROM tickets WHERE status = 'closed'");
        } catch (\Exception $e) {
            $stats['tickets_archived'] = 0;
        }

        /* =========================
           TICKETS – STATS (WICHTIG)
        ========================= */
        $ticketStats = Ticket::stats();

        $stats['tickets_total'] = (int) $ticketStats['total'];
        $stats['tickets_open']  = (int) $ticketStats['open'];

        /* =========================
           VIEW
        ========================= */
        View::render('admin/dashboard/index', [
            'title' => 'Admin Dashboard',
            'stats' => $stats
        ]);
    }

    /* =====================================================
       MODERATION DASHBOARD
       (Reports · Appeals · Tickets)
    ===================================================== */
    public function moderation(): void
    {
        Security::requireTeam();

        /* =========================
           REPORTS PRO TAG (14 TAGE)
        ========================= */
        $reportsPerDay = Database::fetchAll(
            "SELECT
                DATE(created_at) AS day,
                COUNT(*) AS count
             FROM reports
             GROUP BY day
             ORDER BY day DESC
             LIMIT 14"
        ) ?: [];

        /* =========================
           MOD-AKTIONEN PRO TEAMMITGLIED
        ========================= */
        $modActions = Database::fetchAll(
            "SELECT
                created_by,
                COUNT(*) AS count
             FROM user_actions
             GROUP BY created_by
             ORDER BY count DESC"
        ) ?: [];

        /* =========================
           OFFENE COUNTER
        ========================= */
        $openReports = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM reports WHERE status = 'open'"
        );

        $openAppeals = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM user_appeals WHERE status = 'open'"
        );

        /* =========================
           TICKETS – COUNTER
        ========================= */
        $ticketStats = Ticket::stats();

        $openTickets     = (int) $ticketStats['open'];
        $answeredTickets = (int) $ticketStats['answered'];

        /* =========================
           CRON STATUS
        ========================= */
        $cronStatus = CronLogger::lastRun('moderation_cleanup');

        /* =========================
           VIEW
        ========================= */
        View::render('admin/dashboard/moderation', [
            'title'           => 'Moderations-Dashboard',

            // Reports & Appeals
            'reportsPerDay'   => $reportsPerDay,
            'modActions'      => $modActions,
            'openReports'     => $openReports,
            'openAppeals'     => $openAppeals,

            // Tickets
            'openTickets'     => $openTickets,
            'answeredTickets' => $answeredTickets,

            // System
            'cronStatus'      => $cronStatus
        ]);
    }
}