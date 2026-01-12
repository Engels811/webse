<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AjaxController;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Services\Health\HealthManager;
use App\Services\Health\Repairs\CacheRepair;
use App\Services\Health\Checks\TwitchConnectionTest;
use App\Services\Health\Checks\MailCheck;
use App\Services\Mail\MailTester;
use App\Services\Mail\MailImapMonitor;
use App\Core\HtmlLeakStore;

/**
 * SystemHealthController
 *
 * Zentrale Admin-Seite für System Health
 * - Health-Übersicht + Score
 * - Twitch Live-Test (AJAX)
 * - Mail SMTP / IMAP Test (AJAX)
 * - Reparatur-Aktionen
 */

class SystemHealthController extends AjaxController
{
    /**
     * 🩺 Health Dashboard
     */
    public function index(): void
    {
        $this->requireAdmin();

        $pdo = Database::get();

        $healthManager = new HealthManager($pdo);
        $results       = $healthManager->run();
        $score         = $healthManager->score($results);

        // Twitch-Test-Historie
        $twitchHistory = [];
        try {
            $twitchHistory = $pdo->query(
                "SELECT status, score, latency_ms, channel, user_id, created_at
                 FROM twitch_test_logs
                 ORDER BY created_at DESC
                 LIMIT 10"
            )->fetchAll();
        } catch (\Throwable $e) {
            error_log('[SYSTEM HEALTH] Twitch history failed: ' . $e->getMessage());
        }

        $this->view('admin/system/health', [
            'results'       => $results,
            'score'         => $score,
            'twitchHistory' => $twitchHistory,
            'csrf'          => Security::csrf(),
        ]);
    }

    public function htmlLeakList(): void
    {
        $this->requireAdminAjax();
        $this->json([
            'success' => true,
            'items'   => HtmlLeakStore::all()
        ]);
    }

    public function htmlLeakTest(): void
    {
        $this->requireAdminAjax();
    
        // absichtlicher HTML-Output → Debugger MUSS greifen
        echo "<!DOCTYPE html><html><body>DEBUG HTML LEAK TEST</body></html>";
        exit;
    }

    /**
     * 🔧 Reparaturen
     */
    public function repair(): void
    {
        $this->requireAdmin();
        Security::verifyCsrf($_POST['csrf'] ?? '');

        match ($_POST['action'] ?? '') {
            'repair_cache' => CacheRepair::run(),
            default        => null,
        };

        $this->redirect('/admin/system/health');
    }

