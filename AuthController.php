<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Database;
use App\Services\TokenService;
use App\Services\TwoFactorService;
use App\Services\SystemMailer;

final class AuthController
{
    private ?TwoFactorService $twoFactorService;
    private ?SystemMailer $systemMailer;
    private TokenService $tokenService;

    public function __construct(
        ?TwoFactorService $twoFactorService = null,
        ?SystemMailer $systemMailer = null
    ) {
        $this->twoFactorService = $twoFactorService;
        $this->systemMailer     = $systemMailer;
        $this->tokenService     = new TokenService();
    }

    /* =====================================================
       LOGIN
    ===================================================== */
    public function login(): void
    {
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $login    = trim($_POST['login'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($login === '' || $password === '') {
                $error = 'Bitte alle Felder ausfüllen.';
            } else {

                $sql = filter_var($login, FILTER_VALIDATE_EMAIL)
                    ? "
                        SELECT
                            u.id,
                            u.username,
                            u.email,
                            u.password,
                            
                            r.id    AS role_id,
                            r.name  AS role_name,
                            r.level AS role_level
                            
                        FROM users u
                        LEFT JOIN roles r ON r.id = u.role_id
                        WHERE u.email = ?
                        LIMIT 1
                      "
                    : "
                        SELECT
                            u.id,
                            u.username,
                            u.email,
                            u.password,
                            
                            r.id    AS role_id,
                            r.name  AS role_name,
                            r.level AS role_level
                            
                        FROM users u
                        LEFT JOIN roles r ON r.id = u.role_id
                        WHERE u.username = ?
                        LIMIT 1
                      ";
                            
                $user = Database::fetch($sql, [$login]);
                if (!$user || !password_verify($password, $user['password'])) {
                    $error = 'Login fehlgeschlagen.';
                } else {

                    /* =========================
                       ROLLE LADEN (RBAC)
                    ========================= */
                    $role = Database::fetch(
                        "SELECT id, name, level
                         FROM roles
                         WHERE id = ?
                         LIMIT 1",
                        [$user['role_id']]
                    );

                    if (!$role) {
                        $error = 'Deinem Account ist keine gültige Rolle zugewiesen.';
                    } else {

                        session_regenerate_id(true);

                            $_SESSION['user'] = [
                                'id'         => (int)$user['id'],
                                'username'   => $user['username'],
                                'email'      => $user['email'],
                            
                                'role_id'    => (int)$user['role_id'],
                                'role_name'  => $user['role_name'],
                                'role_level' => (int)$user['role_level'],
                            ];


                        header('Location: /dashboard');
                        exit;
                    }
                }
            }
        }

        View::render('auth/login', [
            'title' => 'Login',
            'error' => $error
        ]);
    }

    /* =====================================================
       LOGOUT
    ===================================================== */
    public function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);

