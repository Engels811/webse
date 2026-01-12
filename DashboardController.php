<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Database;
use App\Core\Security;
use App\Core\SecurityLog;
use App\Services\MailService;

final class DashboardController
{
    /* =========================
       LOGIN GUARD
    ========================= */
    private function requireLogin(): void
    {
        if (empty($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }
    }

    /* =========================
       DASHBOARD STARTSEITE
    ========================= */
    public function index(): void
    {
        $this->requireLogin();
        $uid = $_SESSION['user']['id'];

        $logins = Database::fetchAll(
            'SELECT ip_address, created_at
             FROM login_logs
             WHERE user_id = ?
               AND success = 1
             ORDER BY created_at DESC
             LIMIT 5',
            [$uid]
        );

        View::render('dashboard/index', [
            'title'  => 'Dashboard',
            'user'   => $_SESSION['user'],
            'logins' => $logins
        ]);
    }

    /* =========================
       INHALTE
    ========================= */
    public function content(): void
    {
        $this->requireLogin();
        $uid = $_SESSION['user']['id'];

        $media_videos = Database::fetchAll(
            'SELECT id, title, created_at
             FROM videos
             WHERE user_id = ?
             ORDER BY created_at DESC',
            [$uid]
        );

        $gallery = Database::fetchAll(
            'SELECT id, title, created_at
             FROM gallery
             WHERE user_id = ?
             ORDER BY created_at DESC',
            [$uid]
        );

        $threads = Database::fetchAll(
            'SELECT id, title, created_at
             FROM forum_threads
             WHERE user_id = ?
             ORDER BY created_at DESC',
            [$uid]
        );

        View::render('dashboard/content', [
            'title'   => 'Meine Inhalte',
            'videos'  => $videos,
            'gallery' => $gallery,
            'threads' => $threads
        ]);
    }

    /* =========================
       SECURITY
    ========================= */
    public function security(): void
    {
        $this->requireLogin();
        $uid = $_SESSION['user']['id'];

        $sessions = Database::fetchAll(
            'SELECT session_id,
                    ip_address AS ip,
                    user_agent,
                    created_at
             FROM login_logs
             WHERE user_id = ?
               AND success = 1
             ORDER BY created_at DESC
             LIMIT 10',
            [$uid]
        );

        $userExtra = Database::fetch(
            'SELECT twofa_enabled FROM users WHERE id = ?',
            [$uid]
        );

        View::render('dashboard/security', [
            'title'    => 'Sicherheit',
            'sessions' => $sessions,
            'user'     => array_merge($_SESSION['user'], $userExtra ?? [])
        ]);
    }

    /* =========================
       EINZELNE SESSION ABMELDEN
    ========================= */
    public function logoutSession(): void
    {
        $this->requireLogin();
        Security::checkCsrf();

        $sid = $_POST['session_id'] ?? '';
        $uid = $_SESSION['user']['id'];

        if ($sid === '' || $sid === session_id()) {
            $_SESSION['flash_error'] = 'Diese Sitzung kann nicht beendet werden.';
            header('Location: /dashboard/security');
            exit;
        }

        Database::execute(
            'DELETE FROM login_logs
             WHERE session_id = ?
               AND user_id = ?',
            [$sid, $uid]
        );

        SecurityLog::log(
            $uid,
            'session_logout',
            'Sitzung manuell beendet'
        );

        $_SESSION['flash_success'] = 'Sitzung wurde abgemeldet.';
        header('Location: /dashboard/security');
        exit;
    }

    /* =========================
       PROFIL
    ========================= */
    public function profile(): void
    {
        $this->requireLogin();
        $uid = $_SESSION['user']['id'];

        $avatars = Database::fetchAll(
            'SELECT filename
             FROM user_avatars
             WHERE user_id = ?
             ORDER BY created_at DESC',
            [$uid]
        );

        $logs = Database::fetchAll(
            'SELECT action, created_at
             FROM profile_logs
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT 10',
            [$uid]
        );

        $user = Database::fetch(
            'SELECT * FROM users WHERE id = ?',
            [$uid]
        );

        $_SESSION['user'] = array_merge($_SESSION['user'], $user);

        View::render('dashboard/profile/index', [
            'title'   => 'Mein Profil',
            'user'    => $_SESSION['user'],
            'avatars' => $avatars,
            'logs'    => $logs
        ]);
    }

    /* =========================
       PROFIL UPDATE
    ========================= */
    public function updateProfile(): void
    {
        $this->requireLogin();
        Security::checkCsrf();

        $uid      = $_SESSION['user']['id'];
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');

        if (
            strlen($username) < 3 ||
            strlen($username) > 32 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            $_SESSION['flash_error'] = 'Ungültige Eingaben';
            header('Location: /dashboard/profile');
            exit;
        }

        $exists = Database::fetch(
            'SELECT id FROM users WHERE username = ? AND id != ?',
            [$username, $uid]
        );

        if ($exists) {
            $_SESSION['flash_error'] = 'Benutzername bereits vergeben';
            header('Location: /dashboard/profile');
            exit;
        }

        $cooldown = Database::fetch(
            'SELECT username_changed_at, email FROM users WHERE id = ?',
            [$uid]
        );

        if (
            $username !== $_SESSION['user']['username'] &&
            !empty($cooldown['username_changed_at']) &&
            strtotime($cooldown['username_changed_at']) > strtotime('-90 days')
        ) {
            $_SESSION['flash_error'] =
                'Benutzername kann nur alle 90 Tage geändert werden.';
            header('Location: /dashboard/profile');
            exit;
        }

        if ($email !== $cooldown['email']) {

            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 86400);

            Database::execute(
                'UPDATE users
                 SET username = ?,
                     pending_email = ?,
                     email_change_token = ?,
                     email_change_expires = ?,
                     username_changed_at = NOW()
                 WHERE id = ?',
                [$username, $email, $token, $expires, $uid]
            );

            MailService::sendEmailChangeConfirmation(
                $email,
                $username,
                $token
            );

            $_SESSION['flash_success'] =
                'Profil gespeichert – bitte bestätige deine neue E-Mail-Adresse.';
        } else {

            Database::execute(
                'UPDATE users
                 SET username = ?, username_changed_at = NOW()
                 WHERE id = ?',
                [$username, $uid]
            );

            $_SESSION['flash_success'] = 'Profil gespeichert';
        }

        $_SESSION['user']['username'] = $username;

        Database::execute(
            'INSERT INTO profile_logs (user_id, action)
             VALUES (?, ?)',
            [$uid, 'Profil aktualisiert']
        );

        Database::execute(
            'INSERT INTO audit_logs (actor_id, target_user_id, action)
             VALUES (?, ?, ?)',
            [$uid, $uid, 'Profil geändert']
        );

        header('Location: /dashboard/profile');
        exit;
    }

