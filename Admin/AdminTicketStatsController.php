<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Models\TicketStats;

final class AdminTicketStatsController
{
    public function index(): void
    {
        Security::requireAdmin();

        View::render('admin/tickets/stats', [
            'title'          => 'Ticket-Statistiken',
            'ticketStats'    => TicketStats::ticketOverview(),
            'ticketsPerDay'  => TicketStats::ticketsPerDay(),
            'attachmentStats'=> TicketStats::attachmentOverview(),
            'topTickets'     => TicketStats::topTicketsByAttachments(),
        ]);
    }
}
