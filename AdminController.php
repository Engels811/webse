<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Security;
use App\Core\Database;
use PDO;
use PDOException;
use Throwable;
use App\Services\Twitch\TwitchFeature;
use App\Services\Twitch\TwitchTokenRepository;

final class AdminController
{
    public function index(): void
    {
        Security::requireTeam();

        /* =====================================================
           DASHBOARD STATS
        ===================================================== */
        $stats = [
            // CORE
            'users' => $this->safeCount('users'),
            'users_agb_accepted' => $this->safeCount('users', "agb_accepted_at IS NOT NULL"),
            'users_locked' => $this->safeCount('users', "account_locked = 1"),
            'logins_24h' => $this->safeCount('login_history', "created_at > (NOW() - INTERVAL 24 HOUR)"),
            'new_devices_24h' => $this->safeCountDistinct(
                'remembered_devices',
                'device_hash',
                "created_at > (NOW() - INTERVAL 24 HOUR)"
            ),

            // FORUM
            'forum_threads' => $this->safeCount('forum_threads'),
            'forum_posts' => $this->safeCount('forum_posts'),

            // GALLERY
            'gallery_items' => $this->safeCount('gallery_media'),

            // MEDIA
            'media_total' => $this->safeCount('media_videos'),
            'media_videos' => $this->safeCount('media_videos', "source_type = 'upload'"),
            'media_pending' => $this->safeCount('media_videos', "status = 'pending'"),
            'media_views' => 0,

            // COMMENTS
            'comments_total' => $this->safeCount('comments'),
            'comments_pending' => $this->safeCount('comments', "status = 'pending'"),
            'reactions_total' => $this->safeCount('reactions'),
            'trending_items' => 5,

            // LEGACY
            'videos' => $this->safeCount('videos'),

            // PLAYLISTS
            'playlists' => $this->safeCount('playlists'),

            // HARDWARE
            'hardware_items' => $this->safeCount('hardware_items'),
            'hardware_setups' => $this->safeCount('hardware_setups'),

            // GAMES
            'games' => $this->safeCount('games'),
            'game_categories' => $this->safeCount('game_categories'),

            // PARTNERS
            'partners' => $this->safeCount('partners'),

            // TWITCH (STATISTIKEN – NICHT API)
            'twitch_vods' => $this->safeCount('twitch_vods'),
            'twitch_events' => $this->safeCount('twitch_events'),
            'twitch_health' => 100,

            // TICKETS
            'tickets_open' => $this->safeCount('tickets', "status = 'open'"),
            'tickets_archived' => $this->safeCount('tickets', "status = 'closed'"),

            // MAIL
            'mail_templates' => $this->safeCount('mail_templates'),
            'mail_logs' => $this->safeCount('mail_logs'),
            'notification_logs' => $this->safeCount('notification_logs'),

            // SECURITY
            'security_score' => 100,
            'auto_blocks' => $this->safeCount('auto_blocks'),
            'rate_limit_hits' => $this->safeCount('rate_limit_hits', "DATE(created_at) = CURDATE()"),
            'system_alerts' => $this->safeCount('system_alerts', "status = 'active'"),
            'ip_blocks' => $this->safeCount('ip_blocks'),

            // CMS
            'cms_pages' => $this->safeCount('cms_pages'),
            'reports_open' => $this->safeCount('reports', "status = 'open'"),

            // MODERATION
            'mod_actions' => $this->safeCount('moderation_actions', "DATE(created_at) = CURDATE()"),
            'activity_logs' => $this->safeCount('audit_logs', "DATE(created_at) = CURDATE()"),
            'audit_logs' => $this->safeCount('audit_logs'),

            // API & TASKS
            'apis_active' => $this->safeCount('apis', "is_active = 1"),
            'apis_total' => $this->safeCount('apis'),
            'api_keys' => $this->safeCount('api_keys'),
            'tasks_running' => $this->safeCount('tasks', "status = 'running'"),
            'tasks_total' => $this->safeCount('tasks'),
            'tasks_due' => $this->safeCount('tasks', "next_run <= NOW() AND is_active = 1"),

            // FILES
            'total_files' => 0,
            'total_folders' => 0,
            'total_size' => '0 MB',

            // SYSTEM
            'system_health' => 100,
            'debug_logs' => $this->safeCount('debug_logs', "DATE(created_at) = CURDATE()"),

            // ROLES
            'roles' => $this->safeCount('roles'),

            // AGB
            'agb_consents' => $this->safeCount('users', "agb_accepted_at IS NOT NULL"),
        ];

        /* =====================================================
           TWITCH DEBUG WIDGET (SAFE / READ-ONLY)
        ===================================================== */
        $twitchEnabled = TwitchFeature::enabled();
        $twitchToken   = null;

        if ($twitchEnabled) {
            $twitchToken = TwitchTokenRepository::get();
        }

        View::render('admin/index', [
            'title'         => 'Admin Dashboard',
            'currentPage'   => 'admin',
            'stats'         => $stats,

            // 🎮 Widget-Daten
            'twitchWidget' => [
                'enabled' => $twitchEnabled,
                'token'   => $twitchToken,
            ],
        ]);
    }

