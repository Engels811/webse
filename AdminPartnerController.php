<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Database;
use App\Core\Security;

final class AdminPartnerController
{
    /* =====================================================
       LISTE
    ===================================================== */
    public function index(): void
    {
        Security::requireAdmin();

        $partners = Database::fetchAll(
            "SELECT *
             FROM partners
             WHERE deleted_at IS NULL
             ORDER BY is_featured DESC, is_active DESC, name ASC"
        ) ?? [];

        View::render('admin/partners/index', [
            'title'    => 'Partnerverwaltung',
            'partners' => $partners,
        ]);
    }

    /* =====================================================
       CREATE FORM
    ===================================================== */
    public function create(): void
    {
        Security::requireAdmin();

        View::render('admin/partners/form', [
            'title'   => 'Partner anlegen',
            'partner' => null,
        ]);
    }

    /* =====================================================
       EDIT FORM (ROUTING-SICHER)
    ===================================================== */
    public function edit(int $id = 0): void
    {
        Security::requireAdmin();

        if ($id <= 0) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Ungültige Partner-ID']);
            return;
        }

        $partner = Database::fetch(
            "SELECT *
             FROM partners
             WHERE id = ? AND deleted_at IS NULL
             LIMIT 1",
            [$id]
        );

        if (!$partner) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Partner nicht gefunden']);
            return;
        }

        View::render('admin/partners/form', [
            'title'   => 'Partner bearbeiten',
            'partner' => $partner,
        ]);
    }

    /* =====================================================
       STORE
    ===================================================== */
    public function store(): void
    {
        Security::requireAdmin();
        Security::checkCsrf();

        Database::execute(
            "INSERT INTO partners (
                name,
                description,
                url,
                logo,
                is_active,
                is_featured,
                created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, NOW()
            )",
            [
                trim($_POST['name'] ?? ''),
                trim($_POST['description'] ?? ''),
                trim($_POST['url'] ?? ''),
                $this->handleLogo(null),
                isset($_POST['is_active']) ? 1 : 0,
                isset($_POST['is_featured']) ? 1 : 0,
            ]
        );

        header('Location: /admin/partners');
        exit;
    }

    /* =====================================================
       UPDATE
    ===================================================== */
    public function update(): void
    {
        Security::requireAdmin();
        Security::checkCsrf();

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Ungültige Partner-ID']);
            return;
        }

        $partner = Database::fetch(
            "SELECT *
             FROM partners
             WHERE id = ? AND deleted_at IS NULL
             LIMIT 1",
            [$id]
        );

        if (!$partner) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Partner nicht gefunden']);
            return;
        }

        Database::execute(
            "UPDATE partners
             SET
                name = ?,
                description = ?,
                url = ?,
                logo = ?,
                is_active = ?,
                is_featured = ?,
                updated_at = NOW()
             WHERE id = ?",
            [
                trim($_POST['name'] ?? ''),
                trim($_POST['description'] ?? ''),
                trim($_POST['url'] ?? ''),
                $this->handleLogo($partner['logo']),
                isset($_POST['is_active']) ? 1 : 0,
                isset($_POST['is_featured']) ? 1 : 0,
                $id,
            ]
        );

        header('Location: /admin/partners');
        exit;
    }

    /* =====================================================
       SOFT DELETE
    ===================================================== */
    public function delete(): void
    {
        Security::requireAdmin();
        Security::checkCsrf();

        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            Database::execute(
                "UPDATE partners
                 SET deleted_at = NOW()
                 WHERE id = ?",
                [$id]
            );
        }

        header('Location: /admin/partners');
        exit;
    }

    /* =====================================================
       AJAX: STATUS TOGGLE (JSON)
    ===================================================== */
    public function toggleStatus(): void
    {
        Security::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        header('Content-Type: application/json; charset=UTF-8');

        $payload = json_decode((string)file_get_contents('php://input'), true);
        $id      = (int)($payload['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid ID']);
            return;
        }

        Database::execute(
            "UPDATE partners
             SET is_active = IF(is_active = 1, 0, 1)
             WHERE id = ? AND deleted_at IS NULL",
            [$id]
        );

        echo json_encode(['success' => true]);
    }

    /* =====================================================
       LOGO HANDLER
    ===================================================== */
    private function handleLogo(?string $existing): ?string
    {
        /* Upload */
        if (
            isset($_FILES['logo_file']['tmp_name']) &&
            is_uploaded_file($_FILES['logo_file']['tmp_name'])
        ) {
            $dir = BASE_PATH . '/public/uploads/partners';

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $ext  = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
            $file = 'partner_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;

            move_uploaded_file(
                $_FILES['logo_file']['tmp_name'],
                $dir . '/' . $file
            );

            return '/uploads/partners/' . $file;
        }

        /* URL */
        if (!empty($_POST['logo_url'])) {
            return trim($_POST['logo_url']);
        }

        return $existing;
    }
}
