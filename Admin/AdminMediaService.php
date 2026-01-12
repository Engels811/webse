<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;

final class AdminMediaService
{
    public function stats(): array
    {
        return [
            'total' => $this->safeCount('media_videos'),
            'pending' => $this->safeCount('media_videos', "status = 'pending'"),
            'approved' => $this->safeCount('media_videos', "status = 'approved'"),
            'rejected' => $this->safeCount('media_videos', "status = 'rejected'"),
            'uploads' => $this->safeCount('media_videos', "source_type = 'upload'"),
            'twitch_vods' => $this->safeCount('media_videos', "source_type = 'twitch_vod'"),
        ];
    }

    public function pending(): array
    {
        try {
            return Database::fetchAll("
                SELECT 
                    mv.*,
                    u.username,
                    u.avatar
                FROM media_videos mv
                LEFT JOIN users u ON u.id = mv.user_id
                WHERE mv.status = 'pending'
                ORDER BY mv.created_at DESC
            ") ?? [];
        } catch (\Throwable $e) {
            error_log("AdminMediaService::pending: " . $e->getMessage());
            return [];
        }
    }

    public function all(): array
    {
        try {
            return Database::fetchAll("
                SELECT 
                    mv.*,
                    u.username,
                    u.avatar
                FROM media_videos mv
                LEFT JOIN users u ON u.id = mv.user_id
                ORDER BY mv.created_at DESC
                LIMIT 100
            ") ?? [];
        } catch (\Throwable $e) {
            error_log("AdminMediaService::all: " . $e->getMessage());
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
}
