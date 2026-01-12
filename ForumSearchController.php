<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

final class ForumSearchController
{
    public function index(): void
    {
        $query = trim($_GET['q'] ?? '');

        /* =========================
           VALIDATION
        ========================= */
        if ($query === '' || mb_strlen($query) < 3) {

            View::renderLayout(
                'layouts/forum.layout',
                'forum/search',
                [
                    'title'   => 'Forum-Suche',
                    'query'   => $query,
                    'results' => [],
                    'error'   => 'Bitte mindestens 3 Zeichen eingeben.'
                ]
            );

            return;
        }

        /* =========================
           FLOOD PROTECTION
        ========================= */
        Flood::check('forum_search', 5);

        $like = '%' . $query . '%';

        /* =========================
           SEARCH QUERY
        ========================= */
        $results = Database::fetchAll(
            'SELECT 
                p.id           AS post_id,
                p.content,
                t.id           AS thread_id,
                t.title        AS thread_title,
                c.slug         AS category_slug,
                u.username,
                p.created_at
             FROM forum_posts p
             JOIN forum_threads t ON t.id = p.thread_id
             JOIN forum_categories c ON c.id = t.category_id
             JOIN users u ON u.id = p.user_id
             WHERE p.is_deleted = 0
               AND (
                   p.content LIKE ?
                   OR t.title LIKE ?
               )
             ORDER BY p.created_at DESC
             LIMIT 50',
            [$like, $like]
        );

        /* =========================
           EXCERPTS + HIGHLIGHT
        ========================= */
        foreach ($results as &$row) {
            $row['excerpt'] = $this->highlight(
                mb_strimwidth(strip_tags($row['content']), 0, 240, '…'),
                $query
            );
        }

        /* =========================
           RENDER (FORUM LAYOUT)
        ========================= */
        View::renderLayout(
            'layouts/forum.layout',
            'forum/search',
            [
                'title'   => 'Suchergebnisse für „' . htmlspecialchars($query) . '“',
                'query'   => $query,
                'results' => $results
            ]
        );
    }

    /* =========================
       TERM HIGHLIGHT
    ========================= */
    private function highlight(string $text, string $term): string
    {
        return preg_replace(
            '/' . preg_quote($term, '/') . '/i',
            '<mark>$0</mark>',
            htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        );
    }
}