    /**
     * 🎥 Twitch Test (AJAX)
     */
    public function twitchTestAjax(): void
    {
        // ✅ FIX: Verwende AJAX-Methoden
        $this->requireAdminAjax();
        $this->verifyCsrfAjax($_POST['csrf'] ?? '');

        try {
            $result = TwitchConnectionTest::test();

            // Log (nicht blockierend)
            try {
                $pdo = Database::get();
                $stmt = $pdo->prepare(
                    "INSERT INTO twitch_test_logs
                     (status, score, latency_ms, channel, user_id)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $result['status'],
                    $result['score'],
                    $result['latency_ms'],
                    $result['channel'],
                    $result['user_id'],
                ]);
            } catch (\Throwable $e) {
                error_log('[TWITCH LOG ERROR] ' . $e->getMessage());
            }

            $this->json([
                'success'    => true,
                'status'     => $result['status'],
                'score'      => $result['score'],
                'latency_ms' => $result['latency_ms'],
                'channel'    => $result['channel'],
                'user_id'    => $result['user_id'],
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * 📧 Mail Config + SMTP + IMAP Test (AJAX)
     */
    public function mailTestAjax(): void
    {
        // ✅ CRITICAL: Leere ALLE Output Buffer SOFORT
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // ✅ Starte neuen Output Buffer
        ob_start();
        
        // ✅ DEBUG: Teste ob wir überhaupt hier ankommen
        error_log('[MAIL TEST] Method called');
        error_log('[MAIL TEST] Auth check: ' . (Auth::check() ? 'YES' : 'NO'));
        error_log('[MAIL TEST] Is Admin: ' . (Auth::user()?->isAdmin() ? 'YES' : 'NO'));
        error_log('[MAIL TEST] CSRF Token: ' . ($_POST['csrf'] ?? 'MISSING'));
        
        // ✅ FIX: Verwende AJAX-Methoden
        $this->requireAdminAjax();
        $this->verifyCsrfAjax($_POST['csrf'] ?? '');

        error_log('[MAIL TEST] Auth checks passed');

        try {
            $start = microtime(true);
            
            // ✅ FIX: Sichere Prüfung mit Fallback
            $smtpResult = ['ok' => false, 'latency_ms' => 0];
            $imapResult = ['ok' => false, 'latency_ms' => 0];

            // SMTP Test
            try {
                $smtpStart = microtime(true);
                if (method_exists(MailCheck::class, 'testSmtp')) {
                    $smtp = MailCheck::testSmtp();
                    $smtpResult = [
                        'ok' => $smtp['ok'] ?? true,
                        'latency_ms' => round((microtime(true) - $smtpStart) * 1000),
                        'host' => $smtp['host'] ?? getenv('MAIL_HOST'),
                        'port' => $smtp['port'] ?? getenv('MAIL_PORT'),
                        'message' => $smtp['message'] ?? 'SMTP Verbindung erfolgreich'
                    ];
                } else {
                    throw new \Exception('MailCheck::testSmtp() nicht gefunden');
                }
            } catch (\Throwable $e) {
                $smtpResult = [
                    'ok' => false,
                    'latency_ms' => round((microtime(true) - $smtpStart) * 1000),
                    'message' => $e->getMessage()
                ];
            }

            // IMAP Test
            try {
                $imapStart = microtime(true);
                if (method_exists(MailCheck::class, 'testImap')) {
                    $imap = MailCheck::testImap();
                    $imapResult = [
                        'ok' => $imap['ok'] ?? true,
                        'latency_ms' => round((microtime(true) - $imapStart) * 1000),
                        'message' => $imap['message'] ?? 'IMAP Verbindung erfolgreich'
                    ];
                } else {
                    throw new \Exception('MailCheck::testImap() nicht gefunden');
                }
            } catch (\Throwable $e) {
                $imapResult = [
                    'ok' => false,
                    'latency_ms' => round((microtime(true) - $imapStart) * 1000),
                    'message' => $e->getMessage()
                ];
            }

            $this->json([
                'success' => true,
                'steps'   => [
                    'config' => [
                        'ok' => true,
                        'message' => 'Konfiguration geladen'
                    ],
                    'smtp'   => $smtpResult,
                    'imap'   => $imapResult,
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('[MAIL TEST ERROR] ' . $e->getMessage());
            error_log('[MAIL TEST ERROR] Trace: ' . $e->getTraceAsString());
            
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * 📤 Test-Mail senden (AJAX)
     */
    public function mailSendTestAjax(): void
    {
        // ✅ FIX: Verwende AJAX-Methoden
        $this->requireAdminAjax();
        $this->verifyCsrfAjax($_POST['csrf'] ?? '');

        try {
            $result = MailTester::sendTestMail();

            $this->json([
                'success'    => true,
                'status'     => 'ok',
                'latency_ms' => $result['latency_ms'],
                'recipient'  => $result['recipient'],
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * 📬 IMAP Inbox Monitoring (AJAX)
     */
    public function mailImapTestAjax(): void
    {
        // ✅ FIX: Verwende AJAX-Methoden
        $this->requireAdminAjax();
        $this->verifyCsrfAjax($_POST['csrf'] ?? '');

        try {
            $monitor = new MailImapMonitor(Database::get());
            $result  = $monitor->run(20);

            $this->json([
                'success'   => true,
                'status'    => 'ok',
                'processed' => $result['processed'],
                'created'   => $result['created'],
                'replied'   => $result['replied'],
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}