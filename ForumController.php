<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Database;
use App\Core\Security;
use App\Services\ForumAttachmentService;

final class ForumController
{
    /* =========================
       FORUM STARTSEITE
    ========================= */
    public function index(): void
    {
        $categories = Database::fetchAll(
            'SELECT * FROM forum_categories ORDER BY title'
        );

        View::render('forum/index', [
            'title'       => 'Forum',
            'currentPage' => 'forum',
            'categories'  => $categories
        ]);
    }

    /* =========================
       KATEGORIE
    ========================= */
    public function category(string $slug): void
    {
        $category = Database::fetch(
            'SELECT * FROM forum_categories WHERE slug = ?',
            [$slug]
        );

        if (!$category) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Kategorie nicht gefunden']);
            return;
        }

        $threads = Database::fetchAll(
            'SELECT t.*, u.username
             FROM forum_threads t
             JOIN users u ON u.id = t.user_id
             WHERE t.category_id = ?
             ORDER BY t.is_sticky DESC, t.created_at DESC',
            [$category['id']]
        );

        View::render('forum/category', [
            'title'       => $category['title'],
            'currentPage' => 'forum',
            'category'    => $category,
            'threads'     => $threads
        ]);
    }

    /* =========================
       THREAD ANZEIGEN
    ========================= */
    public function thread(int $id): void
    {
        $thread = Database::fetch(
            'SELECT t.*, u.username,
                    c.title AS category_title,
                    c.slug  AS category_slug
             FROM forum_threads t
             JOIN users u ON u.id = t.user_id
             JOIN forum_categories c ON c.id = t.category_id
             WHERE t.id = ?',
            [$id]
        );

        if (!$thread) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Thread nicht gefunden']);
            return;
        }

        $posts = Database::fetchAll(
            'SELECT p.*, u.username, u.avatar
             FROM forum_posts p
             JOIN users u ON u.id = p.user_id
             WHERE p.thread_id = ?
               AND p.is_deleted = 0
             ORDER BY p.created_at ASC',
            [$id]
        );

        foreach ($posts as &$post) {
            $post['attachments'] = Database::fetchAll(
                'SELECT * FROM forum_attachments WHERE post_id = ?',
                [$post['id']]
            );
        }
        unset($post);

        View::render('forum/thread', [
            'title'       => $thread['title'],
            'currentPage' => 'forum',
            'thread'      => $thread,
            'posts'       => $posts
        ]);
    }

    /* =========================
       THEMA SPEICHERN
    ========================= */
    public function storeThread(string $slug): void
    {
        Security::requireLogin();
        Security::checkCsrf();

        $category = Database::fetch(
            'SELECT * FROM forum_categories WHERE slug = ?',
            [$slug]
        );

        if (!$category) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if ($title === '' || $content === '') {
            $_SESSION['forum_error'] = 'Bitte Titel und Beitrag ausfüllen.';
            $_SESSION['forum_old'] = compact('title', 'content');
            header("Location: /forum/{$slug}");
            exit;
        }

        Database::execute(
            'INSERT INTO forum_threads (category_id, user_id, title)
             VALUES (?, ?, ?)',
            [$category['id'], $_SESSION['user']['id'], $title]
        );

        $threadId = Database::lastInsertId();

        Database::execute(
            'INSERT INTO forum_posts (thread_id, user_id, content)
             VALUES (?, ?, ?)',
            [$threadId, $_SESSION['user']['id'], $content]
        );

        $postId = Database::lastInsertId();

        ForumAttachmentService::handleUpload($postId);

        header("Location: /forum/thread/{$threadId}");
        exit;
    }

    /* =========================
       ANTWORTEN
    ========================= */
    public function reply(int $threadId): void
    {
        Security::requireLogin();
        Security::checkCsrf();

        $thread = Database::fetch(
            'SELECT is_locked FROM forum_threads WHERE id = ?',
            [$threadId]
        );

        if (!$thread || $thread['is_locked']) {
            header("Location: /forum/thread/{$threadId}");
            exit;
        }

        $content = trim($_POST['content'] ?? '');
        if ($content === '') {
            header("Location: /forum/thread/{$threadId}");
            exit;
        }

        Database::execute(
            'INSERT INTO forum_posts (thread_id, user_id, content)
             VALUES (?, ?, ?)',
            [$threadId, $_SESSION['user']['id'], $content]
        );

        $postId = Database::lastInsertId();
        ForumAttachmentService::handleUpload($postId);

        Database::execute(
            'UPDATE forum_threads SET updated_at = NOW() WHERE id = ?',
            [$threadId]
        );

        header("Location: /forum/thread/{$threadId}");
        exit;
    }

    /* =========================
       THREAD ERSTELLEN (FORMULAR)
    ========================= */
    public function createThread(string $slug): void
    {
        $category = Database::fetch(
            'SELECT * FROM forum_categories WHERE slug = ?',
            [$slug]
        );

        if (!$category) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        View::render('forum/create', [
            'title'       => 'Neues Thema',
            'currentPage' => 'forum',
            'category'    => $category
        ]);
    }
}
