<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Database;

/**
 * ENGELS811 NETWORK - Hardware Controller (PUBLIC)
 * 
 * Features:
 * - Hardware-Setups anzeigen
 * - Items gruppiert nach Kategorien
 * - Bildergalerie über der Überschrift
 */
final class HardwareController
{
    public function index(?string $slug = null): void
    {
        // Setup ermitteln
        if ($slug) {
            $setup = Database::fetch(
                "SELECT * FROM hardware_setups WHERE slug = ? AND is_active = 1",
                [$slug]
            );
        } else {
            $setup = Database::fetch(
                "SELECT * FROM hardware_setups WHERE is_active = 1 ORDER BY id ASC LIMIT 1"
            );
        }

        if (!$setup) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Hardware-Setup nicht gefunden']);
            return;
        }

        // Alle aktiven Setups (für Switcher)
        $setups = Database::fetchAll(
            "SELECT * FROM hardware_setups WHERE is_active = 1 ORDER BY id ASC"
        ) ?? [];

        // Hardware-Items laden
        $items = Database::fetchAll(
            "SELECT *
             FROM hardware_items
             WHERE setup_id = ?
             ORDER BY category ASC, sort ASC, position ASC",
            [$setup['id']]
        ) ?? [];

        // Bildergalerie laden
        $images = Database::fetchAll(
            "SELECT * FROM hardware_images 
             WHERE setup_id = ? 
             ORDER BY sort ASC, id ASC",
            [$setup['id']]
        ) ?? [];

        // Items nach Kategorien gruppieren
        $hardware = [
            'pc'               => [],
            'monitors'         => [],
            'audio'            => [],
            'camera_lighting'  => [],
            'extras'           => [],
        ];

        foreach ($items as $item) {
            $cat = $item['category'] ?? 'extras';
            
            switch ($cat) {
                case 'pc':
                    $hardware['pc'][] = $item;
                    break;
                case 'monitors':
                    $hardware['monitors'][] = $item;
                    break;
                case 'audio':
                    $hardware['audio'][] = $item;
                    break;
                case 'camera':
                case 'camera_lighting':
                    $hardware['camera_lighting'][] = $item;
                    break;
                default:
                    $hardware['extras'][] = $item;
                    break;
            }
        }

        // View rendern
        View::render('hardware/index', [
            'title'    => 'Hardware – ' . $setup['title'],
            'setup'    => $setup,
            'setups'   => $setups,
            'hardware' => $hardware,
            'images'   => $images
        ]);
    }
}