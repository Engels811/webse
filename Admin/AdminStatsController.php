<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Security;
use App\Core\Database;

final class AdminStatsController
{
    public function index(): void
    {
        Security::requirePermission('stats.view');

        $stats = [
            'users_total' => $this->safeCount('users'),
            'users_active_30d' => $this->safeCount('users', "last_seen > (NOW() - INTERVAL 30 DAY)"),
            'content_total' => $this->safeCount('media_videos') + $this->safeCount('forum_threads'),
            'engagement_total' => $this->safeCount('comments') + $this->safeCount('reactions'),
        ];

        View::render('admin/stats/index', [
            'title' => 'Statistiken',
            'stats' => $stats
        ]);
    }

    public function content(): void
    {
        Security::requirePermission('stats.view');

        $stats = [
            'videos' => $this->safeCount('media_videos'),
            'forum_threads' => $this->safeCount('forum_threads'),
            'forum_posts' => $this->safeCount('forum_posts'),
            'gallery_items' => $this->safeCount('gallery_media'),
            'comments' => $this->safeCount('comments'),
        ];

        View::render('admin/stats/content', [
            'title' => 'Content Statistiken',
            'stats' => $stats
        ]);
    }

    private function safeCount(string $table, ?string $where = null): int
    {
        try {
            $sql = "SELECT COUNT(*) AS c FROM `{$table}`";
            if ($where) {
                $sql .= " WHERE {$where}";
            }
            $pdo = Database::get();
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ? (int)($result['c'] ?? 0) : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
