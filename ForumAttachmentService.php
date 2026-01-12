<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
final class ForumAttachmentService
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif'
    ];

    private const MAX_FILE_SIZE = 5_000_000; // 5 MB

    /* =========================
       UPLOAD HANDLER
    ========================= */
    public static function handleUpload(int $postId): void
    {
        if (
            empty($_FILES['attachments']) ||
            !is_array($_FILES['attachments']['tmp_name'])
        ) {
            return;
        }

        $uploadDir = PUBLIC_PATH . '/uploads/forum/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);

            // Optional: Verzeichnis absichern
            @file_put_contents($uploadDir . 'index.html', '');
        }

        foreach ($_FILES['attachments']['tmp_name'] as $index => $tmpName) {
            if (empty($tmpName) || !is_uploaded_file($tmpName)) {
                continue;
            }

            $originalName = $_FILES['attachments']['name'][$index] ?? '';
            $size         = (int) ($_FILES['attachments']['size'][$index] ?? 0);
            $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                continue;
            }

            if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
                continue;
            }

            $mime = mime_content_type($tmpName);
            if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
                continue;
            }

            $fileName = uniqid('forum_', true) . '.' . $ext;
            $target   = $uploadDir . $fileName;

            if (!move_uploaded_file($tmpName, $target)) {
                continue;
            }

            // Sicherheitsmaßnahme: Dateirechte
            chmod($target, 0644);

            Database::execute(
                'INSERT INTO forum_attachments (post_id, file_path)
                 VALUES (?, ?)',
                [$postId, '/uploads/forum/' . $fileName]
            );
        }
    }

    /* =========================
       SINGLE ATTACHMENT DELETE
    ========================= */
    public static function deleteSingle(int $attachmentId): void
    {
        $file = Database::fetch(
            'SELECT file_path FROM forum_attachments WHERE id = ?',
            [$attachmentId]
        );

        if (!$file || empty($file['file_path'])) {
            return;
        }

        $diskPath = PUBLIC_PATH . $file['file_path'];

        if (is_file($diskPath)) {
            unlink($diskPath);
        }

        Database::execute(
            'DELETE FROM forum_attachments WHERE id = ?',
            [$attachmentId]
        );
    }

    /* =========================
       DELETE ALL BY POST
    ========================= */
    public static function deleteByPost(int $postId): void
    {
        $files = Database::fetchAll(
            'SELECT id FROM forum_attachments WHERE post_id = ?',
            [$postId]
        );

        foreach ($files as $file) {
            self::deleteSingle((int) $file['id']);
        }
    }
}
