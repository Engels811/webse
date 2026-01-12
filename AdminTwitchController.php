<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Security;
use App\Core\Database;
use App\Services\TwitchService;
use Exception;

final class AdminTwitchController
{
    public function index(): void
    {
        Security::requireAdmin();

        $logs = Database::fetchAll(
            "SELECT * FROM twitch_vods ORDER BY created_at DESC LIMIT 50"
        ) ?? [];

        View::render('admin/twitch/index', [
            'title' => 'Twitch VOD Logs',
            'logs'  => $logs
        ]);
    }

    public function sync(): void
    {
        Security::requireAdmin();

        if (!class_exists(TwitchService::class)) {
            $_SESSION['flash_error'] = 'TwitchService nicht verfügbar.';
            header('Location: /admin/twitch');
            exit;
        }

        try {
            TwitchService::syncVods();
            $_SESSION['flash_success'] = 'VODs wurden synchronisiert.';
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Fehler: ' . $e->getMessage();
        }

        header('Location: /admin/twitch');
        exit;
    }

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
            "DELETE FROM twitch_vods WHERE id = ?",
            [$id]
        );

        $_SESSION['flash_success'] = 'VOD wurde gelöscht.';
        header('Location: /admin/twitch');
        exit;
    }
}
