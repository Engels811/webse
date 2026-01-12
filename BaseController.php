<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Security;
use App\Core\Response;
use App\Core\View;

/**
 * BaseController
 *
 * Gemeinsame Basis für ALLE Controller
 *
 * Regeln:
 * - KEINE Datenbank
 * - KEIN Auth-Objekt
 * - NUR Security, View, Response
 */
abstract class BaseController
{
    public function __construct()
    {
        // bewusst leer
    }

    /* =====================================================
       VIEW
    ===================================================== */

    protected function view(string $path, array $data = []): void
    {
        View::renderWithLayout($path, $data);
    }

    protected function viewRaw(string $path, array $data = []): void
    {
        View::render($path, $data);
    }

    /* =====================================================
       AUTH / ACCESS (NEU: Security-basiert)
    ===================================================== */

    protected function requireLogin(): void
    {
        Security::requireLogin();
    }

    protected function requireAdmin(): void
    {
        Security::requireAdmin();
    }

    protected function requireTeam(): void
    {
        Security::requireTeam();
    }

    protected function requirePermission(string $permission): void
    {
        Security::require($permission);
    }

    /* =====================================================
       RESPONSES
    ===================================================== */

    protected function redirect(string $url): void
    {
        Response::redirect($url);
    }

    protected function json(array $data, int $status = 200): void
    {
        Response::json($data, $status);
    }

    protected function forbidden(): void
    {
        http_response_code(403);
        View::render('errors/403', ['title' => 'Zugriff verweigert']);
        exit;
    }
}
