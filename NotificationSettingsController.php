<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * 🔔 NOTIFICATION SETTINGS CONTROLLER
 * ═══════════════════════════════════════════════════════════════════════════
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Security;
use App\Core\Session;
use App\Core\Response;
use App\Services\User\NotificationSettingsService;

final class NotificationSettingsController
{
    private NotificationSettingsService $settingsService;

    public function __construct()
    {
        $this->settingsService = new NotificationSettingsService();
    }

    /**
     * GET /notifications/settings
     */
    public function index(): void
    {
        Security::requireLogin();

        $userId   = Session::userId();
        $settings = $this->settingsService->getUserSettings($userId);
        $channels = $this->settingsService->getAvailableChannels();

        View::render('notifications/settings', [
            'title'    => 'Benachrichtigungs-Einstellungen',
            'settings' => $settings,
            'channels' => $channels,
        ]);
    }

    /**
     * POST /notifications/settings/save
     */
    public function save(): void
    {
        Security::requireLogin();
        Security::validateCSRF();

        $userId    = Session::userId();
        $submitted = $_POST['settings'] ?? [];

        $settingsToSave = [];

        foreach ($submitted as $type => $data) {
            if (!is_array($data)) {
                continue;
            }

            $enabled     = isset($data['enabled']) && $data['enabled'] === '1';
            $channels    = $data['channels'] ?? [];
            $subSettings = [];

            if (isset($data['sub']) && is_array($data['sub'])) {
                foreach ($data['sub'] as $subKey => $subValue) {
                    if (is_array($subValue)) {
                        $subSettings[$subKey] = $subValue;
                    } else {
                        $subSettings[$subKey] =
                            $subValue === '1' ? true : $subValue;
                    }
                }
            }

            $settingsToSave[$type] = [
                'enabled'      => $enabled,
                'channels'     => $channels,
                'sub_settings' => $subSettings,
            ];
        }

        $success = $this->settingsService
            ->saveAllSettings($userId, $settingsToSave);

        notify_ui(
            $success
                ? '✅ Einstellungen gespeichert!'
                : '❌ Fehler beim Speichern',
            $success ? 'success' : 'error'
        );

        Response::redirect('/notifications/settings');
    }

    /**
     * POST /notifications/settings/reset
     * Setzt alle Einstellungen auf Default zurück
     */
    public function reset(): void
    {
        Security::requireLogin();
        Security::validateCSRF();

        $this->settingsService->resetToDefaults(Session::userId());

        notify_ui(
            '🔄 Einstellungen auf Standard zurückgesetzt',
            'success'
        );

        Response::redirect('/notifications/settings');
    }

    /**
     * GET /notifications/settings/export
     */
    public function export(): void
    {
        Security::requireLogin();

        $settings = $this->settingsService
            ->getUserSettings(Session::userId());

        header('Content-Type: application/json');
        header(
            'Content-Disposition: attachment; ' .
            'filename="notification-settings.json"'
        );

        echo json_encode(
            $settings,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    /**
     * POST /notifications/settings/import
     */
    public function import(): void
    {
        Security::requireLogin();
        Security::validateCSRF();

        if (
            empty($_FILES['settings_file']) ||
            !is_uploaded_file($_FILES['settings_file']['tmp_name'])
        ) {
            notify_ui('❌ Keine Datei hochgeladen', 'error');
            Response::redirect('/notifications/settings');
            return;
        }

        $content  = file_get_contents($_FILES['settings_file']['tmp_name']);
        $decoded  = json_decode($content, true);

        if (!is_array($decoded)) {
            notify_ui('❌ Ungültige JSON-Datei', 'error');
            Response::redirect('/notifications/settings');
            return;
        }

        $toSave = [];

        foreach ($decoded as $category) {
            if (
                !isset($category['settings']) ||
                !is_array($category['settings'])
            ) {
                continue;
            }

            foreach ($category['settings'] as $type => $data) {
                if (!is_array($data)) {
                    continue;
                }

                $toSave[$type] = [
                    'enabled'      => (bool)($data['enabled'] ?? false),
                    'channels'     => $data['enabled_channels'] ?? [],
                    'sub_settings' => [],
                ];

                if (
                    isset($data['sub_settings']) &&
                    is_array($data['sub_settings'])
                ) {
                    foreach ($data['sub_settings'] as $k => $v) {
                        if (isset($v['value'])) {
                            $toSave[$type]['sub_settings'][$k] = $v['value'];
                        }
                    }
                }
            }
        }

        $success = $this->settingsService
            ->saveAllSettings(Session::userId(), $toSave);

        notify_ui(
            $success
                ? '✅ Einstellungen importiert!'
                : '❌ Fehler beim Importieren',
            $success ? 'success' : 'error'
        );

        Response::redirect('/notifications/settings');
    }

    /**
     * GET /api/notifications/settings/preview
     */
    public function preview(): void
    {
        Security::requireLogin();

        $type   = $_GET['type'] ?? 'twitch.stream.online';
        $userId = Session::userId();

        $enabled = $this->settingsService
            ->shouldNotify($userId, $type, 'web');

        $subSettings = [];
        foreach (['sound', 'show_stats', 'show_message'] as $key) {
            $value = $this->settingsService
                ->getSubSetting($userId, $type, $key);
            if ($value !== null) {
                $subSettings[$key] = $value;
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'enabled'      => $enabled,
            'sub_settings' => $subSettings,
            'preview'      => $this->generatePreview($type),
        ]);
        exit;
    }

    /**
     * Vorschau-HTML für Notifications
     */
    private function generatePreview(string $type): string
    {
        $examples = [
            'twitch.stream.online' => [
                'title'   => '🔴 GTA RP Stream',
                'message' =>
                    "Der Stream ist jetzt live!\n🎮 Spiel: Grand Theft Auto V",
            ],
            'profile.new_follower' => [
                'title'   => '👥 Neuer Follower',
                'message' => 'MaxMustermann folgt dir jetzt!',
            ],
            'games.new_import' => [
                'title'   => '🎮 Spiele importiert',
                'message' =>
                    "Steam Import abgeschlossen\n✅ 50 Spiele\n🆕 10 neu",
            ],
        ];

        $example = $examples[$type]
            ?? ['title' => 'Beispiel', 'message' => 'Test Benachrichtigung'];

        return sprintf(
            '<div class="notification-preview">
                <strong>%s</strong>
                <p>%s</p>
            </div>',
            htmlspecialchars($example['title'], ENT_QUOTES, 'UTF-8'),
            nl2br(
                htmlspecialchars($example['message'], ENT_QUOTES, 'UTF-8')
            )
        );
    }
}
