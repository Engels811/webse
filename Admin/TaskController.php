<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Response;
use App\Models\ScheduledTask;
use App\Models\Api;
use App\Core\Database;

class TaskController
{
    /**
     * Task-Übersicht mit integriertem Monitoring
     */
    public function index(): void
    {
        // Tasks laden
        $tasks = ScheduledTask::getAll();
        
        // APIs für Dropdown laden
        $availableApis = Api::getAll();
        
        // Statistiken berechnen
        $totalTasks = count($tasks);
        $runningTasks = count(array_filter($tasks, fn($t) => $t['is_active']));
        $stoppedTasks = $totalTasks - $runningTasks;
        $dueTasks = count(array_filter($tasks, fn($t) => $this->isDue($t)));
        
        // Task-Monitoring-Daten
        $taskMonitoring = $this->getTaskMonitoringData();
        
        // Task-Aktivitäten (letzte 100 Ausführungen)
        $recentExecutions = $this->getRecentTaskExecutions(100);
        
        // Performance-Metriken
        $performanceMetrics = $this->getTaskPerformanceMetrics();
        
        View::render('admin/tasks/index', [
            'tasks'                 => $tasks,
            'availableApis'         => $availableApis,
            'totalTasks'            => $totalTasks,
            'runningTasks'          => $runningTasks,
            'stoppedTasks'          => $stoppedTasks,
            'dueTasks'              => $dueTasks,
            'taskMonitoring'        => $taskMonitoring,
            'recentExecutions'      => $recentExecutions,
            'performanceMetrics'    => $performanceMetrics
        ]);
    }
    