        header('Location: /login');
        exit;
    }

    /* =====================================================
       PASSWORD RESET – FORM
    ===================================================== */
    public function forgotPassword(): void
    {
        View::render('auth/forgot_password', [
            'title' => 'Passwort vergessen'
        ]);
    }

    /* =====================================================
       PASSWORD RESET – SEND MAIL
    ===================================================== */
    public function sendPasswordReset(): void
    {
        $email = trim($_POST['email'] ?? '');

        if ($email !== '') {

            $user = Database::fetch(
                'SELECT id, username, email FROM users WHERE email = ? LIMIT 1',
                [$email]
            );

            if ($user) {
                $token = $this->tokenService->generate();

                Database::execute(
                    'INSERT INTO password_resets (user_id, token, expires_at)
                     VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))',
                    [$user['id'], $this->tokenService->hash($token)]
                );

                if ($this->systemMailer) {
                    $link = $this->tokenService->passwordResetLink($token);
                    $this->systemMailer->sendPasswordReset($user, $link);
                }
            }
        }

        View::render('auth/forgot_password_done', [
            'title' => 'E-Mail gesendet'
        ]);
    }

    /* =====================================================
       PASSWORD RESET – FORM
    ===================================================== */
    public function resetPassword(): void
    {
        $token = $_GET['token'] ?? '';

        $reset = Database::fetch(
            'SELECT * FROM password_resets
             WHERE token = ?
               AND expires_at > NOW()
               AND used_at IS NULL
             LIMIT 1',
            [$this->tokenService->hash($token)]
        );

        if (!$reset) {
            View::render('errors/419', ['title' => 'Link ungültig']);
            return;
        }

        View::render('auth/reset_password', [
            'title' => 'Neues Passwort setzen',
            'token' => $token
        ]);
    }

    /* =====================================================
       PASSWORD RESET – UPDATE
    ===================================================== */
    public function updatePassword(): void
    {
        $token    = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        if ($password === '' || $password !== $confirm || strlen($password) < 8) {
            View::render('auth/reset_password', [
                'title' => 'Neues Passwort setzen',
                'token' => $token,
                'error' => 'Passwort ungültig oder zu kurz.'
            ]);
            return;
        }

        $reset = Database::fetch(
            'SELECT * FROM password_resets
             WHERE token = ?
               AND expires_at > NOW()
               AND used_at IS NULL
             LIMIT 1',
            [$this->tokenService->hash($token)]
        );

        if (!$reset) {
            View::render('errors/419', ['title' => 'Link ungültig']);
            return;
        }

        Database::execute(
            'UPDATE users SET password = ? WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]
        );

        Database::execute(
            'UPDATE password_resets SET used_at = NOW() WHERE id = ?',
            [$reset['id']]
        );

        View::render('auth/reset_success', [
            'title' => 'Passwort geändert'
        ]);
    }

        /* =====================================================
       E-MAIL BESTÄTIGUNG – ERNEUT SENDEN
    ===================================================== */
    public function resendConfirmEmail(): void
    {
        // 🔐 Login erforderlich
        if (empty($_SESSION['user']['id'])) {
            header('Location: /login');
            exit;
        }
    
        $userId = (int)$_SESSION['user']['id'];
    
        // User laden
        $user = Database::fetch(
            'SELECT
                id,
                username,
                email,
                email_verified_at
             FROM users
             WHERE id = ?
             LIMIT 1',
            [$userId]
        );
    
            if (!$user) {
            View::render('errors/404', ['title' => 'User nicht gefunden']);
            return;
        }

        // Bereits bestätigt?
        if (!empty($user['email_verified_at'])) {
            View::render('auth/confirm_already', [
                'title' => 'E-Mail bereits bestätigt'
            ]);
            return;
        }

        // Token erzeugen
        $token = $this->tokenService->generate();

        Database::execute(
            'UPDATE users
             SET email_verify_token = ?,
                 email_verify_sent_at = NOW()
             WHERE id = ?',
            [
                $this->tokenService->hash($token),
                $userId
            ]
        );

        // Mail versenden
        if ($this->systemMailer) {
            $confirmLink = $this->tokenService->emailConfirmLink($token);
            $this->systemMailer->sendEmailConfirmation($user, $confirmLink);
        }

        View::render('auth/confirm_sent', [
            'title' => 'Bestätigungs-Mail gesendet'
        ]);
    }

    /* =====================================================
       SOCIAL LOGIN – DISCORD
    ===================================================== */
    public function discord(): void
    {
        // TODO: OAuth Redirect
        View::render('auth/discord', [
            'title'    => 'Discord Login',
            'provider' => 'Discord'
        ]);
    }

    /* =====================================================
       SOCIAL LOGIN – TWITCH
    ===================================================== */
    public function twitch(): void
    {
        // TODO: OAuth Redirect
        View::render('auth/twitch', [
            'title'    => 'Twitch Login',
            'provider' => 'Twitch'
        ]);
    }

    /* =====================================================
       SOCIAL LOGIN – STEAM
    ===================================================== */
    public function steam(): void
    {
        // TODO: OpenID Redirect
        View::render('auth/steam', [
            'title'    => 'Steam Login',
            'provider' => 'Steam'
        ]);
    }


}
