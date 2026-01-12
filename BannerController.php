<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Banner;

final class BannerController
{
    /**
     * Gibt Banner für eine Position zurück (JSON oder View)
     * z.B. /banner/render?pos=top
     */
    public function render(): void
    {
        $position = $_GET['pos'] ?? 'top';

        $banners = Banner::frontend($position);

        header('Content-Type: application/json');
        echo json_encode($banners);
    }
}
