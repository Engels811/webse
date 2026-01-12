<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Database;

final class AgbController
{
    public function index(): void
    {
        $rows = Database::fetchAll(
            "SELECT section_key, title, content
             FROM agb_sections
             ORDER BY id ASC"
        );

        $agb = [];
        foreach ($rows as $row) {
            $agb[$row['section_key']] = $row;
        }

        View::render('agb/index', [
            'title'   => 'AGB',
            'agb'     => $agb,
            'version' => '1.0',
            'stand'   => date('d.m.Y')
        ]);
    }
}

