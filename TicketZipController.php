<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Security;
use App\Core\Session;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use ZipArchive;

final class TicketZipController
{
    public function export(int $ticketId): void
    {
        Security::requireLogin();

        $ticket = Ticket::find($ticketId);
        if (!$ticket) {
            abort(404);
        }

        if ($ticket['user_id'] !== Session::userId() && !Session::isAdmin()) {
            abort(403);
        }

        $attachments = TicketAttachment::forTicket($ticketId);
        if (!$attachments) {
            abort(404);
        }

        $zipPath = sys_get_temp_dir() . '/ticket_' . $ticketId . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500);
        }

        foreach ($attachments as $a) {
            if (is_file($a['path'])) {
                $zip->addFile($a['path'], $a['filename']);
            }
        }

        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="ticket_' . $ticketId . '_attachments.zip"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);

        unlink($zipPath);
        exit;
    }
}