    /* =========================
       AVATAR UPLOAD
    ========================= */
    public function updateAvatar(): void
    {
        $this->requireLogin();
        Security::checkCsrf();

        $uid  = $_SESSION['user']['id'];
        $file = $_FILES['avatar'] ?? null;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'Avatar-Upload fehlgeschlagen';
            header('Location: /dashboard/profile');
            exit;
        }

        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            $_SESSION['flash_error'] = 'Nur JPG oder PNG erlaubt';
            header('Location: /dashboard/profile');
            exit;
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            $_SESSION['flash_error'] = 'Avatar darf max. 2 MB groß sein';
            header('Location: /dashboard/profile');
            exit;
        }

        $dir = BASE_PATH . '/public/uploads/avatars/';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $src = imagecreatefromstring(file_get_contents($file['tmp_name']));
        $out = imagecreatetruecolor(256, 256);

        $size = min(imagesx($src), imagesy($src));
        imagecopyresampled($out, $src, 0, 0, 0, 0, 256, 256, $size, $size);

        $name = 'avatar_' . $uid . '_' . time() . '.jpg';
        imagejpeg($out, $dir . $name, 90);

        imagedestroy($src);
        imagedestroy($out);

        Database::execute(
            'INSERT INTO user_avatars (user_id, filename)
             VALUES (?, ?)',
            [$uid, $name]
        );

        Database::execute(
            'UPDATE users SET avatar = ? WHERE id = ?',
            [$name, $uid]
        );

        $_SESSION['user']['avatar'] = $name;

        Database::execute(
            'INSERT INTO profile_logs (user_id, action)
             VALUES (?, ?)',
            [$uid, 'Avatar geändert']
        );

        $_SESSION['flash_success'] = 'Avatar aktualisiert';
        header('Location: /dashboard/profile');
        exit;
    }

    /* =========================
       AVATAR AUS HISTORIE
    ========================= */
    public function selectAvatar(): void
    {
        $this->requireLogin();
        Security::checkCsrf();

        $uid    = $_SESSION['user']['id'];
        $avatar = $_POST['avatar'] ?? '';

        $valid = Database::fetch(
            'SELECT filename
             FROM user_avatars
             WHERE user_id = ?
               AND filename = ?',
            [$uid, $avatar]
        );

        if (!$valid) {
            $_SESSION['flash_error'] = 'Avatar ungültig';
            header('Location: /dashboard/profile');
            exit;
        }

        Database::execute(
            'UPDATE users SET avatar = ? WHERE id = ?',
            [$avatar, $uid]
        );

        $_SESSION['user']['avatar'] = $avatar;

        Database::execute(
            'INSERT INTO profile_logs (user_id, action)
             VALUES (?, ?)',
            [$uid, 'Avatar aus Historie gewählt']
        );

        $_SESSION['flash_success'] = 'Avatar geändert';
        header('Location: /dashboard/profile');
        exit;
    }
}
