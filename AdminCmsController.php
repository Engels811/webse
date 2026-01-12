<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Core\View;
use App\Core\Database;
use App\Core\Security;

final class AdminCmsController
{
    /* =========================================================
       ROLE GUARD – admin | superadmin | owner
    ========================================================= */

    private function requireAdmin(): void
    {
        if (
            empty($_SESSION['user']['role']) ||
            !in_array($_SESSION['user']['role'], ['admin', 'superadmin', 'owner'], true)
        ) {
            http_response_code(403);
            View::render('errors/403', [
                'title' => 'Zugriff verweigert'
            ]);
            exit;
        }
    }

    /* =========================================================
       INDEX – CMS SEITEN
    ========================================================= */

    public function index(): void
    {
        $this->requireAdmin();

        $pages = Database::fetchAll(
            "SELECT id, title, slug, version, updated_at
             FROM cms_pages
             ORDER BY slug ASC"
        ) ?? [];

        View::render('admin/cms/index', [
            'title' => 'CMS Seiten',
            'pages' => $pages,
        ]);
    }

    /* =========================================================
       EDIT – CMS SEITE BEARBEITEN
    ========================================================= */

    public function edit(string $slug): void
    {
        $this->requireAdmin();

        $page = Database::fetch(
            "SELECT *
             FROM cms_pages
             WHERE slug = ?
             LIMIT 1",
            [$slug]
        );

        if (!$page) {
            http_response_code(404);
            View::render('errors/404', [
                'title' => 'Seite nicht gefunden'
            ]);
            return;
        }

        View::render('admin/cms/edit', [
            'title' => 'CMS bearbeiten',
            'page'  => $page,
            'csrf'  => Security::csrf(),
        ]);
    }

    /* =========================================================
       SAVE – CMS SEITE SPEICHERN
    ========================================================= */

    public function save(): void
    {
        $this->requireAdmin();
        Security::checkCsrf();

        $slug    = trim($_POST['slug'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $version = trim($_POST['version'] ?? '');

        if ($slug === '') {
            http_response_code(400);
            exit('Ungültiger Slug');
        }

        Database::execute(
            "UPDATE cms_pages
             SET content = ?, version = ?, updated_at = NOW()
             WHERE slug = ?",
            [$content, $version, $slug]
        );

        $_SESSION['flash_success'] = 'CMS-Seite wurde gespeichert.';
        header('Location: /admin/cms');
        exit;
    }
}
