<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Response;
use App\Core\Security;
use App\Models\Banner;

final class AdminBannerController
{
    public function index(): void
    {
        Security::requireAdmin();

        View::render('admin/banners/index', [
            'title'   => 'Banner Verwaltung',
            'banners' => Banner::all(),
        ]);
    }

    public function create(): void
    {
        Security::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::checkCsrf();

            Banner::create([
                'message'   => trim((string)($_POST['message'] ?? '')),
                'type'      => (string)($_POST['type'] ?? 'info'),
                'position'  => (string)($_POST['position'] ?? 'top'),
                'start_at'  => ($_POST['start_at'] ?? '') ?: null,
                'end_at'    => ($_POST['end_at'] ?? '') ?: null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ]);

            Response::redirect('/admin/banners');
            return;
        }

        View::render('admin/banners/form', [
            'title' => 'Neues Banner erstellen',
        ]);
    }

    public function edit(): void
    {
        Security::requireAdmin();

        $id = (int)($_GET['id'] ?? 0);
        $banner = Banner::find($id);
        if (!$banner) {
            Response::error(404);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::checkCsrf();

            Banner::update($id, [
                'message'   => trim((string)($_POST['message'] ?? '')),
                'type'      => (string)($_POST['type'] ?? 'info'),
                'position'  => (string)($_POST['position'] ?? 'top'),
                'start_at'  => ($_POST['start_at'] ?? '') ?: null,
                'end_at'    => ($_POST['end_at'] ?? '') ?: null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ]);

            Response::redirect('/admin/banners');
            return;
        }

        View::render('admin/banners/form', [
            'title'  => 'Banner bearbeiten',
            'banner' => $banner,
        ]);
    }

    public function delete(): void
    {
        Security::requireAdmin();
        Security::checkCsrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            Banner::delete($id);
        }

        Response::redirect('/admin/banners');
    }
}
