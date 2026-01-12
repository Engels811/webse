<?php
/**
 * Admin Sync Controller
 * Speicherort: /httpdocs/app/Controllers/Admin/SyncController.php
 */

namespace App\Controllers\Admin;
use App\Controllers\BaseController;

use App\Services\SteamSync;

class SyncController extends BaseController
{
    private $steamSync;
    
    public function __construct()
    {
        parent::__construct();
        
        // Check Admin-Login
        if (!isset($_SESSION['admin_logged_in'])) {
            header('Location: /admin/login');
            exit;
        }
        
    define('STEAM_API_KEY', '755830B344EE975F9A4378219EC2705D');
    define('STEAM_ID', '76561198413304736');

        
        $this->steamSync = new SteamSync($this->db, STEAM_API_KEY, STEAM_ID);
    }
    
    /**
     * Zeige Sync Dashboard
     */
    public function index()
    {
        $this->steamSync = new SteamSync($this->db, STEAM_API_KEY, STEAM_ID);
        
        $this->view('admin/game/sync/index', [
            'title' => 'Steam Sync',
            'stats' => $stats
        ]);
    }
    
    /**
     * Vollständiger Sync mit Live-Progress
     */
    public function fullSync()
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        
        try {
            $stats = $this->steamSync->sync(function($current, $total, $name) {
                echo "data: " . json_encode([
                    'type' => 'progress',
                    'progress' => round(($current / $total) * 100),
                    'current' => $current,
                    'total' => $total,
                    'game' => $name
                ]) . "\n\n";
                
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            });
            
            echo "data: " . json_encode([
                'type' => 'complete',
                'stats' => $stats
            ]) . "\n\n";
            
        } catch (Exception $e) {
            echo "data: " . json_encode([
                'type' => 'error',
                'message' => $e->getMessage()
            ]) . "\n\n";
        }
        
        exit;
    }
    
    /**
     * Quick Sync (nur Spielzeiten)
     */
    public function quickSync()
    {
        header('Content-Type: application/json');
        
        try {
            $stats = $this->steamSync->quickSync();
            echo json_encode(['success' => true, 'stats' => $stats]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        
        exit;
    }
}