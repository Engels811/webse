<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

final class CmsController
{
    public function show(string $page): void
    {
        $allowed = ['impressum', 'datenschutz', 'agb', 'about'];

        if (!in_array($page, $allowed, true)) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Seite nicht gefunden']);
            return;
        }

        View::render('cms/' . $page, [
            'title' => ucfirst($page)
        ]);
    }
}
