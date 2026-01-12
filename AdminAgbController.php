<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Security;
use App\Core\Database;
use App\Core\Response;

final class AdminAgbController
{
    public function consents(): void
    {
        Security::requireTeam();

        try {
            $consents = Database::fetchAll("
                SELECT 
                    u.id,
                    u.username,
                    u.email,
                    u.avatar,
                    u.agb_version,
                    u.agb_accepted_at,
                    u.last_ip
                FROM users u
                WHERE u.agb_accepted_at IS NOT NULL
                ORDER BY u.agb_accepted_at DESC
            ");

            $formattedConsents = [];
            foreach ($consents as $consent) {
                $formattedConsents[] = [
                    'id'          => $consent['id'],
                    'username'    => $consent['username'],
                    'email'       => $consent['email'] ?? null,
                    'avatar'      => $consent['avatar'] ?? null,
                    'version'     => $consent['agb_version'] ?? '1.0',
                    'accepted_at' => $consent['agb_accepted_at'],
                    'ip_address'  => $consent['last_ip'] ?? null
                ];
            }

            View::render('admin/agb_consents', [
                'title'       => 'AGB-Zustimmungen',
                'currentPage' => 'admin',
                'consents'    => $formattedConsents
            ]);
        } catch (\Throwable $e) {
            error_log("AdminAgbController::consents: " . $e->getMessage());
            View::render('admin/agb_consents', [
                'title'       => 'AGB-Zustimmungen',
                'currentPage' => 'admin',
                'consents'    => [],
                'error'       => 'Fehler beim Laden'
            ]);
        }
    }
}
