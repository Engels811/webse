<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\GalleryModel;
use App\Core\View;
use App\Core\Security;

final class AdminGalleryController
{
    private GalleryModel $model;

    public function __construct()
    {
        $this->model = new GalleryModel();
    }

    /**
     * Admin-Galerie anzeigen
     */
    public function index(): void
    {
        Security::requireAdmin();

        $images = $this->model->getAllImagesForAdmin();

        View::render('admin/gallery/index', [
            'title'  => 'Admin – Galerie',
            'images' => $images
        ]);
    }

    /**
     * Sichtbarkeit togglen (AJAX)
     */
    public function toggleVisibility(): void
    {
        Security::requireAdmin();
        Security::checkCsrf();

        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($data['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ungültige ID']);
            return;
        }

        $this->model->toggleVisibility($id);

        echo json_encode(['success' => true]);
    }
}
