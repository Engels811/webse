<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\View;
use App\Services\Games\GameInfoResolver;
use App\Support\Playtime;
use PDO;

final class GamesController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::get();
    }

    /**
     * 🎮 Games Übersicht
     */
    public function index(): void
    {
        $stmt = $this->db->query("
            SELECT
                id,
                name,
                provider,
                genres,
                playtime_minutes,
                is_top_game,
                header_image
            FROM games
            ORDER BY playtime_minutes DESC
        ");

        $games = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($games as &$game) {
            $game['playtime_hm']  = Playtime::formatHM((int)$game['playtime_minutes']);
            $game['playtime_dec'] = Playtime::minutesToHoursDecimal(
                (int)$game['playtime_minutes'],
                1
            );
        }

        View::render('games/index', [
            'title' => 'Games',
            'games' => $games,
        ]);
    }

    /**
     * 🔍 Game Detail
     */
    public function show(string $id): void
    {
        if (!ctype_digit($id)) {
            Response::error(404);
            return;
        }
    
        $id = (int)$id;
    
        $stmt = $this->db->prepare("
            SELECT
                id,
                name,
                provider,
                steam_appid,
                genres,
                playtime_minutes,
                is_top_game,
                description,
                header_image,
                last_played,
                created_at
            FROM games
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
    
        $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if (!$game) {
            Response::error(404);
            return;
        }
    
        $game['playtime_hm']  = Playtime::formatHM((int)$game['playtime_minutes']);
        $game['playtime_dec'] = Playtime::minutesToHoursDecimal(
            (int)$game['playtime_minutes'],
            1
        );
    
        $game['external'] = GameInfoResolver::resolve($game);
    
        View::render('games/show', [
            'title' => $game['name'],
            'game'  => $game,
        ]);
    }
}
