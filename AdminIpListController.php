<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Core\Response;
use App\Services\System\IPListService;

final class AdminIpListController
{
    public function index(): void
    {
        Security::requirePermission(Permissions::SYSTEM_VIEW ?? Permissions::MEDIA_VIEW);

        $svc = new IPListService();

        View::render('admin/system/ip_lists', [
            'title'     => 'IP-Listen',
            'whitelist' => $svc->all('whitelist'),
            'blacklist' => $svc->all('blacklist'),
        ]);
    }

    public function add(): void
    {
        Security::requirePermission(Permissions::SYSTEM_VIEW ?? Permissions::MEDIA_VIEW);
        Security::checkCsrf();

        (new IPListService())->add(
            trim($_POST['ip'] ?? ''),
            $_POST['list'] ?? 'blacklist',
            trim($_POST['reason'] ?? null)
        );

        Response::redirect('/admin/system/ip-lists');
    }

    public function delete(): void
    {
        Security::requirePermission(Permissions::SYSTEM_VIEW ?? Permissions::MEDIA_VIEW);
        Security::checkCsrf();

        (new IPListService())->remove((int)($_POST['id'] ?? 0));

        Response::redirect('/admin/system/ip-lists');
    }
}
