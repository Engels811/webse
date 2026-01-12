<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Database;
use App\Services\FaviconService;
use Throwable;

final class AdminFaviconController
{
    private function requireOwnerOrRoot(): void
    {
        if (empty($_SESSION['user'])) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }

        $id   = (int)($_SESSION['user']['id'] ?? 0);
        $role = $_SESSION['user']['role'] ?? '';

        if ($id !== 1 && $role !== 'owner') {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }

    public function index(): void
    {
        $this->requireOwnerOrRoot();

        View::render('admin/settings/favicon', [
            'version' => FaviconService::getVersion(),
            'history' => FaviconService::getHistory(),
        ]);
    }

    public function upload(): void
    {
        $this->requireOwnerOrRoot();
        Security::checkCsrf();

        $label = trim($_POST['label'] ?? '');

        if ($label === '') {
            $_SESSION['flash_error'] = 'Bitte einen Versionsnamen angeben.';
            header('Location: /admin/settings/favicon');
            exit;
        }

        try {
            FaviconService::processUpload($_FILES['favicon'], $label);
            $_SESSION['flash_success'] = 'Favicon hochgeladen.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: /admin/settings/favicon');
        exit;
    }

    public function restore(): void
    {
        $this->requireOwnerOrRoot();
        Security::checkCsrf();

        FaviconService::restore((int)($_POST['index'] ?? 0));

        $_SESSION['flash_success'] = 'Version wiederhergestellt.';
        header('Location: /admin/settings/favicon');
        exit;
    }
}
