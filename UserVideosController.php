<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Security;
use App\Core\Response;
use App\Services\User\UserVideoService;
use RuntimeException;

final class UserVideosController
{
    /**
     * GET /user/videos
     */
    public function index(): void
    {
        Security::requireLogin();

        View::render('user/videos/index', [
            'title'  => 'Meine Videos',
            'videos' => (new UserVideoService())
                ->listForUser((int)$_SESSION['user']['id']),
        ]);
    }

    /**
     * GET /user/videos/edit?id=123
     */
    public function edit(): void
    {
        Security::requireLogin();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(404);
            return;
        }

        try {
            $video = (new UserVideoService())->getForUser(
                $id,
                (int)$_SESSION['user']['id']
            );
        } catch (RuntimeException) {
            http_response_code(404);
            return;
        }

        View::render('user/videos/edit', [
            'title' => 'Video bearbeiten',
            'video' => $video,
        ]);
    }

    /**
     * POST /user/videos/update
     */
    public function update(): void
    {
        Security::requireLogin();
        Security::checkCsrf();

        (new UserVideoService())->updateForUser(
            id: (int)($_POST['id'] ?? 0),
            userId: (int)$_SESSION['user']['id'],
            title: trim($_POST['title'] ?? ''),
            description: trim($_POST['description'] ?? '')
        );

        Response::redirect('/user/videos');
    }

    /**
     * POST /user/videos/delete
     */
    public function delete(): void
    {
        Security::requireLogin();
        Security::checkCsrf();

        (new UserVideoService())->deleteForUser(
            id: (int)($_POST['id'] ?? 0),
            userId: (int)$_SESSION['user']['id']
        );

        Response::redirect('/user/videos');
    }
}
