<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Security;
use App\Core\Database;
use App\Core\Response;

/**
 * ENGELS811 NETWORK - Admin Hardware Controller (COMPLETE)
 * 
 * Features:
 * - Hardware-Setups verwalten
 * - Items verwalten
 * - Bildergalerie hochladen & verwalten
 * - Bilder vom PC hochladen
 */
final class AdminHardwareController
{
    private const UPLOAD_DIR = '/public/uploads/hardware/';
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    private const ALLOWED_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];

    /* =====================================================
       SETUPS
    ===================================================== */

    public function index(): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.hardware.view');

        $setups = Database::fetchAll(
            "SELECT *
             FROM hardware_setups
             ORDER BY is_active DESC, id DESC"
        ) ?? [];

        View::render('admin/hardware/index', [
            'title'  => 'Hardware-Setups',
            'setups' => $setups
        ]);
    }

    public function create(): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.hardware.create');

        View::render('admin/hardware/form', [
            'title' => 'Setup erstellen',
            'setup' => null
        ]);
    }

    public function store(): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.hardware.create');
        Security::checkCsrf();

        $title       = trim($_POST['title'] ?? '');
        $slug        = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        if ($title === '') {
            $_SESSION['flash_error'] = 'Titel ist erforderlich.';
            Response::redirect('/admin/hardware/create');
        }

        // Slug automatisch generieren falls leer
        if ($slug === '') {
            $slug = $this->generateSlug($title);
        }

        Database::execute(
            "INSERT INTO hardware_setups (title, slug, description, is_active, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$title, $slug, $description, $isActive]
        );

        $_SESSION['flash_success'] = 'Setup wurde erstellt.';
        Response::redirect('/admin/hardware');
    }

    public function edit(int $id): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.hardware.edit');

        $setup = Database::fetch(
            "SELECT * FROM hardware_setups WHERE id = ?",
            [$id]
        );

        if (!$setup) {
            Response::error(404);
        }

        View::render('admin/hardware/form', [
            'title' => 'Setup bearbeiten',
            'setup' => $setup
        ]);
    }

    public function update(): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.hardware.edit');
        Security::checkCsrf();

        $id          = (int)($_POST['id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $slug        = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        if ($id <= 0 || $title === '') {
            Response::error(400);
        }

        if ($slug === '') {
            $slug = $this->generateSlug($title);
        }

        Database::execute(
            "UPDATE hardware_setups
             SET title = ?, slug = ?, description = ?, is_active = ?, updated_at = NOW()
             WHERE id = ?",
            [$title, $slug, $description, $isActive, $id]
        );

        $_SESSION['flash_success'] = 'Setup wurde aktualisiert.';
        Response::redirect('/admin/hardware');
    }

    public function delete(): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.hardware.delete');
        Security::checkCsrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            Response::error(400);
        }

        // Lösche zugehörige Bilder
        $images = Database::fetchAll(
            "SELECT * FROM hardware_images WHERE setup_id = ?",
            [$id]
        );

        foreach ($images as $img) {
            $this->deleteImageFile($img['filename']);
        }

        Database::execute("DELETE FROM hardware_images WHERE setup_id = ?", [$id]);
        Database::execute("DELETE FROM hardware_items WHERE setup_id = ?", [$id]);
        Database::execute("DELETE FROM hardware_setups WHERE id = ?", [$id]);

        $_SESSION['flash_success'] = 'Setup wurde gelöscht.';
        Response::redirect('/admin/hardware');
    }

    /* =====================================================
       ITEMS
    ===================================================== */

    public function items(int $setupId): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.hardware.view');

        $setup = Database::fetch(
            "SELECT * FROM hardware_setups WHERE id = ?",
            [$setupId]
        );

        if (!$setup) {
            Response::error(404);
        }

        $items = Database::fetchAll(
            "SELECT *
             FROM hardware_items
             WHERE setup_id = ?
             ORDER BY category ASC, sort ASC, position ASC, id ASC",
            [$setupId]
        ) ?? [];

        View::render('admin/hardware/items', [
            'title' => 'Items – ' . ($setup['title'] ?? 'Setup'),
            'setup' => $setup,
            'items' => $items
        ]);
    }

    public function createItem(int $setupId): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.hardware.create');

        $setup = Database::fetch(
            "SELECT * FROM hardware_setups WHERE id = ?",
            [$setupId]
        );

        if (!$setup) {
            Response::error(404);
        }

        View::render('admin/hardware/item-form', [
            'title' => 'Item erstellen',
            'setup' => $setup,
            'item'  => null
        ]);
    }

    public function storeItem(): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.hardware.create');
        Security::checkCsrf();

        $setupId  = (int)($_POST['setup_id'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $title    = trim($_POST['title'] ?? '');
        $details  = trim($_POST['details'] ?? '');
        $icon     = trim($_POST['icon'] ?? '');
        $sort     = (int)($_POST['sort'] ?? 0);
        $position = (int)($_POST['position'] ?? 0);

        if ($setupId <= 0 || $category === '' || $title === '') {
            Response::error(400);
        }

        Database::execute(
            "INSERT INTO hardware_items
             (setup_id, category, title, details, icon, sort, position, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [$setupId, $category, $title, $details, $icon, $sort, $position]
        );

        $_SESSION['flash_success'] = 'Item wurde erstellt.';
        Response::redirect('/admin/hardware/' . $setupId . '/items');
    }

    public function editItem(int $id): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.hardware.edit');

        $item = Database::fetch(
            "SELECT * FROM hardware_items WHERE id = ?",
            [$id]
        );

        if (!$item) {
            Response::error(404);
        }

        $setup = Database::fetch(
            "SELECT * FROM hardware_setups WHERE id = ?",
            [(int)$item['setup_id']]
        );

        View::render('admin/hardware/item-form', [
            'title' => 'Item bearbeiten',
            'setup' => $setup,
            'item'  => $item
        ]);
    }

    public function updateItem(): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.hardware.edit');
        Security::checkCsrf();

        $id       = (int)($_POST['id'] ?? 0);
        $setupId  = (int)($_POST['setup_id'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $title    = trim($_POST['title'] ?? '');
        $details  = trim($_POST['details'] ?? '');
        $icon     = trim($_POST['icon'] ?? '');
        $sort     = (int)($_POST['sort'] ?? 0);
        $position = (int)($_POST['position'] ?? 0);

        if ($id <= 0 || $category === '' || $title === '') {
            Response::error(400);
        }

        Database::execute(
            "UPDATE hardware_items
             SET category = ?, title = ?, details = ?, icon = ?, sort = ?, position = ?, updated_at = NOW()
             WHERE id = ?",
            [$category, $title, $details, $icon, $sort, $position, $id]
        );

        $_SESSION['flash_success'] = 'Item wurde aktualisiert.';
        Response::redirect('/admin/hardware/' . $setupId . '/items');
    }

    public function deleteItem(): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.hardware.delete');
        Security::checkCsrf();

        $id      = (int)($_POST['id'] ?? 0);
        $setupId = (int)($_POST['setup_id'] ?? 0);

        if ($id <= 0) {
            Response::error(400);
        }

        Database::execute("DELETE FROM hardware_items WHERE id = ?", [$id]);

        $_SESSION['flash_success'] = 'Item wurde gelöscht.';
        Response::redirect('/admin/hardware/' . $setupId . '/items');
    }

    /* =====================================================
       BILDERGALERIE
    ===================================================== */

    public function images(int $setupId): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.hardware.view');

        $setup = Database::fetch(
            "SELECT * FROM hardware_setups WHERE id = ?",
            [$setupId]
        );

        if (!$setup) {
            Response::error(404);
        }

        $images = Database::fetchAll(
            "SELECT * FROM hardware_images 
             WHERE setup_id = ? 
             ORDER BY sort ASC, id DESC",
            [$setupId]
        ) ?? [];

        View::render('admin/hardware/images', [
            'title'  => 'Bildergalerie – ' . ($setup['title'] ?? 'Setup'),
            'setup'  => $setup,
            'images' => $images
        ]);
    }

    public function uploadImage(): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.hardware.upload');
        Security::checkCsrf();

        $setupId = (int)($_POST['setup_id'] ?? 0);

        if ($setupId <= 0) {
            $_SESSION['flash_error'] = 'Ungültige Setup-ID.';
            Response::redirect('/admin/hardware');
        }

        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'Keine Datei hochgeladen oder Upload-Fehler.';
            Response::redirect("/admin/hardware/{$setupId}/images");
        }

        $file = $_FILES['image'];

        // Validierung
        if ($file['size'] > self::MAX_FILE_SIZE) {
            $_SESSION['flash_error'] = 'Datei zu groß (max. 5MB).';
            Response::redirect("/admin/hardware/{$setupId}/images");
        }

        if (!in_array($file['type'], self::ALLOWED_TYPES)) {
            $_SESSION['flash_error'] = 'Ungültiger Dateityp. Nur Bilder erlaubt.';
            Response::redirect("/admin/hardware/{$setupId}/images");
        }

        // Dateiname generieren
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename  = 'hardware_' . $setupId . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . self::UPLOAD_DIR;

        // Verzeichnis erstellen falls nicht vorhanden
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filepath = $uploadDir . $filename;

        // Datei verschieben
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            $_SESSION['flash_error'] = 'Fehler beim Hochladen der Datei.';
            Response::redirect("/admin/hardware/{$setupId}/images");
        }

        // In Datenbank speichern
        $sort = (int)($_POST['sort'] ?? 0);
        $caption = trim($_POST['caption'] ?? '');

        Database::execute(
            "INSERT INTO hardware_images (setup_id, filename, caption, sort, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$setupId, $filename, $caption, $sort]
        );

        $_SESSION['flash_success'] = 'Bild wurde hochgeladen.';
        Response::redirect("/admin/hardware/{$setupId}/images");
    }

    public function updateImageCaption(): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.hardware.edit');
        Security::checkCsrf();

        $id      = (int)($_POST['id'] ?? 0);
        $caption = trim($_POST['caption'] ?? '');
        $sort    = (int)($_POST['sort'] ?? 0);
        $setupId = (int)($_POST['setup_id'] ?? 0);

        if ($id <= 0) {
            Response::error(400);
        }

        Database::execute(
            "UPDATE hardware_images SET caption = ?, sort = ?, updated_at = NOW() WHERE id = ?",
            [$caption, $sort, $id]
        );

        $_SESSION['flash_success'] = 'Bild wurde aktualisiert.';
        Response::redirect("/admin/hardware/{$setupId}/images");
    }

    public function deleteImage(): void
    {
        Security::requireLogin();
        Security::requirePermission('admin.hardware.delete');
        Security::checkCsrf();

        $id      = (int)($_POST['id'] ?? 0);
        $setupId = (int)($_POST['setup_id'] ?? 0);

        if ($id <= 0) {
            Response::error(400);
        }

        $image = Database::fetch(
            "SELECT * FROM hardware_images WHERE id = ?",
            [$id]
        );

        if ($image) {
            $this->deleteImageFile($image['filename']);
            Database::execute("DELETE FROM hardware_images WHERE id = ?", [$id]);
        }

        $_SESSION['flash_success'] = 'Bild wurde gelöscht.';
        Response::redirect("/admin/hardware/{$setupId}/images");
    }

    /* =====================================================
       HELPER METHODS
    ===================================================== */

    private function generateSlug(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    private function deleteImageFile(string $filename): void
    {
        $filepath = $_SERVER['DOCUMENT_ROOT'] . self::UPLOAD_DIR . $filename;
        if (file_exists($filepath)) {
            @unlink($filepath);
        }
    }
}