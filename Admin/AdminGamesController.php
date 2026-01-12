<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Security;
use App\Core\Response;
use App\Core\View;

final class AdminGamesController
{
    /**
     * GET /admin/games/{id}/external
     */
    public function external(int $id): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.games.manage');

        // Game laden
        $game = Game::find($id);
        if (!$game) {
            Response::error(404);
            return;
        }

        View::renderWithLayout('admin/games/external', [
            'game' => $game
        ]);
    }

    /**
     * POST /admin/games/{id}/external
     */
    public function saveExternal(int $id): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.games.manage');
        Security::verifyCsrf($_POST['_csrf'] ?? '');

        $game = Game::find($id);
        if (!$game) {
            Response::error(404);
            return;
        }

        $game->external_url = trim($_POST['external_url'] ?? '');
        $game->external_api = trim($_POST['external_api'] ?? '');

        $game->save();

        Response::redirect("/admin/games/{$id}/external");
    }
}