    /**
     * Task-Monitoring-Daten
     */
    private function getTaskMonitoringData(): array
    {
        return Database::fetchAll("
            SELECT 
                st.id,
                st.task_name,
                st.is_active,
                st.interval,
                st.last_run,
                st.last_status,
                st.run_count,
                a.api_name,
                COUNT(tel.id) as executions_24h,
                SUM(CASE WHEN tel.status = 'success' THEN 1 ELSE 0 END) as successful_24h,
                AVG(tel.execution_time) as avg_execution_time,
                MAX(tel.executed_at) as last_execution
            FROM scheduled_tasks st
            JOIN apis a ON st.api_id = a.id
            LEFT JOIN task_execution_logs tel ON st.id = tel.task_id 
                AND tel.executed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY st.id
            ORDER BY st.created_at DESC
        ") ?: [];
    }
    
    /**
     * Letzte Task-Ausführungen
     */
    private function getRecentTaskExecutions(int $limit = 100): array
    {
        return Database::fetchAll("
            SELECT 
                tel.*,
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
     * Performance-Metriken für Tasks
     */
    private function getTaskPerformanceMetrics(): array
    {
        $metrics = Database::fetchOne("
            SELECT 
                COUNT(*) as total_executions_24h,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_executions,
                AVG(execution_time) as avg_execution_time,
                MAX(execution_time) as max_execution_time,
                MIN(execution_time) as min_execution_time
            FROM task_execution_logs
            WHERE executed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        
        // Wenn keine Daten oder total_executions = 0
        if (!$metrics || !isset($metrics['total_executions_24h']) || $metrics['total_executions_24h'] == 0) {
            return [
                'total_executions' => 0,
                'success_rate' => 0,
                'avg_execution_time' => 0,
                'max_execution_time' => 0,
                'min_execution_time' => 0,
                'tasks_per_hour' => 0
            ];
        }
        
        // Erfolgsrate berechnen (nur wenn total > 0)
        $successRate = round(($metrics['successful_executions'] / $metrics['total_executions_24h']) * 100, 1);
        $tasksPerHour = round($metrics['total_executions_24h'] / 24, 1);
        
        return [
            'total_executions' => (int)$metrics['total_executions_24h'],
            'success_rate' => $successRate,
            'avg_execution_time' => round((float)($metrics['avg_execution_time'] ?? 0), 2),
            'max_execution_time' => round((float)($metrics['max_execution_time'] ?? 0), 2),
            'min_execution_time' => round((float)($metrics['min_execution_time'] ?? 0), 2),
            'tasks_per_hour' => $tasksPerHour
        ];
    }
    
    /**
     * Prüfen ob Task fällig ist
     */
    private function isDue(array $task): bool
    {
        if (!$task['is_active']) return false;
        if (!$task['last_run']) return true;
        
        $nextRun = strtotime($task['last_run'] . ' +' . $task['interval'] . ' hours');
        return $nextRun <= time();
    }
    
    /**
     * Task erstellen
     */
    public function store(): void
    {
        try {
            $taskName = $_POST['task_name'] ?? '';
            $interval = $_POST['interval'] ?? '';
            $description = $_POST['description'] ?? '';
            $apiId = (int)($_POST['api_id'] ?? 0);
            $taskData = $_POST['task_data'] ?? null;
            
            $parsedTaskData = null;
            if ($taskData) {
                $parsedTaskData = json_decode($taskData, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \InvalidArgumentException('Ungültige JSON-Daten');
                }
            }
            
            $taskId = ScheduledTask::create($taskName, $interval, $description, $apiId, $parsedTaskData);
            
            Response::json([
                'success' => true,
                'message' => 'Task erfolgreich erstellt',
                'task_id' => $taskId
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Task-Daten für Bearbeitung laden
     */
    public function edit(int $id): void
    {
        try {
            $task = ScheduledTask::getById($id);
            
            if (!$task) {
                Response::json(['success' => false, 'message' => 'Task nicht gefunden'], 404);
                return;
            }
            
            Response::json(['success' => true, 'task' => $task]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Task aktualisieren
     */
    public function update(int $id): void
    {
        try {
            $data = [
                'task_name' => $_POST['task_name'] ?? '',
                'interval' => $_POST['interval'] ?? '',
                'description' => $_POST['description'] ?? '',
                'api_id' => (int)($_POST['api_id'] ?? 0),
                'is_active' => (int)($_POST['is_active'] ?? 1)
            ];
            
            if (isset($_POST['task_data'])) {
                $taskData = json_decode($_POST['task_data'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \InvalidArgumentException('Ungültige JSON-Daten');
                }
                $data['task_data'] = $taskData;
            }
            
            ScheduledTask::update($id, $data);
            
            Response::json(['success' => true, 'message' => 'Task erfolgreich aktualisiert']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Task löschen
     */
    public function delete(int $id): void
    {
        try {
            ScheduledTask::delete($id);
            Response::json(['success' => true, 'message' => 'Task erfolgreich gelöscht']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Task aktivieren/deaktivieren
     */
    public function toggle(int $id): void
    {
        try {
            ScheduledTask::toggleActive($id);
            Response::json(['success' => true, 'message' => 'Task-Status geändert']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Task sofort ausführen
     */
    public function runNow(int $id): void
    {
        try {
            $result = ScheduledTask::execute($id);
            
            if ($result['success']) {
                Response::json([
                    'success' => true,
                    'message' => 'Task erfolgreich ausgeführt',
                    'result' => $result
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Task-Ausführung fehlgeschlagen',
                    'error' => $result['error'] ?? 'Unbekannter Fehler'
                ], 400);
            }
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Alle fälligen Tasks ausführen
     */
    public function runAll(): void
    {
        try {
            $dueTasks = ScheduledTask::getDueTasks();
            $executed = 0;
            $errors = 0;
            
            foreach ($dueTasks as $task) {
                $result = ScheduledTask::execute((int)$task['id']);
                if ($result['success']) {
                    $executed++;
                } else {
                    $errors++;
                }
            }
            
            Response::json([
                'success' => true,
                'message' => "{$executed} Task(s) ausgeführt",
                'executed' => $executed,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Task-Logs abrufen
     */
    public function logs(int $id): void
    {
        try {
            $logs = Database::fetchAll("
                SELECT * FROM task_execution_logs
                WHERE task_id = ?
                ORDER BY executed_at DESC
                LIMIT 100
            ", [$id]);
            
            Response::json(['success' => true, 'logs' => $logs ?: []]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Live-Execution-Feed für Task-Seite
     */
    public function executionFeed(): void
    {
        try {
            $executions = $this->getRecentTaskExecutions(50);
            Response::json(['success' => true, 'executions' => $executions]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Task-Performance-Daten
     */
    public function performanceData(): void
    {
        try {
            $performance = $this->getTaskPerformanceMetrics();
            Response::json(['success' => true, 'performance' => $performance]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}