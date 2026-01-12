<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Security;
use RuntimeException;

final class SystemController extends BaseController
{
    /* =====================================================
       DASHBOARD
    ===================================================== */
    public function index(): void
    {
        $this->requireAdmin();

        if (!defined('BASE_PATH')) {
            throw new RuntimeException('BASE_PATH ist nicht definiert');
        }

        // Systempfade (relativ gespeichert)
        $cacheRel = system_setting('cache_dir', 'cache');
        $logRel   = system_setting('log_dir', 'logs');

        $cacheDir = BASE_PATH . '/' . ltrim($cacheRel, '/');
        $logDir   = BASE_PATH . '/' . ltrim($logRel, '/');

        $this->view('admin/system/index', [
            'cacheValue'    => $cacheRel,
            'logValue'      => $logRel,
            'cacheDir'      => $cacheDir,
            'logDir'        => $logDir,
            'cacheExists'   => is_dir($cacheDir),
            'logExists'     => is_dir($logDir),
            'cacheWritable' => is_writable($cacheDir),
            'logWritable'   => is_writable($logDir),
        ]);
    }

    /* =====================================================
       SAVE PATHS
    ===================================================== */
    public function save(): void
    {
        $this->requireAdmin();
        Security::verifyCsrf($_POST['csrf'] ?? '');

        $cache = trim($_POST['cache_dir'] ?? '');
        $log   = trim($_POST['log_dir'] ?? '');

        if ($cache === '' || $log === '' || str_contains($cache, '..') || str_contains($log, '..')) {
            $this->redirect('/admin/system');
        }

        set_system_setting('cache_dir', $cache);
        set_system_setting('log_dir', $log);

        $cachePath = BASE_PATH . '/' . ltrim($cache, '/');
        $logPath   = BASE_PATH . '/' . ltrim($log, '/');

        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }

        $this->redirect('/admin/system');
    }

    /* =====================================================
       CLEAR CACHE
    ===================================================== */
    public function clearCache(): void
    {
        $this->requireAdmin();
        Security::verifyCsrf($_POST['csrf'] ?? '');

        $cacheRel = system_setting('cache_dir', 'cache');
        $cacheDir = BASE_PATH . '/' . ltrim($cacheRel, '/');

        if (is_dir($cacheDir)) {
            foreach (glob($cacheDir . '/*') as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        $this->redirect('/admin/system');
    }

    /* =====================================================
       AJAX STATUS
    ===================================================== */
    public function status(): void
    {
        $this->requireAdmin();

        header('Content-Type: application/json');

        $cacheRel = system_setting('cache_dir', 'cache');
        $logRel   = system_setting('log_dir', 'logs');

        $cacheDir = BASE_PATH . '/' . ltrim($cacheRel, '/');
        $logDir   = BASE_PATH . '/' . ltrim($logRel, '/');

        echo json_encode([
            'cache' => [
                'exists'   => is_dir($cacheDir),
                'writable' => is_writable($cacheDir),
            ],
            'logs' => [
                'exists'   => is_dir($logDir),
                'writable' => is_writable($logDir),
            ],
        ]);
        exit;
    }

    /* =====================================================
       LOG VIEWER
    ===================================================== */
    public function logs(): void
    {
        $this->requireAdmin();

        $logRel = system_setting('log_dir', 'logs');
        $logDir = BASE_PATH . '/' . ltrim($logRel, '/');

        $files = [];
        if (is_dir($logDir)) {
            $files = array_reverse(glob($logDir . '/*.log'));
        }

        $this->view('admin/system/logs', [
            'logFiles' => $files,
        ]);
    }
}
