<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\Security;
use PDO;

final class TwitchAdminController extends BaseController
{
    private ?PDO $db = null;
    private mixed $twitch = null;

    /* =====================================================
       ENTRY
    ===================================================== */
    public function index(): void
    {
        $this->requireAdmin();

        $this->db     = $this->getDb();
        $this->twitch = $this->getTwitchService();

        $message     = '';
        $messageType = 'success';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $message = $this->handlePost();
            } catch (\Throwable $e) {
                $message     = 'Fehler: ' . $e->getMessage();
                $messageType = 'danger';
                error_log('[TwitchAdminController] POST Error: ' . $e->getMessage());
            }
        }

        $this->view('admin/twitch/index', [
            'db'              => $this->db,   // ✅ FIX
            'message'         => $message,
            'messageType'     => $messageType,
            'liveStatus'      => $this->getLiveStatus(),
            'schedule'        => $this->getTwitchSchedule(),
            'vods'            => $this->getVods(),
            'localSchedule'   => $this->getLocalSchedule(),
            'stats'           => $this->getStats(),
            'notifyStats'     => $this->getNotifyStats(),
            'twitchAvailable' => $this->twitch !== null,
        ]);

    }

    /* =====================================================
       INIT (LAZY)
    ===================================================== */
    private function getDb(): ?PDO
    {
        try {
            return Database::getPdo();
        } catch (\Throwable) {
            return null;
        }
    }

    private function getTwitchService(): mixed
    {
        if (!$this->db) {
            return null;
        }

        if (!defined('BASE_PATH')) {
            throw new \RuntimeException('BASE_PATH ist nicht definiert');
        }

        $helper = BASE_PATH . '/app/helpers/twitch_helper.php';
        if (!is_file($helper)) {
            throw new \RuntimeException('Twitch Helper nicht gefunden: ' . $helper);
        }

        require_once $helper;

        if (!function_exists('getTwitchService')) {
            throw new \RuntimeException('getTwitchService() nicht verfügbar');
        }

        try {
            return getTwitchService($this->db);
        } catch (\Throwable $e) {
            error_log('[TwitchAdminController] Twitch Init Error: ' . $e->getMessage());
            return null;
        }
    }


    /* =====================================================
       POST DISPATCH
    ===================================================== */
    private function handlePost(): string
    {
        return match ($_POST['action'] ?? '') {
            'import_vods'        => $this->importVods(),
            'save_schedule'     => $this->saveSchedule(),
            'delete_schedule'   => $this->deleteSchedule(),
            'delete_vod'        => $this->deleteVod(),
            'test_notification' => $this->sendTestNotification(),
            'force_check'       => $this->forceCheck(),
            default             => '',
        };
    }

    private function forceCheck(): string
    {
        if (!$this->twitch) {
            return 'Twitch Service nicht verfügbar';
        }

        $this->twitch->checkLiveStatus();
        return 'Live-Status aktualisiert';
    }

    /* =====================================================
       READ
    ===================================================== */
    private function getLiveStatus(): bool
    {
        try {
            return $this->twitch ? (bool) $this->twitch->isLive() : false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function getTwitchSchedule(): array
    {
        try {
            return $this->twitch ? (array) $this->twitch->getSchedule(20) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function getVods(): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $stmt = $this->db->query("
                SELECT *
                FROM videos
                WHERE is_stream = 1
                ORDER BY published_at DESC
                LIMIT 50
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[TwitchAdminController] VOD Load Error: ' . $e->getMessage());
            return [];
        }
    }

    private function getLocalSchedule(): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $stmt = $this->db->query("
                SELECT *
                FROM stream_schedule
                WHERE start_time >= NOW()
                ORDER BY start_time ASC
                LIMIT 20
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[TwitchAdminController] Schedule Load Error: ' . $e->getMessage());
            return [];
        }
    }

    private function getStats(): array
    {
        if (!$this->db) {
            return ['total_vods' => 0, 'total_views' => 0, 'avg_duration' => 0];
        }

        try {
            $stmt = $this->db->query("
                SELECT
                    COUNT(*) AS total_vods,
                    COALESCE(SUM(view_count), 0) AS total_views,
                    COALESCE(AVG(duration_seconds), 0) AS avg_duration
                FROM videos
                WHERE is_stream = 1
            ");
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'total_vods'   => (int) ($row['total_vods'] ?? 0),
                'total_views'  => (int) ($row['total_views'] ?? 0),
                'avg_duration' => (int) ($row['avg_duration'] ?? 0),
            ];
        } catch (\Throwable $e) {
            error_log('[TwitchAdminController] Stats Error: ' . $e->getMessage());
            return ['total_vods' => 0, 'total_views' => 0, 'avg_duration' => 0];
        }
    }

    private function getNotifyStats(): int
    {
        if (!$this->db) {
            return 0;
        }

        try {
            return (int) $this->db
                ->query("SELECT COUNT(*) FROM users WHERE notify_streams = 1")
                ->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[TwitchAdminController] Notify Stats Error: ' . $e->getMessage());
            return 0;
        }
    }

    /* =====================================================
       WRITE
    ===================================================== */
    private function importVods(): string
    {
        Security::verifyCsrf($_POST['csrf'] ?? '');

        if (!$this->twitch) {
            throw new \RuntimeException('Twitch Service nicht verfügbar');
        }

        $limit    = max(1, min(100, (int) ($_POST['limit'] ?? 20)));
        $imported = (int) $this->twitch->importVODs($limit);

        return $imported . ' VODs erfolgreich importiert';
    }

    private function saveSchedule(): string
    {
        Security::verifyCsrf($_POST['csrf'] ?? '');

        if (!$this->db) {
            throw new \RuntimeException('DB nicht verfügbar');
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $start = (string) ($_POST['start_time'] ?? '');

        if ($title === '' || $start === '') {
            throw new \RuntimeException('Titel und Startzeit sind erforderlich');
        }

        $stmt = $this->db->prepare("
            INSERT INTO stream_schedule (title, category, start_time, end_time, is_recurring)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $title,
            $_POST['category'] ?? '',
            $start,
            $_POST['end_time'] ?: null,
            !empty($_POST['is_recurring']) ? 1 : 0,
        ]);

        return 'Stream-Termin gespeichert';
    }

    private function deleteSchedule(): string
    {
        Security::verifyCsrf($_POST['csrf'] ?? '');

        if (!$this->db) {
            throw new \RuntimeException('DB nicht verfügbar');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('Ungültige ID');
        }

        $stmt = $this->db->prepare("DELETE FROM stream_schedule WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('Termin nicht gefunden');
        }

        return 'Termin gelöscht';
    }

    private function deleteVod(): string
    {
        Security::verifyCsrf($_POST['csrf'] ?? '');

        if (!$this->db) {
            throw new \RuntimeException('DB nicht verfügbar');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('Ungültige ID');
        }

        $stmt = $this->db->prepare("
            DELETE FROM videos
            WHERE id = ? AND is_stream = 1
        ");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('VOD nicht gefunden');
        }

        return 'VOD gelöscht';
    }

    private function sendTestNotification(): string
    {
        Security::verifyCsrf($_POST['csrf'] ?? '');

        if (!$this->db) {
            throw new \RuntimeException('DB nicht verfügbar');
        }

        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, title, message, type, created_at)
            SELECT id,
                   '🔴 Test: Stream ist LIVE!',
                   'Dies ist eine Test-Benachrichtigung',
                   'stream_live',
                   NOW()
            FROM users
            WHERE notify_streams = 1
        ");
        $stmt->execute();

        $count = $stmt->rowCount();

        return $count > 0
            ? "Test-Benachrichtigung an {$count} User gesendet"
            : 'Keine User mit aktivierten Benachrichtigungen gefunden';
    }
}
