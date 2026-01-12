<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

final class HardwareUploadController
{
    public function upload(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $dir = BASE_PATH . '/public/uploads/setup/';
        @mkdir($dir, 0777, true);

        foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
            if (!$tmp) continue;

            $name = uniqid('setup_') . '.jpg';
            move_uploaded_file($tmp, $dir . $name);
        }

        header('Location: /hardware');
        exit;
    }
}
