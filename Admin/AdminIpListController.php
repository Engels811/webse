<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Database;
use App\Core\Response;

final class AdminIpListController
{
    public function index(): void
    {
        Security::requirePermission('security.ip_lists.manage');

        try {
            $blocks = Database::fetchAll("
                SELECT 
                    ib.*,
                    u.username as blocked_by_name
                FROM ip_blocks ib
                LEFT JOIN users u ON u.id = ib.blocked_by
                ORDER BY ib.created_at DESC
            ") ?? [];
        } catch (\Throwable $e) {
            $blocks = [];
        }

        View::render('admin/security/ip_lists', [
            'title' => 'IP-Listen',
            'blocks' => $blocks
        ]);
    }

    public function add(): void
    {
        Security::requirePermission('security.ip_lists.manage');
        Security::checkCsrf();

        $ip = $_POST['ip_address'] ?? '';
        $reason = $_POST['reason'] ?? '';

        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            Response::error(400);
            return;
        }

        try {
            Database::execute("
                INSERT INTO ip_blocks (ip_address, reason, blocked_by, is_active)
                VALUES (?, ?, ?, 1)
            ", [$ip, $reason, Security::userId()]);
        } catch (\Throwable $e) {
            error_log("AdminIpListController::add: " . $e->getMessage());
        }

        Response::redirect('/admin/security/ip-lists');
    }

    public function remove(): void
    {
        Security::requirePermission('security.ip_lists.manage');
        Security::checkCsrf();

        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            try {
                Database::execute("DELETE FROM ip_blocks WHERE id = ?", [$id]);
            } catch (\Throwable $e) {
                error_log("AdminIpListController::remove: " . $e->getMessage());
            }
        }

        Response::redirect('/admin/security/ip-lists');
    }
}
