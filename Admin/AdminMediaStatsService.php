<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;

final class AdminMediaStatsService
{
    public function overview(): array
    {
        return [
            'total_videos' => $this->safeCount('media_videos'),
            'total_views' => $this->safeSumColumn('media_videos', 'view_count'),
            'total_likes' => $this->safeSumColumn('media_videos', 'like_count'),
            'total_comments' => $this->safeCount('comments'),
            'pending_approval' => $this->safeCount('media_videos', "status = 'pending'"),
            'approved_today' => $this->safeCount('media_videos', "status = 'approved' AND DATE(approved_at) = CURDATE()"),
        ];
    }

    public function topVideos(int $limit = 10): array
    {
        try {
            return Database::fetchAll("
                SELECT 
                    mv.id,
                    mv.title,
                    mv.view_count,
                    mv.like_count,
                    mv.created_at,
                    u.username
                FROM media_videos mv
                LEFT JOIN users u ON u.id = mv.user_id
                WHERE mv.status = 'approved'
                ORDER BY mv.view_count DESC
                LIMIT ?
            ", [$limit]) ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function safeCount(string $table, ?string $where = null): int
    {
        try {
            $sql = "SELECT COUNT(*) AS c FROM `{$table}`";
            if ($where) {
                $sql .= " WHERE {$where}";
            }
            $result = Database::fetch($sql);
            return $result ? (int)($result['c'] ?? 0) : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function safeSumColumn(string $table, string $column): int
    {
        try {
            $sql = "SELECT COALESCE(SUM(`{$column}`), 0) AS total FROM `{$table}`";
            $result = Database::fetch($sql);
            return $result ? (int)($result['total'] ?? 0) : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
