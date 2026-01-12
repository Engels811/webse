<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Security;
use App\Core\Database;

final class AdminPlaylistController
{
    /* =========================
       LISTE
    ========================= */
    public function index(): void
    {
        Security::requireAdmin();

        $playlists = Database::fetchAll(
            'SELECT *
             FROM playlists
             ORDER BY sort_order ASC, created_at DESC'
        );

        View::render('admin/playlists/index', [
            'title'     => 'Playlists',
            'playlists' => $playlists ?? []
        ]);
    }

    /* =========================
       CREATE FORM
    ========================= */
    public function create(): void
    {
        Security::requireAdmin();

        View::render('admin/playlists/create', [
            'title' => 'Playlist erstellen'
        ]);
    }

    /* =========================
       STORE
    ========================= */
    public function store(): void
    {
        Security::requireAdmin();
        Security::checkCsrf();

        $title = trim($_POST['title'] ?? '');
        $type  = trim($_POST['type'] ?? '');

        if ($title === '' || !in_array($type, ['spotify','youtube','youtube_music'], true)) {
            $_SESSION['flash_error'] = 'Titel oder Plattform ungültig.';
            header('Location: /admin/playlists/create');
            exit;
        }

        Database::execute(
            'INSERT INTO playlists (title, type, is_active, sort_order)
             VALUES (?, ?, 1, 0)',
            [$title, $type]
        );

        $_SESSION['flash_success'] = 'Playlist wurde erstellt.';
        header('Location: /admin/playlists');
        exit;
    }

    /* =========================
       TOGGLE ACTIVE
    ========================= */
    public function toggle(): void
    {
        Security::requireAdmin();
        Security::checkCsrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            exit;
        }

        Database::execute(
            'UPDATE playlists SET is_active = 1 - is_active WHERE id = ?',
            [$id]
        );

        header('Location: /admin/playlists');
        exit;
    }

    /* =========================
       DELETE
    ========================= */
    public function delete(): void
    {
        Security::requireAdmin();
        Security::checkCsrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            exit;
        }

        Database::execute(
            'DELETE FROM playlists WHERE id = ?',
            [$id]
        );

        $_SESSION['flash_success'] = 'Playlist wurde gelöscht.';
        header('Location: /admin/playlists');
        exit;
    }
}
