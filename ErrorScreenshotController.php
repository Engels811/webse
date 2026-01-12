<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\TicketAttachment;

final class ErrorScreenshotController
{
    public function store(): void
    {
        header('Content-Type: application/json');

        /* =========================================
           VALIDIERUNG
        ========================================= */
        if (
            empty($_FILES['screenshot']) ||
            $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK
        ) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'Kein Screenshot empfangen'
            ]);
            return;
        }

        /* =========================================
           SCREENSHOT OHNE TICKET SPEICHERN
           (ABSICHTLICH!)
        ========================================= */
        try {
            TicketAttachment::storeErrorScreenshot(
                $_FILES['screenshot'],
                $_POST['error_id'] ?? null
            );

            http_response_code(200);
            echo json_encode([
                'success' => true
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => $e->getMessage()
            ]);
        }
    }
}
