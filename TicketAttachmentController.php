<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Security;
use App\Core\Session;
use App\Models\TicketAttachment;

final class TicketAttachmentController
{
    public function download(int $id): void
    {
        Security::requireLogin();

        $file = TicketAttachment::find($id);
        if (!$file) {
            abort(404);
        }

        if ($file['user_id'] !== Session::userId() && !Session::isAdmin()) {
            abort(403);
        }

        if (!is_file($file['path'])) {
            abort(404);
        }

        $config = require __DIR__ . '/../../config/attachments.php';
        $inline = in_array($file['mime'], $config['inline_preview'], true);

        header('Content-Type: ' . $file['mime']);
        header(
            'Content-Disposition: ' .
            ($inline ? 'inline' : 'attachment') .
            '; filename="' . basename($file['filename']) . '"'
        );
        header('Content-Length: ' . filesize($file['path']));
        readfile($file['path']);
        exit;
    }
}
