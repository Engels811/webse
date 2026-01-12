<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Database;

final class PartnerController
{
    public function index(): void
    {
        $partners = Database::fetchAll(
            "SELECT
                id,
                name,
                description,
                url,
                logo,
                is_featured
             FROM partners
             WHERE is_active = 1
               AND deleted_at IS NULL
             ORDER BY
                is_featured DESC,
                name ASC"
        ) ?? [];

        View::render('partner/index', [
            'title'    => 'Unsere Partner',
            'partners' => $partners,
        ]);
    }
}
