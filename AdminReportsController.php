<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Security;
use App\Core\Database;

final class AdminReportsController
{
    public function index(): void
    {
        Security::requireTeam();

        $status = $_GET['status'] ?? 'open';

        $where = $status === 'open'
            ? "WHERE r.status = 'open'"
            : "WHERE r.status != 'open'";

        $reports = Database::fetchAll(
            "SELECT
                r.*,
                u.username AS reporter_username
             FROM reports r
             LEFT JOIN users u ON u.id = r.reporter_id
             {$where}
             ORDER BY r.created_at DESC
             LIMIT 200"
        ) ?? [];

        View::render('admin/reports/index', [
            'title'   => 'Reports',
            'reports' => $reports,
            'status'  => $status
        ]);
    }

    public function view(int $id): void
    {
        Security::requireTeam();

        $report = Database::fetch(
            "SELECT
                r.*,
                u1.username AS reporter_username,
                u2.username AS reported_username
             FROM reports r
             LEFT JOIN users u1 ON u1.id = r.reporter_id
             LEFT JOIN users u2 ON u2.id = r.reported_user_id
             WHERE r.id = ?",
            [$id]
        );

        if (!$report) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        View::render('admin/reports/view', [
            'title'  => 'Report Details',
            'report' => $report
        ]);
    }

    public function resolve(): void
    {
        Security::requireModerator();
        Security::checkCsrf();

        $id     = (int)($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? '';
        $notes  = trim($_POST['notes'] ?? '');

        if ($id <= 0 || !in_array($action, ['approved', 'rejected'], true)) {
            http_response_code(400);
            exit;
        }

        Database::execute(
            "UPDATE reports
             SET status = ?, resolved_by = ?, resolved_at = NOW(), admin_notes = ?
             WHERE id = ?",
            [$action, $_SESSION['user']['username'], $notes, $id]
        );

        $_SESSION['flash_success'] = 'Report wurde bearbeitet.';
        header('Location: /admin/reports');
        exit;
    }

    public function delete(): void
    {
        Security::requireModerator();
        Security::checkCsrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            exit;
        }

        Database::execute(
            "DELETE FROM reports WHERE id = ?",
            [$id]
        );

        $_SESSION['flash_success'] = 'Report wurde gelöscht.';
        header('Location: /admin/reports');
        exit;
    }
}
