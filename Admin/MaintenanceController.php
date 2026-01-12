<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Security;
use App\Core\View;
use App\Core\Response;

/**
 * ENGELS811 NETWORK – Wartungsmodus Controller (FIXED - NO CSRF)
 */
final class MaintenanceController
{
    /**
     * GET /admin/maintenance
     */
    public function index(): void
    {
        Security::requireAdmin();
        $this->ensureTablesExist();

        $maintenance = Database::fetch("SELECT * FROM maintenance_mode LIMIT 1");

        if (!$maintenance) {
            Database::execute("
                INSERT INTO maintenance_mode (is_active, title, message, allow_admins, show_countdown, created_at) 
                VALUES (0, 'Wartungsarbeiten', 'Das System befindet sich in Wartung.', 1, 1, NOW())
            ");
            $maintenance = Database::fetch("SELECT * FROM maintenance_mode LIMIT 1");
        }

        $history = Database::fetchAll("
            SELECT h.*, u1.username as started_by_name, u2.username as ended_by_name
            FROM maintenance_history h
            LEFT JOIN users u1 ON h.started_by = u1.id
            LEFT JOIN users u2 ON h.ended_by = u2.id
            ORDER BY h.started_at DESC LIMIT 20
        ") ?? [];

        $scheduled = Database::fetchAll("
            SELECT s.*, u.username as created_by_name
            FROM maintenance_schedule s
            LEFT JOIN users u ON s.created_by = u.id
            WHERE s.executed = 0 AND s.start_time > NOW()
            ORDER BY s.start_time ASC
        ") ?? [];

        View::render('admin/maintenance/index', [
            'title' => 'Wartungsmodus',
            'maintenance' => $maintenance,
            'history' => $history,
            'scheduled' => $scheduled,
            'isActive' => (int)($maintenance['is_active'] ?? 0) === 1
        ]);
    }

    /**
     * POST /admin/maintenance/activate
     * AJAX - Wartungsmodus aktivieren
     */
    public function activate(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        
        try {
            // Admin Check
            if (empty($_SESSION['user']) || ($_SESSION['user']['role_level'] ?? 0) < 100) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
                exit;
            }

            $maintenance = Database::fetch("SELECT * FROM maintenance_mode LIMIT 1");

            if (!$maintenance) {
                echo json_encode(['success' => false, 'message' => 'Keine Konfiguration gefunden']);
                exit;
            }

            if ((int)$maintenance['is_active'] === 1) {
                echo json_encode(['success' => false, 'message' => 'Wartungsmodus bereits aktiv']);
                exit;
            }

            Database::execute(
                "UPDATE maintenance_mode SET is_active = 1, started_at = NOW() WHERE id = ?",
                [$maintenance['id']]
            );

            Database::execute(
                "INSERT INTO maintenance_history (title, message, started_at, started_by) VALUES (?, ?, NOW(), ?)",
                [
                    $maintenance['title'] ?? 'Wartung',
                    $maintenance['message'] ?? '',
                    $_SESSION['user']['id'] ?? 0
                ]
            );

            // Notifications
            if ((int)($maintenance['notify_users'] ?? 0) === 1) {
                $this->sendNotificationsToUsers($maintenance);
            }

            echo json_encode([
                'success' => true, 
                'message' => 'Wartungsmodus wurde aktiviert'
            ]);
            exit;

        } catch (\Exception $e) {
            error_log('Maintenance Activate Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'message' => 'Serverfehler: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * POST /admin/maintenance/deactivate
     * AJAX - Wartungsmodus deaktivieren
     */
    public function deactivate(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        
        try {
            if (empty($_SESSION['user']) || ($_SESSION['user']['role_level'] ?? 0) < 100) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
                exit;
            }

            $maintenance = Database::fetch("SELECT * FROM maintenance_mode WHERE is_active = 1 LIMIT 1");

            if (!$maintenance) {
                echo json_encode(['success' => false, 'message' => 'Kein aktiver Wartungsmodus']);
                exit;
            }

            Database::execute(
                "UPDATE maintenance_mode SET is_active = 0, ended_at = NOW() WHERE id = ?",
                [$maintenance['id']]
            );

            if (!empty($maintenance['started_at'])) {
                $duration = (int) round((time() - strtotime($maintenance['started_at'])) / 60);
                Database::execute(
                    "UPDATE maintenance_history SET ended_at = NOW(), duration_minutes = ?, ended_by = ? WHERE ended_at IS NULL ORDER BY started_at DESC LIMIT 1",
                    [$duration, $_SESSION['user']['id'] ?? 0]
                );
            }

            echo json_encode([
                'success' => true, 
                'message' => 'Wartungsmodus wurde beendet'
            ]);
            exit;

        } catch (\Exception $e) {
            error_log('Maintenance Deactivate Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'message' => 'Serverfehler: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * POST /admin/maintenance/notify
     * AJAX - Manuelle Benachrichtigung
     */
    public function sendNotification(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        
        try {
            if (empty($_SESSION['user']) || ($_SESSION['user']['role_level'] ?? 0) < 100) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
                exit;
            }

            $maintenance = Database::fetch("SELECT * FROM maintenance_mode LIMIT 1");

            if (!$maintenance) {
                echo json_encode(['success' => false, 'message' => 'Keine Konfiguration']);
                exit;
            }

            $count = $this->sendNotificationsToUsers($maintenance);

            echo json_encode([
                'success' => true, 
                'message' => "{$count} Benutzer wurden benachrichtigt"
            ]);
            exit;

        } catch (\Exception $e) {
            error_log('Notification Send Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'message' => 'Serverfehler: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * POST /admin/maintenance/save
     * Formular - Einstellungen speichern
     */
    public function save(): void
    {
        Security::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::redirect('/admin/maintenance');
        }

        $title = trim($_POST['title'] ?? 'Wartungsarbeiten');
        $message = trim($_POST['message'] ?? '');
        $estimatedEnd = $_POST['estimated_end'] ?? null;
        $notifyUsers = isset($_POST['notify_users']) ? 1 : 0;
        $allowAdmins = isset($_POST['allow_admins']) ? 1 : 0;
        $showCountdown = isset($_POST['show_countdown']) ? 1 : 0;

        if ($estimatedEnd === '') {
            $estimatedEnd = null;
        }

        $existing = Database::fetch("SELECT id FROM maintenance_mode LIMIT 1");

        if ($existing) {
            Database::execute(
                "UPDATE maintenance_mode SET title = ?, message = ?, estimated_end = ?, notify_users = ?, allow_admins = ?, show_countdown = ?, updated_at = NOW() WHERE id = ?",
                [$title, $message, $estimatedEnd, $notifyUsers, $allowAdmins, $showCountdown, $existing['id']]
            );
        } else {
            Database::execute(
                "INSERT INTO maintenance_mode (is_active, title, message, estimated_end, notify_users, allow_admins, show_countdown, created_at) VALUES (0, ?, ?, ?, ?, ?, ?, NOW())",
                [$title, $message, $estimatedEnd, $notifyUsers, $allowAdmins, $showCountdown]
            );
        }

        $_SESSION['flash_success'] = 'Einstellungen gespeichert';
        Response::redirect('/admin/maintenance');
    }

    /**
     * POST /admin/maintenance/schedule
     * Formular - Wartung planen
     */
    public function schedule(): void
    {
        Security::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::redirect('/admin/maintenance');
        }

        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $startTime = $_POST['start_time'] ?? null;
        $endTime = $_POST['end_time'] ?? null;
        $notifyBefore = (int)($_POST['notify_before'] ?? 60);

        if ($title === '' || !$startTime || !$endTime) {
            $_SESSION['flash_error'] = 'Titel, Start und Ende sind erforderlich';
            Response::redirect('/admin/maintenance');
        }

        Database::execute(
            "INSERT INTO maintenance_schedule (title, message, start_time, end_time, notify_before_minutes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$title, $message, $startTime, $endTime, $notifyBefore, $_SESSION['user']['id'] ?? 0]
        );

        $_SESSION['flash_success'] = 'Wartung geplant';
        Response::redirect('/admin/maintenance');
    }

    /**
     * Benachrichtigungen an User senden
     */
    private function sendNotificationsToUsers(array $maintenance): int
    {
        $users = Database::fetchAll("SELECT id FROM users WHERE email IS NOT NULL") ?? [];
        $count = 0;

        foreach ($users as $user) {
            try {
                Database::execute(
                    "INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, 'system', NOW())",
                    [
                        $user['id'],
                        '🛠️ ' . ($maintenance['title'] ?? 'Wartung'),
                        $maintenance['message'] ?? ''
                    ]
                );
                $count++;
            } catch (\Exception $e) {
                continue;
            }
        }

        return $count;
    }

    /**
     * Tabellen erstellen falls nicht vorhanden
     */
    private function ensureTablesExist(): void
    {
        $db = Database::getPdo();

        // maintenance_mode
        $stmt = $db->query("SHOW TABLES LIKE 'maintenance_mode'");
        if (!$stmt->fetch()) {
            $db->exec("
                CREATE TABLE `maintenance_mode` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `is_active` tinyint(1) DEFAULT 0,
                  `title` varchar(255) NOT NULL DEFAULT 'Wartungsarbeiten',
                  `message` text DEFAULT NULL,
                  `estimated_end` datetime DEFAULT NULL,
                  `notify_users` tinyint(1) DEFAULT 0,
                  `allow_admins` tinyint(1) DEFAULT 1,
                  `show_countdown` tinyint(1) DEFAULT 1,
                  `started_at` datetime DEFAULT NULL,
                  `ended_at` datetime DEFAULT NULL,
                  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // maintenance_history
        $stmt = $db->query("SHOW TABLES LIKE 'maintenance_history'");
        if (!$stmt->fetch()) {
            $db->exec("
                CREATE TABLE `maintenance_history` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `title` varchar(255) NOT NULL,
                  `message` text DEFAULT NULL,
                  `started_at` datetime NOT NULL,
                  `ended_at` datetime DEFAULT NULL,
                  `duration_minutes` int(11) DEFAULT NULL,
                  `started_by` int(11) DEFAULT NULL,
                  `ended_by` int(11) DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // maintenance_schedule
        $stmt = $db->query("SHOW TABLES LIKE 'maintenance_schedule'");
        if (!$stmt->fetch()) {
            $db->exec("
                CREATE TABLE `maintenance_schedule` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `title` varchar(255) NOT NULL,
                  `message` text DEFAULT NULL,
                  `start_time` datetime NOT NULL,
                  `end_time` datetime NOT NULL,
                  `notify_before_minutes` int(11) DEFAULT 60,
                  `notified` tinyint(1) DEFAULT 0,
                  `executed` tinyint(1) DEFAULT 0,
                  `created_by` int(11) DEFAULT NULL,
                  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }
}