<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;

/**
 * AjaxController
 *
 * Basis für ALLE reinen AJAX-Controller
 * - KEINE Redirects
 * - KEINE Views
 * - IMMER JSON
 */
abstract class AjaxController extends BaseController
{
    /**
     * 🔒 AJAX-Admin-Check (JSON statt Redirect)
     */
    protected function requireAdminAjax(): void
    {
        error_log('[AJAX] requireAdminAjax called');
        
        if (!Auth::check()) {
            error_log('[AJAX] Auth failed - not logged in');
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Nicht eingeloggt'
            ]);
            exit;
        }

        if (!Auth::user() || !Auth::user()->isAdmin()) {
            error_log('[AJAX] Auth failed - not admin');
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Kein Zugriff'
            ]);
            exit;
        }
        
        error_log('[AJAX] Auth passed');
    }

    /**
     * 🔐 AJAX-CSRF-Check (JSON)
     */
    protected function verifyCsrfAjax(string $token): void
    {
        error_log('[AJAX] verifyCsrfAjax called with token: ' . substr($token, 0, 10) . '...');
        
        try {
            \App\Core\Security::verifyCsrf($token);
            error_log('[AJAX] CSRF valid');
        } catch (\Throwable $e) {
            error_log('[AJAX] CSRF failed: ' . $e->getMessage());
            http_response_code(419);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'CSRF ungültig: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * ✅ JSON Response Helper
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        // Leere Output Buffer falls was drin ist
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}