    /* =====================================================
       🛡️ ULTRA-SICHERER COUNT
    ===================================================== */
    private function safeCount(string $table, ?string $where = null): int
    {
        try {
            $table = str_replace('`', '', $table);

            $sql = "SELECT COUNT(*) AS c FROM `{$table}`";
            if ($where) {
                $sql .= " WHERE {$where}";
            }

            $pdo = Database::get();
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return isset($row['c']) ? (int)$row['c'] : 0;

        } catch (PDOException $e) {
            error_log("AdminController::safeCount({$table}): " . $e->getMessage());
            return 0;
        } catch (Throwable $e) {
            error_log("AdminController::safeCount({$table}) Throwable: " . $e->getMessage());
            return 0;
        }
    }

    /* =====================================================
       🛡️ ULTRA-SICHERER COUNT DISTINCT
    ===================================================== */
    private function safeCountDistinct(
        string $table,
        string $column,
        ?string $where = null
    ): int {
        try {
            $table  = str_replace('`', '', $table);
            $column = str_replace('`', '', $column);

            $sql = "SELECT COUNT(DISTINCT `{$column}`) AS c FROM `{$table}`";
            if ($where) {
                $sql .= " WHERE {$where}";
            }

            $pdo = Database::get();
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return isset($row['c']) ? (int)$row['c'] : 0;

        } catch (PDOException $e) {
            error_log("AdminController::safeCountDistinct({$table}.{$column}): " . $e->getMessage());
            return 0;
        } catch (Throwable $e) {
            error_log("AdminController::safeCountDistinct({$table}.{$column}) Throwable: " . $e->getMessage());
            return 0;
        }
    }

    /* =====================================================
       AGB CONSENTS
    ===================================================== */
    public function agbConsents(): void
    {
        Security::requireTeam();

        try {
            $consents = Database::fetchAll("
                SELECT 
                    u.id,
                    u.username,
                    u.email,
                    u.avatar,
                    u.agb_version,
                    u.agb_accepted_at,
                    u.last_ip
                FROM users u
                WHERE u.agb_accepted_at IS NOT NULL
                ORDER BY u.agb_accepted_at DESC
            ");

            View::render('admin/agb_consents', [
                'title'       => 'AGB-Zustimmungen',
                'currentPage' => 'admin',
                'consents'    => $consents,
            ]);

        } catch (Throwable $e) {
            error_log("AdminController::agbConsents: " . $e->getMessage());

            View::render('admin/agb_consents', [
                'title'       => 'AGB-Zustimmungen',
                'currentPage' => 'admin',
                'consents'    => [],
                'error'       => 'Fehler beim Laden',
            ]);
        }
    }

    /* =====================================================
       LOCKED ACCOUNTS
    ===================================================== */
    public function lockedAccounts(): void
    {
        Security::requireTeam();

        try {
            $users = Database::fetchAll("
                SELECT 
                    id,
                    username,
                    email,
                    account_locked_reason,
                    last_failed_login_at
                FROM users
                WHERE account_locked = 1
                ORDER BY last_failed_login_at DESC
            ");

            View::render('admin/locked_accounts', [
                'title'       => 'Gesperrte Accounts',
                'currentPage' => 'admin',
                'users'       => $users,
            ]);

        } catch (Throwable $e) {
            error_log("AdminController::lockedAccounts: " . $e->getMessage());

            View::render('admin/locked_accounts', [
                'title'       => 'Gesperrte Accounts',
                'currentPage' => 'admin',
                'users'       => [],
                'error'       => 'Fehler beim Laden',
            ]);
        }
    }

    /* =====================================================
       UNLOCK USER
    ===================================================== */
    public function unlockUser(): void
    {
        Security::requireTeam();
        Security::checkCsrf();

        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId > 0) {
            try {
                Database::execute("
                    UPDATE users
                    SET
                        account_locked = 0,
                        account_locked_reason = NULL,
                        failed_login_attempts = 0
                    WHERE id = ?
                ", [$userId]);
            } catch (Throwable $e) {
                error_log("AdminController::unlockUser: " . $e->getMessage());
            }
        }

        header('Location: /admin/locked-accounts');
        exit;
    }
}
