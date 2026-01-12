<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Security;
use App\Services\Domain\VideoSubmissionService;
use RuntimeException;

final class VideoSubmissionController
{
    /**
     * POST /videos/upload
     */
    public function upload(): void
    {
        Security::requireLogin();
        Security::checkCsrf();

        if (empty($_FILES['video'])) {
            throw new RuntimeException('Keine Videodatei übermittelt.');
        }

        (new VideoSubmissionService())->submitUpload(
            userId: (int)$_SESSION['user']['id'],
            title: (string)($_POST['title'] ?? ''),
            description: (string)($_POST['description'] ?? ''),
            file: $_FILES['video']
        );

        // Optional: Erfolgshinweis über Session
        Response::redirect('/videos?submitted=1');
    }

    /**
     * POST /videos/external
     */
    public function external(): void
    {
        Security::requireLogin();
        Security::checkCsrf();

        if (empty($_POST['url'])) {
            throw new RuntimeException('Keine URL angegeben.');
        }

        (new VideoSubmissionService())->submitExternal(
            userId: (int)$_SESSION['user']['id'],
            platform: (string)($_POST['platform'] ?? 'other'),
            url: (string)$_POST['url'],
            title: (string)($_POST['title'] ?? ''),
            description: (string)($_POST['description'] ?? '')
        );

        Response::redirect('/videos?submitted=1');
    }
}
