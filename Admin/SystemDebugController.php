<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;

final class SystemDebugController extends BaseController
{
    private ?\PDO $db = null;

    private const SESSION_KEY = 'debug_authenticated';

    /* =========================================================
       MAIN
    ========================================================= */

    public function index(): void
    {
        $this->requireAdmin();

        if (!$this->isDebugAuthenticated()) {
            $this->showPasswordPrompt();
            return;
        }

        $this->initDb();

        // 🔥 ZENTRALE WARNING-LISTE
        $warnings = [];

        $checks = [
            'database'    => $this->checkDatabase(),
            'twitch'      => $this->checkTwitch($warnings),
            'cron'        => $this->checkCron($warnings),
            'php'         => $this->checkPhp(),
            'permissions' => $this->checkPermissions(),
            'tables'      => $this->checkTables(),
            'paths'       => $this->checkPaths(),
            'configs'     => $this->checkConfigs(),
            'services'    => $this->checkServices(),
        ];

        $overall = $this->calculateOverallStatus($checks);

        $this->view('admin/debug/index', [
            'checks'    => $checks,
            'overall'   => $overall,
            'warnings'  => $warnings, // ✅ NEU
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /* =========================================================
       AUTH
    ========================================================= */

    public function authenticate(): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/debug');
            return;
        }

        $this->initDb();

        if (!$this->db) {
            $this->showPasswordPrompt('Datenbankverbindung fehlgeschlagen');
            return;
        }

        $password = $_POST['debug_password'] ?? '';

        $stmt = $this->db->prepare(
            "SELECT password FROM users WHERE id = 1 LIMIT 1"
        );
        $stmt->execute();

        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->showPasswordPrompt('Falsches Passwort');
            return;
        }

        $_SESSION[self::SESSION_KEY] = true;
        $_SESSION[self::SESSION_KEY . '_time'] = time();

        $this->redirect('/admin/debug');
    }

    public function logout(): void
    {
        $this->requireAdmin();
        unset($_SESSION[self::SESSION_KEY], $_SESSION[self::SESSION_KEY . '_time']);
        $this->redirect('/admin/debug');
    }

    /* =========================================================
       SESSION
    ========================================================= */

    private function isDebugAuthenticated(): bool
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        if ((time() - ($_SESSION[self::SESSION_KEY . '_time'] ?? 0)) > 1800) {
            unset($_SESSION[self::SESSION_KEY]);
            return false;
        }

