<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Core\Security;
use App\Models\Notification;

final class NotificationController
{
    /* ================= PAGE ================= */

    public function index(): void
    {
        Security::requireLogin();

        // Hier wird das activeType gesetzt
        $activeType = 'default_value';  // Setze hier den Wert, den du benötigst
        // Wenn das activeType dynamisch sein soll, kannst du es nach Bedarf anpassen, z.B.:
        // $activeType = $this->determineActiveType();

        View::render('notifications/index', [
            'title'         => 'Benachrichtigungen',
            'notifications' => Notification::allForUser(Session::userId()),
            'currentPage'   => 'notifications',
            'activeType'    => $activeType,  // activeType wird hier übergeben
        ]);
    }

    /* ================= AJAX: POLL ================= */

    public function poll(): void
    {
        Security::requireLogin();

        $notifications = Notification::unreadForUser(Session::userId());

        if (!$notifications) {
            echo '<div class="notification-empty">Keine neuen Benachrichtigungen</div>';
            return;
        }

        foreach ($notifications as $n) {
            echo '
                <div class="notification-card" data-id="'.(int)$n['id'].'">
                    <a href="'.htmlspecialchars($n['link'], ENT_QUOTES, 'UTF-8').'" class="notification-link">
                        <div class="notification-card-title">'
                            .htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8').'
                        </div>

                        <!-- 🔔 WICHTIG: Klasse für Toast -->
                        <div class="notification-message">'
                            .htmlspecialchars($n['message'], ENT_QUOTES, 'UTF-8').'
                        </div>

                        <div class="notification-card-time">'
                            .date('d.m.Y H:i', strtotime($n['created_at'])).'
                        </div>
                    </a>

                    <button
                        type="button"
                        class="notification-delete"
                        data-id="'.(int)$n['id'].'"
                        aria-label="Benachrichtigung löschen"
                    >×</button>
                </div>
            ';
        }
    }

    /* ================= AJAX: DELETE ONE ================= */

    public function ajaxDelete(): void
    {
        Security::requireLogin();
        Security::checkCsrf();

        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false]);
            return;
        }

        Notification::delete($id, Session::userId());

        echo json_encode(['success' => true]);
    }

    /* ================= AJAX: DELETE ALL ================= */

    public function ajaxDeleteAll(): void
    {
        Security::requireLogin();
        Security::checkCsrf();

        header('Content-Type: application/json');

        Notification::deleteAll(Session::userId());

        echo json_encode(['success' => true]);
    }

    /* ================= AJAX: READ ================= */

    public function ajaxRead(): void
    {
        Security::requireLogin();
        Security::checkCsrf();

        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false]);
            return;
        }

        Notification::markAsRead($id, Session::userId());

        echo json_encode(['success' => true]);
    }

    /* ================= LEGACY SAFETY ================= */

    public function delete(): void
    {
        $this->ajaxDelete();
    }
}
