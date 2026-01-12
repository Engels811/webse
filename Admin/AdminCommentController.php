<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Permissions;
use App\Core\Response;
use App\Models\Comment;

final class AdminCommentController
{
    /**
     * GET /admin/comments
     */
    public function index(): void
    {
        Security::requirePermission(Permissions::MEDIA_MODERATE);

        View::render('admin/comments/index', [
            'title'    => 'Kommentare – Moderation',
            'comments' => Comment::pending(),
        ]);
    }

    /**
     * POST /admin/comments/approve
     */
    public function approve(): void
    {
        Security::requirePermission(Permissions::MEDIA_MODERATE);
        Security::checkCsrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            Response::redirect('/admin/comments');
            return;
        }

        Comment::approve($id);
        Response::redirect('/admin/comments');
    }

    /**
     * POST /admin/comments/delete
     */
    public function delete(): void
    {
        Security::requirePermission(Permissions::MEDIA_MODERATE);
        Security::checkCsrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            Response::redirect('/admin/comments');
            return;
        }

        Comment::delete($id);
        Response::redirect('/admin/comments');
    }
}
