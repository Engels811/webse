<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

final class ForumAttachmentController
{
    /* =========================
       DELETE ATTACHMENT
    ========================= */
    public function delete(): void
    {
        /* =========================
           AUTHORIZATION
        ========================= */
        if (
            empty($_SESSION['user']) ||
            !in_array($_SESSION['user']['role'] ?? '', ['admin', 'moderator'], true)
        ) {
            http_response_code(403);

            View::renderLayout(
                'layouts/forum.layout',
                'errors/403',
                [
                    'title' => 'Zugriff verweigert'
                ]
            );

            return;
        }

        /* =========================
           CSRF
        ========================= */
        Security::checkCsrf();

        /* =========================
           INPUT
        ========================= */
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            return;
        }

        /* =========================
           DELETE
        ========================= */
        ForumAttachmentService::deleteSingle($id);

        /* =========================
           REDIRECT BACK
        ========================= */
        $redirect = $_SERVER['HTTP_REFERER'] ?? '/forum';
        header('Location: ' . $redirect);
        exit;
    }
}
