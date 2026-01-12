<?php
/**
 * Newsletter Frontend Controller
 * Handles public newsletter actions
 */

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Core\Security;
use App\Models\Newsletter;
use App\Services\MailService;

class NewsletterController extends BaseController
{
    /**
     * Show subscribe form
     */
    public function subscribe()
    {
        $this->view('public/newsletter_subscribe');
    }
    
    /**
     * Process subscription
     */
    public function processSubscribe()
    {
        Security::validateCSRF();
        
        if (empty($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $this->redirect('/newsletter/subscribe?error=invalid');
        }
        
        $email = trim($_POST['email']);
        $name = trim($_POST['name'] ?? '');
        $categories = $_POST['categories'] ?? [];
        
        // Subscribe
        $result = Newsletter::subscribe($email, $name, $categories, 'website');
        
        if (!$result['success']) {
            $this->redirect('/newsletter/subscribe?error=exists');
        }
        
        // Send verification email if double opt-in is enabled
        if (Newsletter::getSetting('double_optin', 1)) {
            $this->sendVerificationEmail($email, $result['token']);
        }
        
        $this->redirect('/newsletter/subscribe?success=subscribed');
    }
    
    /**
     * Verify email
     */
    public function verify()
    {
        $token = $_GET['token'] ?? '';
        
        if (empty($token)) {
            $this->view('public/newsletter_verify', ['error' => 'invalid_token']);
            return;
        }
        
        if (Newsletter::verify($token)) {
            $this->view('public/newsletter_verify', ['success' => true]);
        } else {
            $this->view('public/newsletter_verify', ['error' => 'invalid_token']);
        }
    }
    
    /**
     * Show unsubscribe form
     */
    public function unsubscribe()
    {
        $this->view('public/newsletter_unsubscribe');
    }
    
    /**
     * Process unsubscribe
     */
    public function processUnsubscribe()
    {
        Security::validateCSRF();
        
        $token = $_POST['token'] ?? '';
        $reason = $_POST['reason'] ?? 'not_specified';
        
        if (empty($token)) {
            $this->redirect('/newsletter/unsubscribe?error=invalid_token');
        }
        
        if (Newsletter::unsubscribe($token)) {
            // Optional: Log reason for statistics
            $this->logUnsubscribeReason($token, $reason);
            
            $this->redirect('/newsletter/unsubscribe?success=unsubscribed');
        } else {
            $this->redirect('/newsletter/unsubscribe?error=not_found');
        }
    }
    
    /**
     * Send verification email
     */
    private function sendVerificationEmail($email, $token)
    {
        $verifyLink = "https://{$_SERVER['HTTP_HOST']}/newsletter/verify?token={$token}";
        
        $template = Newsletter::getTemplate('email_verification');
        
        if (!$template) {
            // Fallback template
            $subject = "Bestätige deine Newsletter-Anmeldung";
            $body = $this->getDefaultVerificationTemplate($verifyLink);
        } else {
            $subject = $template['subject'];
            $body = str_replace('{{verify_link}}', $verifyLink, $template['body']);
        }
        
        $body = str_replace('{{email}}', htmlspecialchars($email), $body);
        
        MailService::send(
            $email,
            $subject,
            $body
        );
    }
    
    /**
     * Default verification email template
     */
    private function getDefaultVerificationTemplate($link)
    {
        return '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Mail Bestätigung</title>
</head>
<body style="margin:0;padding:0;background:#050505;font-family:Arial,Helvetica,sans-serif;color:#ffffff;">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:36px 0;">
<tr>
<td align="center">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#0b0b0b;border-radius:14px;overflow:hidden;box-shadow:0 0 60px rgba(196,0,0,0.35);">
    <tr>
        <td style="padding:28px 0 22px;text-align:center;border-bottom:1px solid #2b0000;">
            <img src="https://i.ibb.co/ns1czZv9/Brennender-Wolf-und-Flammen-Sym33bole-removebg-preview.png" width="120" alt="Engels811 Network">
        </td>
    </tr>
    <tr>
        <td style="padding:20px 30px 0;text-align:center;">
            <h2 style="margin:0;color:#c40000;letter-spacing:1px;">Newsletter-Anmeldung bestätigen</h2>
        </td>
    </tr>
    <tr>
        <td style="padding:30px;text-align:center;line-height:1.7;color:#8a8a8a;">
            <p>Vielen Dank für deine Anmeldung zu unserem Newsletter!</p>
            <p>Bitte klicke auf den Button unten, um deine E-Mail-Adresse zu bestätigen:</p>
            <div style="margin:30px 0;">
                <a href="' . $link . '" style="display:inline-block;padding:14px 32px;background:#c40000;color:#ffffff;text-decoration:none;border-radius:24px;font-weight:bold;letter-spacing:1px;">E-Mail bestätigen</a>
            </div>
            <p style="font-size:11px;">Falls der Button nicht funktioniert, kopiere diesen Link:<br>
            <a href="' . $link . '" style="color:#c40000;word-break:break-all;">' . $link . '</a></p>
        </td>
    </tr>
    <tr><td><img src="https://i.ibb.co/Y7zCgFFt/Chat-GPT-Image-27-Dez-2025-09-42-07.png" width="100%" style="display:block;border:0;"></td></tr>
    <tr>
        <td style="padding:18px 22px;background:#070707;border-top:1px solid #2b0000;text-align:center;font-size:11px;color:#8a8a8a;">
            © 2025 Engels811 Network · Newsletter<br>
            Falls du dich nicht angemeldet hast, ignoriere diese E-Mail.
        </td>
    </tr>
</table>
</td>
</tr>
</table>
</body>
</html>';
    }
    
    /**
     * Log unsubscribe reason
     */
    private function logUnsubscribeReason($token, $reason)
    {
        try {
            $db = \App\Core\Model::getDB();
            $stmt = $db->prepare("
                INSERT INTO newsletter_unsubscribe_reasons (token, reason, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$token, $reason]);
        } catch (\Exception $e) {
            // Silent fail - not critical
        }
    }
}
