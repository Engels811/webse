<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Response;
use App\Models\Api;
use App\Models\ApiKey;

class ApiController
{
    /**
     * API-Übersicht mit integriertem Monitoring
     */
    public function index(): void
    {
        // APIs laden
        $apis = Api::getAll();
        
        // Statistiken berechnen
        $totalApis = count($apis);
        $activeApis = count(array_filter($apis, fn($a) => $a['is_active']));
        $inactiveApis = $totalApis - $activeApis;

        // Berechnung der fehlenden totalKeys
        $totalKeys = (int) \App\Core\Database::fetchColumn(
            "SELECT COUNT(*) FROM api_keys"
        );

        // Berechnung von totalTasks (Anzahl der geplanten Aufgaben)
        $totalTasks = (int) \App\Core\Database::fetchColumn(
            "SELECT COUNT(*) FROM scheduled_tasks"
        );
        
        // API-Monitoring-Daten laden
        $apiMonitoring = $this->getApiMonitoringData();
        
        // API-Aktivitäten (letzte 24h)
        $recentActivity = $this->getRecentApiActivity(20);
        
        View::render('admin/api/index', [
            'apis'           => $apis,
            'totalKeys'      => $totalKeys,
            'totalTasks'     => $totalTasks,
            'totalApis'      => $totalApis,
            'activeApis'     => $activeApis,
            'inactiveApis'   => $inactiveApis,
            'apiMonitoring'  => $apiMonitoring,
            'recentActivity' => $recentActivity
        ]);
    }
    
    /**
     * API-Details für Detailansicht laden
     */
    public function view(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $api = Api::getById($id);
            
            if (!$api) {
                http_response_code(404);
                echo json_encode([
                    'success' => false, 
                    'message' => 'API nicht gefunden'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
            
            // Zusätzliche Daten laden
            $api['keys'] = Api::getApiKeys($id);
            $api['tasks'] = Api::getTasks($id);
            $api['stats'] = Api::getStats($id);
            
            echo json_encode([
                'success' => true, 
                'api' => $api
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    
    /**
     * API-Monitoring-Daten abrufen
     */
    private function getApiMonitoringData(): array
    {
        $monitoring = \App\Core\Database::fetchAll("
            SELECT 
                a.id,
                a.api_name,
                a.is_active,
                COUNT(DISTINCT ak.id) as total_keys,
                COUNT(DISTINCT st.id) as total_tasks,
                COUNT(DISTINCT CASE WHEN st.is_active = 1 THEN st.id END) as active_tasks,
                AVG(tel.execution_time) as avg_response_time,
                COUNT(tel.id) as total_requests_24h,
                SUM(CASE WHEN tel.status = 'success' THEN 1 ELSE 0 END) as successful_requests,
                MAX(tel.executed_at) as last_activity
            FROM apis a
            LEFT JOIN api_keys ak ON a.id = ak.api_id
            LEFT JOIN scheduled_tasks st ON a.id = st.api_id
            LEFT JOIN task_execution_logs tel ON st.id = tel.task_id 
                AND tel.executed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY a.id
            ORDER BY a.api_name
        ");
        
        // Erfolgsrate berechnen
        foreach ($monitoring as &$mon) {
            if ($mon['total_requests_24h'] > 0) {
                $mon['success_rate'] = round(($mon['successful_requests'] / $mon['total_requests_24h']) * 100, 1);
            } else {
                $mon['success_rate'] = 0;
            }
            
            // Health Status bestimmen
            if (!$mon['is_active']) {
                $mon['health_status'] = 'inactive';
            } elseif ($mon['success_rate'] >= 90) {
                $mon['health_status'] = 'healthy';
            } elseif ($mon['success_rate'] >= 70) {
                $mon['health_status'] = 'warning';
            } else {
                $mon['health_status'] = 'critical';
            }
            
            $mon['avg_response_time'] = $mon['avg_response_time'] ? round($mon['avg_response_time'], 2) : 0;
        }
        
        return $monitoring ?: [];
    }
    
    /**
     * Letzte API-Aktivitäten
     */
    private function getRecentApiActivity(int $limit = 20): array
    {
        return \App\Core\Database::fetchAll("
            SELECT 
                tel.id,
                tel.executed_at,
                tel.status,
                tel.http_code,
                tel.execution_time,
                tel.error_message,
                st.task_name,
                a.api_name
            FROM task_execution_logs tel
            JOIN scheduled_tasks st ON tel.task_id = st.id
            JOIN apis a ON st.api_id = a.id
            WHERE tel.executed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY tel.executed_at DESC
            LIMIT ?
        ", [$limit]) ?: [];
    }
    
    /**
     * API erstellen
     */
    public function store(): void
    {
        try {
            $data = [
                'api_name' => $_POST['api_name'] ?? '',
                'base_url' => $_POST['base_url'] ?? '',
                'description' => $_POST['description'] ?? '',
                'api_type' => $_POST['api_type'] ?? 'rest',
                'auth_type' => $_POST['auth_type'] ?? 'none',
                'is_active' => (int)($_POST['is_active'] ?? 1)
            ];
            
            if (isset($_FILES['api_file']) && $_FILES['api_file']['error'] === UPLOAD_ERR_OK) {
                $data['api_file'] = $_FILES['api_file'];
            }
            
            $apiId = Api::create($data);
            
            Response::json([
                'success' => true,
                'message' => 'API erfolgreich erstellt',
                'api_id' => $apiId
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * API-Daten für Bearbeitung laden
     */
    public function edit(int $id): void
    {
        try {
            $api = Api::getById($id);
            
            if (!$api) {
                Response::json(['success' => false, 'message' => 'API nicht gefunden'], 404);
                return;
            }
            
            Response::json(['success' => true, 'api' => $api]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * API aktualisieren
     */
    public function update(int $id): void
    {
        try {
            $data = [
                'api_name' => $_POST['api_name'] ?? '',
                'base_url' => $_POST['base_url'] ?? '',
                'description' => $_POST['description'] ?? '',
                'api_type' => $_POST['api_type'] ?? 'rest',
                'auth_type' => $_POST['auth_type'] ?? 'none',
                'is_active' => (int)($_POST['is_active'] ?? 1)
            ];
            
            if (isset($_FILES['api_file']) && $_FILES['api_file']['error'] === UPLOAD_ERR_OK) {
                $data['api_file'] = $_FILES['api_file'];
            }
            
            Api::update($id, $data);
            
            Response::json(['success' => true, 'message' => 'API erfolgreich aktualisiert']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * API löschen
     */
    public function delete(int $id): void
    {
        try {
            Api::delete($id);
            Response::json(['success' => true, 'message' => 'API erfolgreich gelöscht']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * API aktivieren/deaktivieren
     */
    public function toggle(int $id): void
    {
        try {
            Api::toggleActive($id);
            Response::json(['success' => true, 'message' => 'API-Status geändert']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Live-Activity-Feed für API-Seite
     */
    public function activityFeed(): void
    {
        try {
            $activities = $this->getRecentApiActivity(50);
            Response::json(['success' => true, 'activities' => $activities]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * API-Health-Daten für Monitoring-Bereich
     */
    public function healthData(): void
    {
        try {
            $healthData = $this->getApiMonitoringData();
            Response::json(['success' => true, 'health' => $healthData]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}