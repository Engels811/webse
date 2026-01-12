<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ContactOption;

final class ContactOptionsController extends BaseController
{
    /**
     * Übersicht aller Kontaktoptionen
     */
    public function index(): void
    {
        $this->requireAdmin();
        
        $this->view('admin/contact_options/index', [
            'title' => 'Kontaktoptionen verwalten',
            'options' => ContactOption::all()
        ]);
    }

    /**
     * Neue Option erstellen
     */
    public function store(): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/contact-options');
            return;
        }

        $data = [
            'label'     => trim($_POST['label'] ?? ''),
            'value'     => trim($_POST['value'] ?? ''),
            'type'      => $_POST['type'] ?? 'text',
            'icon'      => $_POST['icon'] ?? 'link',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        // Validation
        if (empty($data['label']) || empty($data['value'])) {
            $_SESSION['flash_error'] = 'Label und Wert sind Pflichtfelder.';
            $this->redirect('/admin/contact-options');
            return;
        }

        ContactOption::create($data);
        
        $_SESSION['flash_success'] = 'Kontaktoption erfolgreich erstellt.';
        $this->redirect('/admin/contact-options');
    }

    /**
     * Option bearbeiten
     */
    public function update(int $id): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/contact-options');
            return;
        }

        $data = [
            'label'     => trim($_POST['label'] ?? ''),
            'value'     => trim($_POST['value'] ?? ''),
            'type'      => $_POST['type'] ?? 'text',
            'icon'      => $_POST['icon'] ?? 'link',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        // Validation
        if (empty($data['label']) || empty($data['value'])) {
            $_SESSION['flash_error'] = 'Label und Wert sind Pflichtfelder.';
            $this->redirect('/admin/contact-options');
            return;
        }

        ContactOption::update($id, $data);
        
        $_SESSION['flash_success'] = 'Kontaktoption erfolgreich aktualisiert.';
        $this->redirect('/admin/contact-options');
    }

    /**
     * Option aktivieren/deaktivieren (AJAX)
     */
    public function toggle(int $id): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;
        
        ContactOption::update($id, ['is_active' => $isActive]);
        
        http_response_code(200);
        echo json_encode(['success' => true, 'is_active' => $isActive]);
    }

    /**
     * Option löschen
     */
    public function delete(int $id): void
    {
        $this->requireAdmin();

        ContactOption::delete($id);
        
        // Wenn AJAX Request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            http_response_code(200);
            echo json_encode(['success' => true]);
            return;
        }

        $_SESSION['flash_success'] = 'Kontaktoption erfolgreich gelöscht.';
        $this->redirect('/admin/contact-options');
    }

    /**
     * Sortierung speichern (AJAX)
     */
    public function sort(): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        if (!isset($_POST['order']) || !is_array($_POST['order'])) {
            http_response_code(400);
            return;
        }

        foreach ($_POST['order'] as $position => $id) {
            ContactOption::updateSort((int)$id, $position);
        }

        http_response_code(200);
        echo json_encode(['success' => true]);
    }
}