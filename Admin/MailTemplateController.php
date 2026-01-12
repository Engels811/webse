<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Security;
use App\Core\View;
use App\Core\Response;
use App\Core\Database;

final class MailTemplateController
{
    private const TEMPLATE_PATH = BASE_PATH . '/app/Mail/Templates/';

    /**
     * 🔥 FINAL FIX: Mail Templates Liste anzeigen
     * Lädt Templates aus Datenbank ODER aus Dateisystem
     */
    public function index(): void
    {
        Security::requireLogin();

        // Permission check mit Fallback
        if (!Security::can('mail.templates.manage') && !Security::can('admin.access')) {
            Response::error(403);
            return;
        }

        // 🔥 FIX: Templates aus Datenbank laden (ohne updated_at!)
        $dbTemplates = [];
        try {
            $dbTemplates = Database::fetchAll(
                "SELECT 
                    id,
                    name,
                    name AS `key`,
                    subject AS description,
                    'Datenbank' AS category,
                    created_at AS updated_at
                 FROM mail_templates
                 WHERE is_active = 1
                 ORDER BY name ASC"
            ) ?? [];
        } catch (\Exception $e) {
            // Ignorieren falls Tabelle nicht existiert oder Fehler
            error_log("Mail templates DB error: " . $e->getMessage());
        }

        // 🔥 FIX: Templates aus Dateisystem laden (Fallback)
        $fileTemplates = $this->getFileTemplates();

        // Zusammenführen
        $templates = array_merge($dbTemplates, $fileTemplates);

        // Falls keine Templates gefunden, Beispiel-Daten für Demo
        if (empty($templates)) {
            $templates = $this->getDemoTemplates();
        }

        View::render('admin/mail_templates/index', [
            'title'     => 'Mail Templates',
            'templates' => $templates
        ]);
    }

    /**
     * 🆕 Lädt Templates aus Dateisystem
     */
    private function getFileTemplates(): array
    {
        $templates = [];
        $directories = [
            'sicherheit' => 'Sicherheit',
            'benachrichtigungen' => 'Benachrichtigungen',
            'system' => 'System'
        ];

        foreach ($directories as $dir => $category) {
            $path = self::TEMPLATE_PATH . $dir . '/';
            
            if (!is_dir($path)) {
                continue;
            }

            $files = glob($path . '*.php');
            
            foreach ($files as $file) {
                $basename = basename($file, '.php');
                
                $templates[] = [
                    'name' => str_replace('_', ' ', ucfirst($basename)),
                    'key' => $dir . '/' . $basename,
                    'description' => 'Template: ' . $basename,
                    'category' => $category,
                    'updated_at' => date('Y-m-d H:i:s', filemtime($file))
                ];
            }
        }

        return $templates;
    }

