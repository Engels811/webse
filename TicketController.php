<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Core\Security;
use App\Core\Database;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketAttachment;
use App\Models\Notification;
use App\Mail\TicketMailer;
use App\Mail\TicketNotificationMailer;

final class TicketController
{
    public function __construct()
    {
        Security::requireLogin();
    }

    /* =====================================================
       MEINE TICKETS
    ===================================================== */
    public function index(): void
    {
        View::render('tickets/index', [
            'title'       => 'Engels811 Network | Meine Support-Tickets',
            'tickets'     => Ticket::forUser(Session::userId()),
            'currentPage' => 'tickets',
        ]);
    }

    /* =====================================================
       TICKET ERSTELLEN (FORM)
    ===================================================== */
    public function create(): void
    {
        View::render('tickets/create', [
            'title'       => 'Engels811 Network | Neues Support-Ticket',
            'currentPage' => 'tickets',
        ]);
    }

    /* =====================================================
       TICKET SPEICHERN
       - Ticket wird erstellt
       - Erste Nachricht wird gespeichert
       - E-Mail wird an User gesendet (Bestätigung)
    ===================================================== */
    public function store(): void
    {
        Security::checkCsrf();

        $subject  = trim($_POST['subject'] ?? '');
        $message  = trim($_POST['message'] ?? '');
        $priority = $_POST['priority'] ?? 'normal';

        if ($subject === '' || $message === '') {
            Session::flash('error', 'Bitte alle Felder ausfüllen.');
            header('Location: /tickets/create');
            exit;
        }

        Database::beginTransaction();

        try {
            // 1) Ticket anlegen (mit Reply-Token)
            $ticketId = Ticket::create(
                Session::userId(),
                $subject,
                $priority
            );

            // 2) Erste Nachricht vom User
            $messageId = TicketMessage::add(
                $ticketId,
                Session::userId(),
                $message,
                false,
                'user',
                'user'
            );

            // 3) Aktivität
            Ticket::touch($ticketId);

            // 4) Anhang (optional)
            if (!empty($_FILES['attachment']['name'])) {
                TicketAttachment::add(
                    $ticketId,
                    $messageId,
                    $_FILES['attachment']
                );
            }

            Database::commit();

        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        /* =========================
           BENACHRICHTIGUNGEN
        ========================= */

        // 📧 Admin-Mail (Benachrichtigung über neues Ticket)
        TicketNotificationMailer::notifyAdminNewTicket(
            $ticketId,
            $subject,
            $message
        );

        // 🔔 In-App: Support-Team
        $teamUsers = Database::fetchAll(
            "SELECT id FROM users
             WHERE role IN ('support', 'moderator', 'admin', 'superadmin', 'owner')"
        );

        foreach ($teamUsers as $teamUser) {
            Notification::create(
                (int)$teamUser['id'],
                'ticket_created',
                'Neues Support-Ticket',
                'Ein neues Ticket wurde erstellt: ' . $subject,
                '/admin/tickets/' . $ticketId
            );
        }

        // 🔔 In-App: User
        Notification::create(
            Session::userId(),
            'ticket_created_self',
            'Ticket erstellt',
            'Dein Support-Ticket wurde erfolgreich erstellt.',
            '/tickets/' . $ticketId
        );

        // 📧 Bestätigungsmail an User (mit seiner eigenen Nachricht + Reply-Token)
        $ticket = Ticket::find($ticketId);
        if ($ticket) {
            $user = Database::fetch(
                "SELECT email FROM users WHERE id = ?",
                [Session::userId()]
            );
            
            if ($user) {
                TicketMailer::send(
                    $ticketId,
                    $user['email'],
                    "Vielen Dank für deine Nachricht!\n\n" . $message . "\n\nUnser Support-Team wird sich in Kürze bei dir melden."
                );
            }
        }

        Session::flash(
            'success',
            'Ticket erfolgreich erstellt! Du erhältst eine Bestätigungs-E-Mail und kannst direkt per E-Mail antworten.'
        );

        header('Location: /tickets/' . $ticketId);
        exit;
    }

    /* =====================================================
       TICKET ANZEIGEN
    ===================================================== */
    public function show(int $id): void
    {
        $ticket = Ticket::find($id);

        if (
            !$ticket ||
            ((int)$ticket['user_id'] !== 0 && (int)$ticket['user_id'] !== Session::userId())
        ) {
            abort(404);
        }

        View::render('tickets/show', [
            'title'       => 'Engels811 Network | Support-Ticket #' . $ticket['id'],
            'ticket'      => $ticket,
            'messages'    => TicketMessage::forTicket($id),
            'currentPage' => 'tickets',
        ]);
    }

    /* =====================================================
       AUF TICKET ANTWORTEN
       - Nachricht wird gespeichert
       - Status auf "open" (User wartet auf Antwort)
       - E-Mail an Admin-Team
    ===================================================== */
    public function reply(int $id): void
    {
        Security::checkCsrf();

        $ticket = Ticket::find($id);

        if (
            !$ticket ||
            ((int)$ticket['user_id'] !== 0 && (int)$ticket['user_id'] !== Session::userId())
        ) {
            abort(403);
        }

        // User kann auch auf geschlossene Tickets antworten (öffnet sie automatisch wieder)
        $ticketWasClosed = ($ticket['status'] ?? '') === 'closed';

        $message = trim($_POST['message'] ?? '');

        if ($message === '') {
            Session::flash('error', 'Nachricht darf nicht leer sein.');
            header('Location: /tickets/' . $id);
            exit;
        }

        Database::beginTransaction();

        try {
            $messageId = TicketMessage::add(
                $id,
                Session::userId(),
                $message,
                false,
                'user',
                'user'
            );

            // Ticket wieder öffnen (auch wenn es geschlossen war)
            Ticket::reopen($id);
            Ticket::touch($id);

            if (!empty($_FILES['attachment']['name'])) {
                TicketAttachment::add(
                    $id,
                    $messageId,
                    $_FILES['attachment']
                );
            }

            Database::commit();

        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        // 📧 Admin-Mail
        TicketNotificationMailer::notifyAdminUserReply(
            $id,
            $message
        );

        // 🔔 In-App: Support-Team
        $teamUsers = Database::fetchAll(
            "SELECT id FROM users
             WHERE role IN ('support', 'moderator', 'admin', 'superadmin', 'owner')"
        );

        $notificationMessage = $ticketWasClosed 
            ? 'User hat auf GESCHLOSSENES Ticket #' . $id . ' geantwortet (Ticket wurde wieder geöffnet)'
            : 'Ein User hat auf Ticket #' . $id . ' geantwortet.';

        foreach ($teamUsers as $teamUser) {
            Notification::create(
                (int)$teamUser['id'],
                $ticketWasClosed ? 'ticket_reopened_by_user' : 'ticket_reply_user',
                $ticketWasClosed ? '🔄 Ticket wieder geöffnet' : 'Neue Ticket-Antwort',
                $notificationMessage,
                '/admin/tickets/' . $id
            );
        }

        $successMessage = $ticketWasClosed 
            ? 'Antwort wurde gesendet und das Ticket wurde wieder geöffnet! Du kannst auch direkt per E-Mail antworten.'
            : 'Antwort wurde gesendet! Du kannst auch direkt per E-Mail antworten.';

        Session::flash('success', $successMessage);
        header('Location: /tickets/' . $id);
        exit;
    }
}