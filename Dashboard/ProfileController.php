<?php
declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Core\Security;
use App\Core\Response;
use App\Core\Database;
use App\Core\Csrf;
use App\Services\AuditService;

final class ProfileController
{
    /**
     * GET /dashboard/profile
     * Profilübersicht für User
     */
    public function index(): void
    {
        Security::requireLogin();

        $userId = (int)($_SESSION['user']['id'] ?? 0);
        if ($userId <= 0) {
            Response::error(403);
            return;
        }

        /* =========================================================
           USER + ROLLE
        ========================================================= */
        $user = Database::fetch(
            "SELECT
                u.id,
                u.username,
                u.email,
                u.avatar,
                u.banned_at,
                u.last_seen AS last_login_at,
                r.id    AS role_id,
                r.name  AS role_name,
                r.label AS role_label
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = ?
             LIMIT 1",
            [$userId]
        );

        if (!$user) {
            Response::error(404);
            return;
        }

        /* =========================================================
           OAUTH STATUS
        ========================================================= */
        $oauthRows = Database::fetchAll(
            "SELECT provider, linked_at
             FROM user_oauth_accounts
             WHERE user_id = ?",
            [$userId]
        );

        $oauth = [];
        foreach ($oauthRows as $row) {
            $oauth[$row['provider']] = $row;
        }

        /* =========================================================
           SECURITY LOGS
        ========================================================= */
        $securityLogs = Database::fetchAll(
            "SELECT
                event,
                ip_address,
                created_at
             FROM security_logs
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT 50",
            [$userId]
        );

        /* =========================================================
           EFFEKTIVE PERMISSIONS (ROLLE)
        ========================================================= */
        $permissions = Database::fetchAll(
            "SELECT
                p.name,
                p.label,
                p.category
             FROM permissions p
             JOIN role_permissions rp
               ON rp.permission_id = p.id
             WHERE rp.role_id = ?
             ORDER BY p.category, p.label",
            [(int)$user['role_id']]
        );

        /* =========================================================
           TWITCH SUBSCRIPTION
        ========================================================= */
        $twitchSub = Database::fetch(
            "SELECT tier, started_at
             FROM twitch_subscriptions
             WHERE user_id = ?
             LIMIT 1",
            [$userId]
        );

        /* =========================================================
           BADGES
        ========================================================= */
        $badges = Database::fetchAll(
            "SELECT
                b.id,
                b.label,
                b.icon_path,
                b.type,
                ub.awarded_at
             FROM badges b
             LEFT JOIN user_badges ub
               ON ub.badge_id = b.id
              AND ub.user_id = ?
             ORDER BY b.sort_order, b.label",
            [$userId]
        );

        /* =========================================================
           VIEW
        ========================================================= */
        Response::view('dashboard/profile/index', [
            'title'        => 'Mein Profil',
            'user'         => $user,
            'oauth'        => $oauth,
            'securityLogs' => $securityLogs,
            'permissions'  => $permissions,
            'twitchSub'    => $twitchSub,
            'badges'       => $badges,
        ]);
    }

    /* =========================================================
       AVATAR UPLOAD
    ========================================================= */
    public function uploadAvatar(): void
    {
        Security::requireLogin();
        Security::checkCsrf();

        if (
            empty($_FILES['avatar']) ||
            $_FILES['avatar']['error'] !== UPLOAD_ERR_OK
        ) {
            Response::redirect('/dashboard/profile');
            return;
        }

        $file = $_FILES['avatar'];

        if ($file['size'] > 2 * 1024 * 1024) {
            $_SESSION['flash_error'] = 'Avatar ist zu groß (max. 2 MB).';
            Response::redirect('/dashboard/profile');
            return;
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed, true)) {
            $_SESSION['flash_error'] = 'Ungültiges Bildformat.';
            Response::redirect('/dashboard/profile');
            return;
        }

        $src = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
            'image/png'  => imagecreatefrompng($file['tmp_name']),
            'image/webp' => imagecreatefromwebp($file['tmp_name']),
            default      => null,
        };

        if (!$src) {
            $_SESSION['flash_error'] = 'Bild konnte nicht geladen werden.';
            Response::redirect('/dashboard/profile');
            return;
        }

        $size = min(imagesx($src), imagesy($src));
        $x = (imagesx($src) - $size) / 2;
        $y = (imagesy($src) - $size) / 2;

        $avatarSize = 256;
        $dst = imagecreatetruecolor($avatarSize, $avatarSize);

        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transparent);

        imagecopyresampled(
            $dst,
            $src,
            0,
            0,
            (int)$x,
            (int)$y,
            $avatarSize,
            $avatarSize,
            $size,
            $size
        );

        imagedestroy($src);

        $userId = (int)$_SESSION['user']['id'];
        $filename = 'avatar_' . $userId . '.png';
        $path = BASE_PATH . '/public/uploads/avatars/' . $filename;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        imagepng($dst, $path);
        imagedestroy($dst);

        Database::execute(
            "UPDATE users SET avatar = ? WHERE id = ?",
            [$filename, $userId]
        );

        AuditService::log(
            'user.avatar.updated',
            'user',
            $userId,
            null,
            null
        );

        $_SESSION['flash_success'] = 'Avatar aktualisiert.';
        Response::redirect('/dashboard/profile');
    }

    /**
     * POST /dashboard/profile/unlink-oauth
     */
    public function unlinkOAuth(): void
    {
        Security::requireLogin();
        Security::checkCsrf();

        $provider = $_POST['provider'] ?? '';
        $userId   = (int)$_SESSION['user']['id'];

        if (!in_array($provider, ['discord', 'twitch', 'steam'], true)) {
            $_SESSION['flash_error'] = 'Ungültiger Provider.';
            Response::redirect('/dashboard/profile');
            return;
        }

        Database::execute(
            "DELETE FROM user_oauth_accounts
             WHERE user_id = ? AND provider = ?",
            [$userId, $provider]
        );

        AuditService::log(
            'user.oauth.unlinked.self',
            'user',
            $userId,
            ['provider' => $provider],
            null
        );

        $_SESSION['flash_success'] =
            ucfirst($provider) . ' wurde getrennt.';

        Response::redirect('/dashboard/profile');
    }
}
