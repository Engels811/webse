<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\ContactOption;
use App\Services\MailService;

final class ContactController
{
    /**
     * Kontaktformular anzeigen
     */
    public function showForm(): void
    {
        $options = ContactOption::active();

        // Formulardaten aus Session (nach Fehler)
        $formData = $_SESSION['form_data'] ?? [];
        unset($_SESSION['form_data']);

        // Flash Messages holen
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        // View rendern
        require BASE_PATH . '/app/Views/contact/form.php';
    }

    /**
     * Formular absenden
     */
    public function submitForm(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        // Honeypot Check
        if (!empty($_POST['website'] ?? '')) {
            http_response_code(200);
            return;
        }

        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        // Daten für Repopulation speichern
        $_SESSION['form_data'] = compact('name', 'email', 'subject', 'message');

        // Validation
        if ($name === '' || $email === '' || $subject === '' || $message === '') {
            $_SESSION['flash_error'] = 'Bitte alle Felder ausfüllen.';
            header('Location: /kontakt');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Ungültige E-Mail-Adresse.';
            header('Location: /kontakt');
            exit;
        }

        // Mail versenden (falls MailService existiert)
        if (class_exists('App\Services\MailService')) {
            if (!MailService::sendContact($name, $email, $subject . "\n\n" . $message)) {
                $_SESSION['flash_error'] = 'Versand fehlgeschlagen. Bitte versuche es später erneut.';
                header('Location: /kontakt');
                exit;
            }
        }

        // Erfolg
        unset($_SESSION['form_data']);
        $_SESSION['flash_success'] = 'Nachricht erfolgreich gesendet. Wir melden uns bald!';
        header('Location: /kontakt');
        exit;
    }
}