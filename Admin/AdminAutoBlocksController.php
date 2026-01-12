<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Core\Response;
use App\Services\System\AutoBlockService;
use App\Services\System\IPListService;

final class AdminAutoBlocksController
{
    public function index(): void
    {
        Security::requirePermission(Permissions::SYSTEM_VIEW ?? Permissions::MEDIA_VIEW);

        View::render('admin/system/auto_blocks', [
            'title'  => 'Auto-Blocks',
            'blocks' => (new AutoBlockService())->recent(),
        ]);
    }

    public function unblock(): void
    {
        Security::requirePermission(Permissions::SYSTEM_VIEW ?? Permissions::MEDIA_VIEW);
        Security::checkCsrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Audit als erledigt markieren
            (new AutoBlockService())->resolve($id);
            // Optional: IP aus Blacklist entfernen (manuell)
            // (new IPListService())->removeByIp(...);
        }

        Response::redirect('/admin/system/auto-blocks');
    }
}