        return true;
    }

    private function showPasswordPrompt(?string $error = null): void
    {
        $this->view('admin/debug/password', ['error' => $error]);
    }

    /* =========================================================
       DB
    ========================================================= */

    private function initDb(): void
    {
        if ($this->db instanceof \PDO) {
            return;
        }

        try {
            $this->db = Database::get();
        } catch (\Throwable) {
            $this->db = null;
        }
    }

    /* =========================================================
       CHECKS
    ========================================================= */

    private function checkDatabase(): array
    {
        if (!$this->db) {
            return [
                'status'    => 'error',
                'connected' => false,
                'version'   => null,
                'database'  => null,
            ];
        }

        $row = $this->db
            ->query("SELECT VERSION() AS v, DATABASE() AS d")
            ->fetch(\PDO::FETCH_ASSOC);

        return [
            'status'    => 'ok',
            'connected' => true,
            'version'   => $row['v'] ?? null,
            'database'  => $row['d'] ?? null,
        ];
    }

    private function checkTwitch(array &$warnings): array
    {
        $configFile = BASE_PATH . '/app/config/twitch.php';

        $credentials = false;
        if (is_file($configFile)) {
            $cfg = require $configFile;
            $credentials = !empty($cfg['client_id']) && !empty($cfg['client_secret']);
        }

        if (!$credentials) {
            $warnings[] = [
                'source'  => 'twitch',
                'title'   => 'Twitch Credentials fehlen',
                'message' => 'client_id oder client_secret sind nicht konfiguriert.',
            ];
        }

        $tokenCache = BASE_PATH . '/app/cache/twitch_token.json';

        return [
            'status'      => $credentials ? 'ok' : 'warning',
            'credentials' => $credentials,
            'token_cache' => is_file($tokenCache),
            'vod_count'   => 0,
            'live_status' => null,
        ];
    }

    private function checkCron(array &$warnings): array
    {
        $log = BASE_PATH . '/logs/twitch_cron.log';

        if (!is_file($log)) {
            $warnings[] = [
                'source'  => 'cron',
                'title'   => 'Cron Log fehlt',
                'message' => 'Die Datei logs/twitch_cron.log existiert nicht.',
            ];

            return [
                'status'    => 'warning',
                'last_run'  => null,
                'log_lines' => [],
            ];
        }

        $lines = array_slice(file($log, FILE_IGNORE_NEW_LINES), -5);
        $lastRun = null;

        foreach (array_reverse($lines) as $line) {
            if (preg_match('/\[(.*?)\]/', $line, $m)) {
                $lastRun = strtotime($m[1]);
                break;
            }
        }

        if (!$lastRun || (time() - $lastRun) > 600) {
            $warnings[] = [
                'source'  => 'cron',
                'title'   => 'Cron nicht aktuell',
                'message' => 'Der Twitch-Cron wurde seit über 10 Minuten nicht ausgeführt.',
            ];

            return [
                'status'    => 'warning',
                'last_run'  => $lastRun ? date('Y-m-d H:i:s', $lastRun) : null,
                'log_lines' => $lines,
            ];
        }

        return [
            'status'    => 'ok',
            'last_run'  => date('Y-m-d H:i:s', $lastRun),
            'log_lines' => $lines,
        ];
    }

    private function checkPhp(): array
    {
        $extensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring'];
        $loaded = [];

        foreach ($extensions as $ext) {
            $loaded[$ext] = extension_loaded($ext);
        }

        return [
            'status'     => in_array(false, $loaded, true) ? 'warning' : 'ok',
            'version'    => PHP_VERSION,
            'extensions' => $loaded,
        ];
    }

    private function checkPermissions(): array
    {
        $dirs = [
            'cache'   => BASE_PATH . '/app/cache/',
            'logs'    => BASE_PATH . '/logs/',
            'uploads' => BASE_PATH . '/public/uploads/',
        ];

        $out = [];
        $ok = true;

        foreach ($dirs as $name => $dir) {
            $writable = is_dir($dir) && is_writable($dir);
            $out[$name] = ['writable' => $writable];
            if (!$writable) $ok = false;
        }

        $out['overall_status'] = $ok ? 'ok' : 'error';
        return $out;
    }

    private function checkTables(): array
    {
        if (!$this->db) {
            return ['status' => 'error'];
        }

        $tables = ['users','videos','notifications','stream_schedule','twitch_live_status'];
        $out = [];
        $ok = true;

        foreach ($tables as $t) {
            $exists = $this->db->query("SHOW TABLES LIKE '{$t}'")->rowCount() > 0;
            $out[$t] = $exists;
            if (!$exists) $ok = false;
        }

        $out['status'] = $ok ? 'ok' : 'error';
        return $out;
    }

    private function checkPaths(): array    { return ['status' => 'ok']; }
    private function checkConfigs(): array  { return ['status' => 'ok']; }
    private function checkServices(): array { return ['status' => 'ok']; }

    /* =========================================================
       OVERALL STATUS
    ========================================================= */

    private function calculateOverallStatus(array $checks): array
    {
        $ok = $warnings = $errors = 0;

        foreach ($checks as $check) {
            $s = $check['status'] ?? 'ok';
            if ($s === 'error') $errors++;
            elseif ($s === 'warning') $warnings++;
            else $ok++;
        }

        return [
            'ok'       => $ok,
            'warnings' => $warnings,
            'errors'   => $errors,
            'total'    => $ok + $warnings + $errors,
            'health'   => $errors ? 'critical' : ($warnings ? 'warning' : 'healthy'),
        ];
    }
}