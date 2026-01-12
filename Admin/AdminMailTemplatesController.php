<?php
declare(strict_types=1);

public function index(): void
{
    Security::requireAdmin();
    Security::require('admin.mail.templates.view');

    $templates = Database::fetchAll(
        "SELECT
            id,
            name,
            subject,
            is_active,
            updated_at
         FROM mail_templates
         ORDER BY name ASC"
    ) ?? []; // ← WICHTIG

    $this->view('admin/mail_templates/index', [
        'title'     => 'Mail-Vorlagen',
        'templates' => $templates,
    ]);
}
