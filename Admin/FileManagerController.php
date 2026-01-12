<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Response;

class FileManagerController
{
    private string $baseDir;
    private array $allowedExtensions = [
        'php', 'js', 'css', 'html', 'txt', 'json', 'xml', 'md', 'sql',
        'jpg', 'jpeg', 'png', 'gif', 'svg', 'pdf', 'zip', 'rar', '7z',
        'mp3', 'mp4', 'avi', 'doc', 'docx', 'xls', 'xlsx'
    ];
    
    public function __construct()
    {
        // Base Directory - Webspace Root
        $this->baseDir = realpath($_SERVER['DOCUMENT_ROOT']);
    }
    
    /**
     * Datei-Manager Hauptseite
     */
    public function index(): void
    {
        $path = $_GET['path'] ?? '/';
        $fullPath = $this->getFullPath($path);
        
        if (!$this->isPathAllowed($fullPath)) {
            View::render('error/403');
            return;
        }
        
        $files = $this->scanDirectory($fullPath);
        $stats = $this->calculateStats($this->baseDir);
        
        View::render('admin/files/index', [
            'files' => $files,
            'currentPath' => $path,
            'totalFiles' => $stats['totalFiles'],
            'totalFolders' => $stats['totalFolders'],
            'totalSize' => $this->formatFileSize($stats['totalSize']),
            'recentFiles' => $stats['recentFiles']
        ]);
    }
    
