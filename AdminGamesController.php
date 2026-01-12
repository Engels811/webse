<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Security;
use App\Core\Database;
use App\Core\Response;

final class AdminGamesController
{
    public function index(): void
    {
        Security::requirePermission('admin.games.manage');

        try {
            // Prüfe welche Spalten existieren
            $games = Database::fetchAll("
                SELECT 
                    g.id,
                    g.name,
                    g.image,
                    g.description,
                    g.created_at
                FROM games g
                ORDER BY g.name ASC
            ");
        } catch (\Throwable $e) {
            error_log("AdminGamesController::index: " . $e->getMessage());
            $games = [];
        }

        try {
            $categories = Database::fetchAll("
                SELECT 
                    gc.id,
                    gc.name,
                    gc.icon,
                    gc.sort_order
                FROM game_categories gc
                ORDER BY gc.sort_order ASC, gc.name ASC
            ");
        } catch (\Throwable $e) {
            error_log("AdminGamesController::index categories: " . $e->getMessage());
            $categories = [];
        }

        View::render('admin/games/index', [
            'title' => 'Games verwalten',
            'games' => $games,
            'categories' => $categories
        ]);
    }

    public function create(): void
    {
        Security::requirePermission('admin.games.manage');

        View::render('admin/games/create', [
            'title' => 'Game hinzufügen'
        ]);
    }

    public function store(): void
    {
        Security::requirePermission('admin.games.manage');
        Security::checkCsrf();

        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $image = $_POST['image'] ?? '';

        if (empty($name)) {
            Response::error(400);
            return;
        }

        try {
            Database::execute("
                INSERT INTO games (name, description, image)
                VALUES (?, ?, ?)
            ", [$name, $description, $image]);
        } catch (\Throwable $e) {
            error_log("AdminGamesController::store: " . $e->getMessage());
        }

        Response::redirect('/admin/games');
    }

    public function edit(int $id): void
    {
        Security::requirePermission('admin.games.manage');

        try {
            $game = Database::fetch("
                SELECT * FROM games WHERE id = ? LIMIT 1
            ", [$id]);

            if (!$game) {
                Response::error(404);
                return;
            }

            View::render('admin/games/edit', [
                'title' => 'Game bearbeiten',
                'game' => $game
            ]);
        } catch (\Throwable $e) {
            error_log("AdminGamesController::edit: " . $e->getMessage());
            Response::error(500);
        }
    }

    public function update(): void
    {
        Security::requirePermission('admin.games.manage');
        Security::checkCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $image = $_POST['image'] ?? '';

        if ($id <= 0 || empty($name)) {
            Response::error(400);
            return;
        }

        try {
            Database::execute("
                UPDATE games 
                SET name = ?, description = ?, image = ?
                WHERE id = ?
            ", [$name, $description, $image, $id]);
        } catch (\Throwable $e) {
            error_log("AdminGamesController::update: " . $e->getMessage());
        }

        Response::redirect('/admin/games');
    }

    public function delete(): void
    {
        Security::requirePermission('admin.games.manage');
        Security::checkCsrf();

        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            try {
                Database::execute("DELETE FROM games WHERE id = ?", [$id]);
            } catch (\Throwable $e) {
                error_log("AdminGamesController::delete: " . $e->getMessage());
            }
        }

        Response::redirect('/admin/games');
    }
}