    /**
     * 🆕 Demo-Templates falls keine echten existieren
     */
    private function getDemoTemplates(): array
    {
        return [
            [
                'name' => 'E-Mail Bestätigung',
                'key' => 'sicherheit/email_bestaetigung',
                'description' => 'Template für E-Mail-Bestätigung',
                'category' => 'Sicherheit',
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Passwort zurücksetzen',
                'key' => 'sicherheit/passwort_reset',
                'description' => 'Template für Passwort-Reset',
                'category' => 'Sicherheit',
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'name' => '2FA Code',
                'key' => 'sicherheit/zwei_faktor_code',
                'description' => 'Template für 2FA-Codes',
                'category' => 'Sicherheit',
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Willkommensmail',
                'key' => 'system/willkommen',
                'description' => 'Template für neue Benutzer',
                'category' => 'System',
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ];
    }

    /**
     * Mail Template bearbeiten
     */
    public function edit(string $key): void
    {
        Security::requireLogin();

        if (!Security::can('mail.templates.manage') && !Security::can('admin.access')) {
            Response::error(403);
            return;
        }

        // 🔥 FIX: Unterstütze verschiedene Pfadformate
        $file = $this->resolveTemplatePath($key);

        if (!$file || !is_file($file)) {
            // Versuche aus Datenbank zu laden
            $dbTemplate = $this->getDbTemplate($key);
            
            if ($dbTemplate) {
                View::render('admin/mail_templates/mail_template_editor', [
                    'title'    => 'Template bearbeiten',
                    'key'      => $key,
                    'content'  => $dbTemplate['body'] ?? '',
                    'subject'  => $dbTemplate['subject'] ?? '',
                    'is_db'    => true
                ]);
                return;
            }
            
            Response::error(404);
            return;
        }

        View::render('admin/mail_templates/mail_template_editor', [
            'title'    => 'Template bearbeiten',
            'key'      => $key,
            'content'  => file_get_contents($file),
            'is_db'    => false
        ]);
    }

    /**
     * 🆕 Template aus DB laden
     */
    private function getDbTemplate(string $key): ?array
    {
        try {
            return Database::fetch(
                "SELECT * FROM mail_templates WHERE name = ? OR id = ? LIMIT 1",
                [$key, (int)$key]
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 🆕 Template-Pfad auflösen
     */
    private function resolveTemplatePath(string $key): ?string
    {
        // Verschiedene Möglichkeiten probieren
        $possiblePaths = [
            self::TEMPLATE_PATH . $key . '.php',
            self::TEMPLATE_PATH . $key . '.html.php',
            self::TEMPLATE_PATH . basename($key) . '.php',
            self::TEMPLATE_PATH . basename($key) . '.html.php',
        ];

        foreach ($possiblePaths as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Mail Template speichern
     */
    public function save(): void
    {
        Security::requireLogin();
        Security::verifyCsrf();

        if (!Security::can('mail.templates.manage') && !Security::can('admin.access')) {
            Response::error(403);
            return;
        }

        $key     = $_POST['key'] ?? '';
        $content = $_POST['content'] ?? '';
        $isDb    = isset($_POST['is_db']) && $_POST['is_db'] === '1';

        if ($isDb) {
            // In Datenbank speichern
            try {
                Database::execute(
                    "UPDATE mail_templates SET body = ? WHERE name = ? OR id = ?",
                    [$content, $key, (int)$key]
                );
                
                header('Location: /admin/mail-templates?success=saved');
                exit;
            } catch (\Exception $e) {
                error_log("Template save error: " . $e->getMessage());
                header('Location: /admin/mail-templates?error=save_failed');
                exit;
            }
        }

        // In Datei speichern
        $file = $this->resolveTemplatePath($key);

        if (!$file || !is_file($file)) {
            header('Location: /admin/mail-templates?error=not_found');
            exit;
        }

        // Backup erstellen
        $backupDir = self::TEMPLATE_PATH . 'Versions/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }

        $backupFile = sprintf(
            '%s%s_%s_%s.php',
            $backupDir,
            str_replace('/', '_', $key),
            date('Ymd_His'),
            $_SESSION['user']['username'] ?? 'unknown'
        );

        copy($file, $backupFile);
        file_put_contents($file, $content);

        header('Location: /admin/mail-templates?success=saved');
        exit;
    }

    /**
     * Test-Mail senden
     */
    public function sendTest(): void
    {
        Security::requireLogin();
        Security::verifyCsrf();

        if (!Security::can('mail.templates.test.send') && !Security::can('admin.access')) {
            Response::error(403);
            return;
        }

        $key   = $_POST['key'] ?? '';
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

        if (!$email) {
            header('Location: /admin/mail-templates?error=invalid_email');
            exit;
        }

        try {
            // Test-Mail senden
            $result = \Mailer::send(
                $email,
                '[TEST] Mail Template Test',
                '<h1>Test-Mail</h1><p>Dies ist eine Test-Mail für Template: ' . htmlspecialchars($key) . '</p>'
            );

            if ($result) {
                header('Location: /admin/mail-templates?success=test_sent');
            } else {
                header('Location: /admin/mail-templates?error=test_failed');
            }
        } catch (\Exception $e) {
            error_log("Test mail failed: " . $e->getMessage());
            header('Location: /admin/mail-templates?error=test_failed');
        }
        exit;
    }

    /**
     * Vorschau (Sandbox)
     */
    public function sandboxPreview(): void
    {
        Security::requireLogin();

        if (!Security::can('mail.templates.view') && !Security::can('admin.access')) {
            Response::error(403);
            return;
        }

        $key  = $_POST['key'] ?? '';
        $json = $_POST['json'] ?? '';

        echo json_encode([
            'success' => true,
            'preview' => '<h1>Vorschau</h1><p>Template: ' . htmlspecialchars($key) . '</p>'
        ]);
        exit;
    }

    /**
     * Export aller Templates als ZIP
     */
    public function exportAll(): void
    {
        Security::requireLogin();

        if (!Security::can('mail.templates.manage') && !Security::can('admin.access')) {
            Response::error(403);
            return;
        }

        $zip = new \ZipArchive();
        $zipFileName = '/tmp/templates_' . date('Ymd_His') . '.zip';

        if ($zip->open($zipFileName, \ZipArchive::CREATE) !== TRUE) {
            die("Kann ZIP nicht erstellen");
        }

        // PHP-Templates
        $files = glob(self::TEMPLATE_PATH . '*/*.php');
        foreach ($files as $file) {
            $relativePath = str_replace(self::TEMPLATE_PATH, '', $file);
            $zip->addFile($file, $relativePath);
        }

        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="templates_' . date('Ymd_His') . '.zip"');
        header('Content-Length: ' . filesize($zipFileName));

        readfile($zipFileName);
        unlink($zipFileName);
        exit;
    }

    /**
     * 🆕 Neues Template erstellen (Formular anzeigen)
     */
    public function create(): void
    {
        Security::requireLogin();

        if (!Security::can('mail.templates.manage') && !Security::can('admin.access')) {
            Response::error(403);
            return;
        }

        View::render('admin/mail_templates/create', [
            'title' => 'Neues Template erstellen'
        ]);
    }

    /**
     * 🆕 Neues Template speichern
     */
    public function store(): void
    {
        Security::requireLogin();
        Security::verifyCsrf();

        if (!Security::can('mail.templates.manage') && !Security::can('admin.access')) {
            Response::error(403);
            return;
        }

        $type = $_POST['type'] ?? 'custom';
        $name = trim($_POST['name'] ?? '');
        $key = trim($_POST['key'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $body = $_POST['body'] ?? '';

        // Validierung
        if (empty($name) || empty($key) || empty($subject) || empty($body)) {
            header('Location: /admin/mail-templates/create?error=invalid');
            exit;
        }

        // Key validieren (nur a-z, 0-9, _, -)
        if (!preg_match('/^[a-z0-9_-]+$/', $key)) {
            header('Location: /admin/mail-templates/create?error=invalid');
            exit;
        }

        // Typ-Mapping
        $typeMap = [
            'security' => 'sicherheit',
            'notification' => 'benachrichtigungen',
            'system' => 'system',
            'custom' => 'custom'
        ];

        $folder = $typeMap[$type] ?? 'custom';
        $fullKey = $folder . '/' . $key;

        // Prüfen ob Template bereits existiert (Datenbank)
        try {
            $exists = Database::fetch(
                "SELECT id FROM mail_templates WHERE name = ? LIMIT 1",
                [$key]
            );

            if ($exists) {
                header('Location: /admin/mail-templates/create?error=exists');
                exit;
            }
        } catch (\Exception $e) {
            // Ignorieren falls Tabelle nicht existiert
        }

        // Prüfen ob Datei bereits existiert
        $filePath = self::TEMPLATE_PATH . $fullKey . '.php';
        if (file_exists($filePath)) {
            header('Location: /admin/mail-templates/create?error=exists');
            exit;
        }

        // Verzeichnis erstellen falls nicht vorhanden
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // Template als Datei speichern
        file_put_contents($filePath, $body);

        // Auch in Datenbank speichern
        try {
            Database::execute(
                "INSERT INTO mail_templates (name, subject, body, is_active, created_at)
                 VALUES (?, ?, ?, 1, NOW())",
                [$key, $subject, $body]
            );
        } catch (\Exception $e) {
            // Ignorieren falls Tabelle nicht existiert
            error_log("Could not save template to database: " . $e->getMessage());
        }

        // Erfolg!
        header('Location: /admin/mail-templates?success=created');
        exit;
    }
}