    /**
     * Dateien im Verzeichnis auflisten (AJAX)
     */
    public function list(): void
    {
        header('Content-Type: application/json');
        
        try {
            $path = $_GET['path'] ?? '/';
            $searchQuery = $_GET['search'] ?? '';
            $searchInContent = isset($_GET['searchInContent']) && $_GET['searchInContent'] === 'true';
            
            $fullPath = $this->getFullPath($path);
            
            if (!$this->isPathAllowed($fullPath)) {
                Response::json(['success' => false, 'message' => 'Zugriff verweigert'], 403);
                return;
            }
            
            // Wenn Suche aktiv ist
            if (!empty($searchQuery)) {
                $files = $this->searchFiles($fullPath, $searchQuery, $searchInContent);
            } else {
                $files = $this->scanDirectory($fullPath);
            }
            
            $stats = $this->calculateStats($this->baseDir);
            
            Response::json([
                'success' => true,
                'files' => $files,
                'stats' => [
                    'totalFiles' => $stats['totalFiles'],
                    'totalFolders' => $stats['totalFolders'],
                    'totalSize' => $this->formatFileSize($stats['totalSize']),
                    'recentFiles' => $stats['recentFiles']
                ]
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Dateien durchsuchen (Name und optional Inhalt)
     */
    private function searchFiles(string $baseDir, string $query, bool $searchInContent = false): array
    {
        $results = [];
        $query = strtolower($query);
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            $fileName = $file->getFilename();
            $filePath = $file->getPathname();
            $relativePath = str_replace($this->baseDir, '', $filePath);
            
            // Suche im Dateinamen
            $nameMatch = stripos($fileName, $query) !== false;
            
            // Suche im Inhalt (nur bei Textdateien und wenn aktiviert)
            $contentMatch = false;
            if ($searchInContent && $file->isFile() && $this->isTextFile($filePath)) {
                // Prüfe Dateigröße (max 1MB für Inhaltssuche)
                if ($file->getSize() < 1024 * 1024) {
                    $content = @file_get_contents($filePath);
                    if ($content !== false) {
                        $contentMatch = stripos($content, $query) !== false;
                    }
                }
            }
            
            if ($nameMatch || $contentMatch) {
                $results[] = [
                    'name' => $fileName,
                    'path' => $relativePath,
                    'type' => $file->isDir() ? 'folder' : 'file',
                    'size' => $file->isFile() ? $this->formatFileSize($file->getSize()) : '-',
                    'extension' => $file->isFile() ? strtolower($file->getExtension()) : '',
                    'modified' => $file->getMTime(),
                    'permissions' => substr(sprintf('%o', $file->getPerms()), -4),
                    'matchType' => $contentMatch ? 'content' : 'name' // Für Highlight
                ];
            }
            
            // Limit auf 100 Ergebnisse
            if (count($results) >= 100) {
                break;
            }
        }
        
        return $results;
    }
    
    /**
     * Datei-Details anzeigen
     */
    public function view(): void
    {
        header('Content-Type: application/json');
        
        try {
            $path = $_GET['path'] ?? '';
            $fullPath = $this->getFullPath($path);
            
            if (!$this->isPathAllowed($fullPath) || !file_exists($fullPath)) {
                Response::json(['success' => false, 'message' => 'Datei nicht gefunden'], 404);
                return;
            }
            
            $fileInfo = $this->getFileInfo($fullPath);
            
            Response::json([
                'success' => true,
                'file' => $fileInfo
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Dateiinhalt laden (für Editor)
     */
    public function content(): void
    {
        header('Content-Type: application/json');
        
        try {
            $path = $_GET['path'] ?? '';
            
            if (empty($path)) {
                Response::json(['success' => false, 'message' => 'Kein Pfad angegeben'], 400);
                return;
            }
            
            $fullPath = $this->getFullPath($path);
            
            if (!$this->isPathAllowed($fullPath)) {
                Response::json(['success' => false, 'message' => 'Zugriff auf diesen Pfad nicht erlaubt'], 403);
                return;
            }
            
            if (!file_exists($fullPath)) {
                Response::json(['success' => false, 'message' => 'Datei nicht gefunden: ' . $path], 404);
                return;
            }
            
            if (is_dir($fullPath)) {
                Response::json(['success' => false, 'message' => 'Dies ist ein Ordner, keine Datei'], 400);
                return;
            }
            
            // Prüfe ob Datei lesbar ist
            if (!is_readable($fullPath)) {
                Response::json(['success' => false, 'message' => 'Datei kann nicht gelesen werden (Berechtigungen)'], 403);
                return;
            }
            
            // Prüfe Dateigröße (max 5MB für Editor)
            $fileSize = filesize($fullPath);
            if ($fileSize > 5 * 1024 * 1024) {
                Response::json(['success' => false, 'message' => 'Datei zu groß für den Editor (max. 5MB)'], 400);
                return;
            }
            
            if (!$this->isTextFile($fullPath)) {
                Response::json(['success' => false, 'message' => 'Dieser Dateityp kann nicht im Editor bearbeitet werden'], 400);
                return;
            }
            
            $content = file_get_contents($fullPath);
            
            if ($content === false) {
                Response::json(['success' => false, 'message' => 'Fehler beim Lesen der Datei'], 500);
                return;
            }
            
            $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
            
            Response::json([
                'success' => true,
                'content' => $content,
                'language' => $this->getLanguageFromExtension($extension),
                'size' => $fileSize,
                'path' => $path
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Datei speichern
     */
    public function save(): void
    {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $path = $data['path'] ?? '';
            $content = $data['content'] ?? '';
            
            $fullPath = $this->getFullPath($path);
            
            if (!$this->isPathAllowed($fullPath)) {
                Response::json(['success' => false, 'message' => 'Zugriff verweigert'], 403);
                return;
            }
            
            // Backup erstellen
            if (file_exists($fullPath)) {
                copy($fullPath, $fullPath . '.backup');
            }
            
            if (file_put_contents($fullPath, $content) !== false) {
                Response::json(['success' => true, 'message' => 'Datei erfolgreich gespeichert']);
            } else {
                Response::json(['success' => false, 'message' => 'Fehler beim Speichern'], 500);
            }
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Datei hochladen
     */
    public function upload(): void
    {
        header('Content-Type: application/json');
        
        try {
            $path = $_POST['path'] ?? '/';
            $targetDir = $this->getFullPath($path);
            
            if (!$this->isPathAllowed($targetDir)) {
                Response::json(['success' => false, 'message' => 'Zugriff verweigert'], 403);
                return;
            }
            
            if (!isset($_FILES['files'])) {
                Response::json(['success' => false, 'message' => 'Keine Dateien hochgeladen'], 400);
                return;
            }
            
            $uploaded = 0;
            $errors = [];
            
            foreach ($_FILES['files']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                    $fileName = basename($_FILES['files']['name'][$key]);
                    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                    
                    if (!in_array(strtolower($extension), $this->allowedExtensions)) {
                        $errors[] = "$fileName: Dateityp nicht erlaubt";
                        continue;
                    }
                    
                    $targetPath = $targetDir . '/' . $fileName;
                    
                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $uploaded++;
                    } else {
                        $errors[] = "$fileName: Upload fehlgeschlagen";
                    }
                }
            }
            
            if ($uploaded > 0) {
                Response::json([
                    'success' => true,
                    'message' => "$uploaded Datei(en) erfolgreich hochgeladen",
                    'errors' => $errors
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Keine Dateien konnten hochgeladen werden',
                    'errors' => $errors
                ], 400);
            }
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Ordner erstellen
     */
    public function createFolder(): void
    {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $path = $data['path'] ?? '/';
            $name = $data['name'] ?? '';
            
            if (empty($name)) {
                Response::json(['success' => false, 'message' => 'Ordnername erforderlich'], 400);
                return;
            }
            
            $parentDir = $this->getFullPath($path);
            $newDir = $parentDir . '/' . $name;
            
            if (!$this->isPathAllowed($newDir)) {
                Response::json(['success' => false, 'message' => 'Zugriff verweigert'], 403);
                return;
            }
            
            if (file_exists($newDir)) {
                Response::json(['success' => false, 'message' => 'Ordner existiert bereits'], 400);
                return;
            }
            
            if (mkdir($newDir, 0755, true)) {
                Response::json(['success' => true, 'message' => 'Ordner erfolgreich erstellt']);
            } else {
                Response::json(['success' => false, 'message' => 'Fehler beim Erstellen'], 500);
            }
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Datei/Ordner umbenennen oder verschieben
     */
    public function rename(): void
    {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $oldPath = $data['oldPath'] ?? '';
            $newPath = $data['newPath'] ?? '';
            
            if (empty($oldPath) || empty($newPath)) {
                Response::json(['success' => false, 'message' => 'Alter und neuer Pfad erforderlich'], 400);
                return;
            }
            
            $fullOldPath = $this->getFullPath($oldPath);
            $fullNewPath = $this->getFullPath($newPath);
            
            if (!$this->isPathAllowed($fullOldPath) || !$this->isPathAllowed($fullNewPath)) {
                Response::json(['success' => false, 'message' => 'Zugriff verweigert'], 403);
                return;
            }
            
            if (!file_exists($fullOldPath)) {
                Response::json(['success' => false, 'message' => 'Ursprüngliche Datei nicht gefunden'], 404);
                return;
            }
            
            if (file_exists($fullNewPath)) {
                Response::json(['success' => false, 'message' => 'Zieldatei existiert bereits'], 400);
                return;
            }
            
            if (rename($fullOldPath, $fullNewPath)) {
                Response::json(['success' => true, 'message' => 'Erfolgreich umbenannt/verschoben']);
            } else {
                Response::json(['success' => false, 'message' => 'Fehler beim Umbenennen/Verschieben'], 500);
            }
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Datei/Ordner löschen
     */
    public function delete(): void
    {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $path = $data['path'] ?? '';
            
            $fullPath = $this->getFullPath($path);
            
            if (!$this->isPathAllowed($fullPath) || !file_exists($fullPath)) {
                Response::json(['success' => false, 'message' => 'Datei nicht gefunden'], 404);
                return;
            }
            
            if (is_dir($fullPath)) {
                if ($this->deleteDirectory($fullPath)) {
                    Response::json(['success' => true, 'message' => 'Ordner erfolgreich gelöscht']);
                } else {
                    Response::json(['success' => false, 'message' => 'Fehler beim Löschen'], 500);
                }
            } else {
                if (unlink($fullPath)) {
                    Response::json(['success' => true, 'message' => 'Datei erfolgreich gelöscht']);
                } else {
                    Response::json(['success' => false, 'message' => 'Fehler beim Löschen'], 500);
                }
            }
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Datei herunterladen
     */
    public function download(): void
    {
        try {
            $path = $_GET['path'] ?? '';
            $fullPath = $this->getFullPath($path);
            
            if (!$this->isPathAllowed($fullPath) || !file_exists($fullPath) || is_dir($fullPath)) {
                header('HTTP/1.0 404 Not Found');
                echo 'Datei nicht gefunden';
                return;
            }
            
            $fileName = basename($fullPath);
            $mimeType = mime_content_type($fullPath) ?: 'application/octet-stream';
            
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($fullPath));
            header('Cache-Control: no-cache');
            
            readfile($fullPath);
            exit;
        } catch (\Exception $e) {
            header('HTTP/1.0 500 Internal Server Error');
            echo 'Fehler beim Download: ' . $e->getMessage();
        }
    }
    
    // Hilfsfunktionen
    
    private function getFullPath(string $path): string
    {
        $path = ltrim($path, '/');
        $fullPath = realpath($this->baseDir . '/' . $path);
        
        if ($fullPath === false) {
            $fullPath = $this->baseDir . '/' . $path;
        }
        
        return $fullPath;
    }
    
    private function isPathAllowed(string $path): bool
    {
        $realPath = realpath($path) ?: $path;
        $realBase = realpath($this->baseDir);
        
        return str_starts_with($realPath, $realBase);
    }
    
    private function scanDirectory(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        
        $files = [];
        $items = scandir($dir);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $fullPath = $dir . '/' . $item;
            $relativePath = str_replace($this->baseDir, '', $fullPath);
            
            $files[] = [
                'name' => $item,
                'path' => $relativePath,
                'type' => is_dir($fullPath) ? 'folder' : 'file',
                'size' => is_file($fullPath) ? $this->formatFileSize(filesize($fullPath)) : '-',
                'extension' => is_file($fullPath) ? strtolower(pathinfo($item, PATHINFO_EXTENSION)) : '',
                'modified' => filemtime($fullPath),
                'permissions' => substr(sprintf('%o', fileperms($fullPath)), -4)
            ];
        }
        
        // Sortieren: Ordner zuerst, dann nach Name
        usort($files, function($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'folder' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });
        
        return $files;
    }
    
    private function getFileInfo(string $path): array
    {
        return [
            'name' => basename($path),
            'path' => str_replace($this->baseDir, '', $path),
            'size' => $this->formatFileSize(filesize($path)),
            'mimeType' => mime_content_type($path),
            'extension' => pathinfo($path, PATHINFO_EXTENSION),
            'created' => filectime($path),
            'modified' => filemtime($path),
            'permissions' => substr(sprintf('%o', fileperms($path)), -4)
        ];
    }
    
    private function calculateStats(string $dir): array
    {
        $totalFiles = 0;
        $totalFolders = 0;
        $totalSize = 0;
        $recentFiles = 0;
        $yesterday = strtotime('-1 day');
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $totalFiles++;
                $totalSize += $file->getSize();
                
                if ($file->getMTime() >= $yesterday) {
                    $recentFiles++;
                }
            } elseif ($file->isDir()) {
                $totalFolders++;
            }
        }
        
        return [
            'totalFiles' => $totalFiles,
            'totalFolders' => $totalFolders,
            'totalSize' => $totalSize,
            'recentFiles' => $recentFiles
        ];
    }
    
    private function formatFileSize(int $bytes): string
    {
        if ($bytes === 0) return '0 Bytes';
        
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
    
    private function isTextFile(string $path): bool
    {
        $textExtensions = ['php', 'js', 'css', 'html', 'txt', 'json', 'xml', 'md', 'sql', 'ini', 'conf', 'log'];
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        return in_array($extension, $textExtensions);
    }
    
    private function getLanguageFromExtension(string $extension): string
    {
        $languages = [
            'php' => 'php',
            'js' => 'javascript',
            'css' => 'css',
            'html' => 'html',
            'json' => 'json',
            'xml' => 'xml',
            'sql' => 'sql',
            'md' => 'markdown',
        ];
        
        return $languages[$extension] ?? 'text';
    }
    
    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        
        $items = scandir($dir);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $path = $dir . '/' . $item;
            
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
}