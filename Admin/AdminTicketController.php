<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Session;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Mail\TicketMailer;
use App\Mail\TicketNotificationMailer;

final class AdminTicketController
{
    public function __construct()
    {
        Security::requireAdmin();
    }

    /* =====================================================
       TICKET ÜBERSICHT (ADMIN)
       - aktive + archivierte Tickets
    ===================================================== */
    public function index(): void
    {
        View::render('admin/tickets/index', [
            'title'           => 'Admin | Support-Tickets',
            'activeTickets'   => Ticket::active(),
            'archivedTickets' => Ticket::archived(),
        ]);
    }

    /* =====================================================
       TICKET ANZEIGEN (DETAIL)
       - inkl. Konversation
    ===================================================== */
    public function show(int $id): void
    {
        $ticket = Ticket::find($id);

        if (!$ticket) {
            abort(404);
        }

        View::render('admin/tickets/show', [
            'title'     => 'Admin | Ticket #' . $ticket['id'],
            'bodyClass' => 'admin-ticket-detail-view', // ⭐ WICHTIG
            'ticket'    => $ticket,
            'messages'  => TicketMessage::forTicket($id),
        ]);
    }

    /* =====================================================
       ADMIN ANTWORT
       - Status-Automatik im Model
       - kompatibel mit Live-Update
    ===================================================== */
    public function reply(int $id): void
    {
        Security::checkCsrf();

        $ticket = Ticket::find($id);
        if (!$ticket) {
            abort(404);
        }

        if ($ticket['status'] === 'closed') {
            Session::flash('error', 'Ticket ist geschlossen.');
            header('Location: /admin/tickets/' . $id);
            exit;
        }

        $message = trim($_POST['message'] ?? '');
        if ($message === '') {
            Session::flash('error', 'Antwort darf nicht leer sein.');
            header('Location: /admin/tickets/' . $id);
            exit;
        }

        // ✅ Nachricht speichern
        // 👉 Status wird AUTOMATISCH auf "answered" gesetzt
        TicketMessage::add(
            $id,
            'admin',
            Session::userId(),
            $message
        );

        // 📧 Mail an User (Inhalt = Originalnachricht)
        TicketMailer::send(
            $id,
            $ticket['user_email'],
            $message
        );

        // 🔔 Zusatz-Benachrichtigung
        TicketNotificationMailer::notifyUserAdminReply(
            $ticket['user_email'],
            $id,
            $ticket['subject']
        );

        Session::flash(
            'success',
            'Antwort wurde gesendet. Status automatisch aktualisiert.'
        );

        header('Location: /admin/tickets/' . $id);
        exit;
    }

    /* =====================================================
       TICKET SCHLIESSEN
    ===================================================== */
    public function close(int $id): void
    {
        Security::checkCsrf();

        Ticket::close($id);

        Session::flash('success', 'Ticket wurde geschlossen.');
        header('Location: /admin/tickets/' . $id);
        exit;
    }
}
