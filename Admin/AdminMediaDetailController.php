<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Response;
use App\Core\Permissions;
use App\Services\Admin\AdminMediaEditService;
use App\Services\Support\TagService;
use RuntimeException;

final class AdminMediaDetailController
{
    /**
     * GET /admin/media/show?id=123
     */
    public function show(): void
    {
        Security::requirePermission(Permissions::MEDIA_VIEW);

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(404);
            return;
        }

        try {
            $video = (new AdminMediaEditService())->get($id);
        } catch (RuntimeException) {
            http_response_code(404);
            return;
        }

        View::render('admin/media/show', [
            'title' => 'Video bearbeiten',
            'video' => $video,
        ]);
    }

    /**
     * POST /admin/media/update
     */
    public function update(): void
    {
        Security::requirePermission(Permissions::MEDIA_MODERATE);
        Security::checkCsrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            Response::redirect('/admin/media/videos');
            return;
        }

        (new AdminMediaEditService())->update(
            id: $id,
            title: trim($_POST['title'] ?? ''),
            description: trim($_POST['description'] ?? ''),
            visibility: $_POST['visibility'] ?? 'public',
            status: $_POST['status'] ?? 'approved'
        );

        // =====================
        // TAGS (Media-konform)
        // =====================
        $tags = array_filter(
            array_map('trim', explode(',', $_POST['tags'] ?? ''))
        );

        (new TagService())->assignToMediaVideo($id, $tags);

        Response::redirect('/admin/media/videos');
    }
}
