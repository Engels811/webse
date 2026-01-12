<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Security;
use App\Core\Session;
use App\Models\Notification;

final class NotificationPageController
{
    /* =========================
       PAGE
    ========================= */

    public function index(): void
    {
        Security::requireLogin();

        // ✅ erlaubte Filter
        $allowedTypes = ['twitch', 'report', 'moderation', 'media', 'system'];

        $type = $_GET['type'] ?? null;
        $type = in_array($type, $allowedTypes, true) ? $type : null;

        // ✅ Notifications laden (optional gefiltert)
        $notifications = Notification::allForUser(
            Session::userId(),
            $type
        );

        View::render('notifications/index', [
            'title'         => 'Benachrichtigungen',
            'notifications' => $notifications,
            'activeType'    => $type,
        ]);
    }

    /* =========================
       FORM ACTIONS (HTML)
    ========================= */

    public function markRead(): void
    {
        Security::requireLogin();
        Security::checkCsrf();

        $id   = (int)($_POST['id'] ?? 0);
        $type = $_POST['type'] ?? null;

        if ($id > 0) {
            Notification::markAsRead($id, Session::userId());
            notify_ui('Benachrichtigung als gelesen markiert', 'success');
        }

        $this->redirectWithFilter($type);
    }

    public function delete(): void
    {
        Security::requireLogin();
        Security::checkCsrf();

        $id   = (int)($_POST['id'] ?? 0);
        $type = $_POST['type'] ?? null;

        if ($id > 0) {
            Notification::delete($id, Session::userId());
            notify_ui('Benachrichtigung gelöscht', 'success');
        }

        $this->redirectWithFilter($type);
    }

    public function markAllRead(): void
    {
        Security::requireLogin();
        Security::checkCsrf();

        $type = $_POST['type'] ?? null;

        Notification::markAllAsRead(Session::userId());
        notify_ui('Alle Benachrichtigungen als gelesen markiert', 'success');

        $this->redirectWithFilter($type);
    }

    public function deleteAll(): void
    {
        Security::requireLogin();
        Security::checkCsrf();

        $type = $_POST['type'] ?? null;

        Notification::deleteAll(Session::userId());
        notify_ui('Alle Benachrichtigungen gelöscht', 'success');

        $this->redirectWithFilter($type);
    }

    /* =========================
       AJAX ACTION
    ========================= */

    public function markReadAjax(): void
    {
        Security::requireLogin();
        Security::checkCsrf();

        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            Notification::markAsRead($id, Session::userId());
        }

        echo 'OK';
        exit;
    }

    /* =========================
       INTERNAL HELPERS
    ========================= */

    private function redirectWithFilter(?string $type): void
    {
        $allowedTypes = ['twitch', 'report', 'moderation', 'media', 'system'];

        $url = '/notifications';
        if ($type && in_array($type, $allowedTypes, true)) {
            $url .= '?type=' . $type;
        }

        header('Location: ' . $url);
        exit;
    }
}
