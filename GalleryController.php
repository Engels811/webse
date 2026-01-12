<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\View;
use App\Core\Response;
use PDO;
use Exception;

final class GalleryController
{
    private string $uploadPath;

    private array $allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    private int $maxFileSize = 10485760; // 10 MB

    public function __construct()
    {
        $this->uploadPath = BASE_PATH . '/public/uploads/gallery/';

        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }

    /* =====================================================
       UPLOAD
    ===================================================== */
    public function upload(): void
    {
        $this->startSession();

        if (empty($_SESSION['user'])) {
            $this->redirectWithError('/login', 'Du musst eingeloggt sein.');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirectWithError('/galerie', 'Ungültige Anfrage.');
        }

        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $this->redirectWithError('/galerie', 'Fehler beim Upload.');
        }

        $section = $_POST['section'] ?? 'community';
        $title   = trim($_POST['title'] ?? '');
        $userId  = (int)$_SESSION['user']['id'];

        if ($title === '') {
            $this->redirectWithError("/galerie/$section", 'Titel fehlt.');
        }

        $validSections = ['community', 'artwork', 'bts'];
        if (!in_array($section, $validSections, true)) {
            $section = 'community';
        }

        $file = $_FILES['image'];

        if ($file['size'] > $this->maxFileSize) {
            $this->redirectWithError("/galerie/$section", 'Datei zu groß (max. 10 MB).');
        }

        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $this->allowedTypes, true)) {
            $this->redirectWithError("/galerie/$section", 'Ungültiger Dateityp.');
        }

        $targetDir = $this->uploadPath . $section . '/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $filepath = $targetDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            $this->redirectWithError("/galerie/$section", 'Datei konnte nicht gespeichert werden.');
        }

        try {
            $stmt = Database::prepare("
                INSERT INTO gallery_media (user_id, section, title, file, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $section, $title, $filename]);

            $_SESSION['success'] = 'Bild erfolgreich hochgeladen.';
        } catch (Exception $e) {
            unlink($filepath);
            error_log('Gallery Upload Error: ' . $e->getMessage());
            $this->redirectWithError("/galerie/$section", 'Datenbankfehler.');
        }

        header("Location: /galerie/$section");
        exit;
    }

    /* =====================================================
       DELETE (USER)
    ===================================================== */
    public function delete(): void
    {
        $this->startSession();

        if (empty($_SESSION['user'])) {
            $this->redirectWithError('/login', 'Nicht autorisiert.');
        }

        $imageId = (int)($_POST['id'] ?? 0);
        $userId  = (int)$_SESSION['user']['id'];

        $stmt = Database::prepare("
            SELECT * FROM gallery_media
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$imageId, $userId]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$image) {
            $this->redirectWithError('/galerie', 'Bild nicht gefunden.');
        }

        $file = $this->uploadPath . $image['section'] . '/' . $image['file'];
        if (is_file($file)) {
            unlink($file);
        }

        Database::prepare("DELETE FROM gallery_media WHERE id = ?")
            ->execute([$imageId]);

        $_SESSION['success'] = 'Bild gelöscht.';
        header('Location: /galerie/' . $image['section']);
        exit;
    }

    /* =====================================================
       ADMIN DELETE
    ===================================================== */
    public function adminDelete(): void
    {
        $this->startSession();

        if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            Response::error(403);
            return;
        }

        $imageId = (int)($_POST['id'] ?? 0);

        $stmt = Database::prepare("SELECT * FROM gallery_media WHERE id = ?");
        $stmt->execute([$imageId]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$image) {
            $this->redirectWithError('/admin/galerie', 'Bild nicht gefunden.');
        }

        $file = $this->uploadPath . $image['section'] . '/' . $image['file'];
        if (is_file($file)) {
            unlink($file);
        }

        Database::prepare("DELETE FROM gallery_media WHERE id = ?")
            ->execute([$imageId]);

        $_SESSION['success'] = 'Bild gelöscht.';
        header('Location: /admin/galerie');
        exit;
    }

    /* =====================================================
       ADMIN VISIBILITY (AJAX)
    ===================================================== */
    public function toggleVisibility(): void
    {
        $this->startSession();
        header('Content-Type: application/json');

        if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            echo json_encode(['success' => false]);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($data['id'] ?? 0);
        $vis  = (int)($data['visible'] ?? 1);

        Database::prepare("
            UPDATE gallery_media SET is_visible = ? WHERE id = ?
        ")->execute([$vis, $id]);

        echo json_encode(['success' => true, 'visible' => $vis]);
        exit;
    }

    /* =====================================================
       GET IMAGES FOR SECTION (PUBLIC)
    ===================================================== */
    public function getImagesForSection(string $section): array
    {
        $stmt = Database::prepare("
            SELECT 
                gm.*,
                u.username
            FROM gallery_media gm
            LEFT JOIN users u ON gm.user_id = u.id
            WHERE gm.section = ?
              AND (gm.is_visible = 1 OR gm.is_visible IS NULL)
            ORDER BY gm.created_at DESC
        ");
        $stmt->execute([$section]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =====================================================
       GET ALL IMAGES FOR ADMIN
    ===================================================== */
    public function getAllImagesForAdmin(): array
    {
        $stmt = Database::prepare("
            SELECT 
                gm.*,
                u.username
            FROM gallery_media gm
            LEFT JOIN users u ON gm.user_id = u.id
            ORDER BY gm.created_at DESC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =====================================================
       HELPERS
    ===================================================== */
    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    private function redirectWithError(string $url, string $message): void
    {
        $_SESSION['error'] = $message;
        header("Location: $url");
        exit;
    }
}