<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

final class ReactionController
{
    public function toggle(): void
    {
        if (empty($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['error' => 'unauthorized']);
            return;
        }

        Security::checkCsrf();
        Flood::check('react_' . $_SESSION['user']['id'], 2);

        $postId   = (int)($_POST['post_id'] ?? 0);
        $reaction = $_POST['reaction'] ?? '';

        if (!in_array($reaction, ['like', 'heart', 'fire'], true) || $postId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid']);
            return;
        }

        // Existiert Reaktion?
        $exists = Database::fetch(
            'SELECT id FROM forum_reactions
             WHERE post_id = ? AND user_id = ? AND reaction = ?',
            [$postId, $_SESSION['user']['id'], $reaction]
        );

        if ($exists) {
            // Entfernen
            Database::execute(
                'DELETE FROM forum_reactions WHERE id = ?',
                [$exists['id']]
            );
        } else {
            // Hinzufügen
            Database::execute(
                'INSERT INTO forum_reactions (post_id, user_id, reaction)
                 VALUES (?, ?, ?)',
                [$postId, $_SESSION['user']['id'], $reaction]
            );
        }

        // Neue Zähler laden
        $counts = Database::fetchAll(
            'SELECT reaction, COUNT(*) AS cnt
             FROM forum_reactions
             WHERE post_id = ?
             GROUP BY reaction',
            [$postId]
        );

        $response = [
            'like'  => 0,
            'heart' => 0,
            'fire'  => 0
        ];

        foreach ($counts as $c) {
            $response[$c['reaction']] = (int)$c['cnt'];
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    }
}
