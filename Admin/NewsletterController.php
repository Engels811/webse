<?php
/**
 * Newsletter Admin Controller
 * Manages newsletter system
 */

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Security;
use App\Models\Newsletter;
use App\Services\MailService;

class NewsletterController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
    }
    
    /**
     * Dashboard
     */
    public function index()
    {
        $stats = [
            'total_subscribers' => Newsletter::getSubscriberCount(null, true),
            'verified_subscribers' => Newsletter::getSubscriberCount(true, true),
            'pending_subscribers' => Newsletter::getSubscriberCount(false, true),
            'total_campaigns' => count(Newsletter::getAllCampaigns()),
            'sent_campaigns' => count(Newsletter::getAllCampaigns('sent')),
            'draft_campaigns' => count(Newsletter::getAllCampaigns('draft'))
        ];
        
        $recentCampaigns = array_slice(Newsletter::getAllCampaigns(), 0, 5);
        $recentSubscribers = array_slice(Newsletter::getAllSubscribers(null, true), 0, 10);
        
        $this->view('admin/newsletter/index', [
            'stats' => $stats,
            'recentCampaigns' => $recentCampaigns,
            'recentSubscribers' => $recentSubscribers
        ]);
    }
    
    /**
     * Templates List
     */
    public function templates()
    {
        $templates = Newsletter::getAllTemplates();
        $this->view('admin/newsletter/templates', ['templates' => $templates]);
    }
    
    /**
     * Create Template Form
     */
    public function createTemplate()
    {
        $this->view('admin/newsletter/create_template');
    }
    
    /**
     * Store Template
     */
    public function storeTemplate()
    {
        Security::validateCSRF();
        
        $required = ['name', 'template_key', 'type', 'subject', 'body'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                $this->redirect('/admin/newsletter/templates/create?error=invalid');
            }
        }
        
        // Check if key exists
        if (Newsletter::getTemplate($_POST['template_key'])) {
            $this->redirect('/admin/newsletter/templates/create?error=exists');
        }
        
        $data = [
            'name' => $_POST['name'],
            'template_key' => $_POST['template_key'],
            'type' => $_POST['type'],
            'subject' => $_POST['subject'],
            'body' => $_POST['body'],
            'preview_text' => $_POST['preview_text'] ?? null
        ];
        
        if (Newsletter::createTemplate($data)) {
            $this->redirect('/admin/newsletter/templates?success=created');
        } else {
            $this->redirect('/admin/newsletter/templates/create?error=failed');
        }
    }
    
    /**
     * Edit Template
     */
    public function editTemplate($id)
    {
        $template = Newsletter::getTemplate($id);
        
        if (!$template) {
            $this->redirect('/admin/newsletter/templates?error=notfound');
        }
        
        $this->view('admin/newsletter/edit_template', ['template' => $template]);
    }
    
    /**
     * Update Template
     */
    public function updateTemplate($id)
    {
        Security::validateCSRF();
        
        $data = [
            'name' => $_POST['name'],
            'type' => $_POST['type'],
            'subject' => $_POST['subject'],
            'body' => $_POST['body'],
            'preview_text' => $_POST['preview_text'] ?? null
        ];
        
        if (Newsletter::updateTemplate($id, $data)) {
            $this->redirect('/admin/newsletter/templates?success=updated');
        } else {
            $this->redirect('/admin/newsletter/templates/edit/' . $id . '?error=failed');
        }
    }
    
    /**
     * Delete Template
     */
    public function deleteTemplate($id)
    {
        Security::validateCSRF();
        
        if (Newsletter::deleteTemplate($id)) {
            $this->redirect('/admin/newsletter/templates?success=deleted');
        } else {
            $this->redirect('/admin/newsletter/templates?error=failed');
        }
    }
    
    /**
     * Subscribers List
     */
    public function subscribers()
    {
        $subscribers = Newsletter::getAllSubscribers(null, null);
        $categories = Newsletter::getCategories();
        
        $this->view('admin/newsletter/subscribers', [
            'subscribers' => $subscribers,
            'categories' => $categories
        ]);
    }
    
    /**
     * Campaigns List
     */
    public function campaigns()
    {
        $campaigns = Newsletter::getAllCampaigns();
        $this->view('admin/newsletter/campaigns', ['campaigns' => $campaigns]);
    }
    
    /**
     * Create Campaign
     */
    public function createCampaign()
    {
        $templates = Newsletter::getAllTemplates();
        $categories = Newsletter::getCategories();
        $subscribers = Newsletter::getAllSubscribers(true, true);
        
        $this->view('admin/newsletter/create_campaign', [
            'templates' => $templates,
            'categories' => $categories,
            'subscriber_count' => count($subscribers)
        ]);
    }
    
    /**
     * Store Campaign
     */
    public function storeCampaign()
    {
        Security::validateCSRF();
        
        $data = [
            'name' => $_POST['name'],
            'template_id' => $_POST['template_id'] ?? null,
            'category_id' => $_POST['category_id'] ?? null,
            'subject' => $_POST['subject'],
            'body' => $_POST['body'],
            'status' => $_POST['status'] ?? 'draft',
            'scheduled_at' => $_POST['scheduled_at'] ?? null,
            'created_by' => $_SESSION['user_id'] ?? null
        ];
        
        if (Newsletter::createCampaign($data)) {
            $this->redirect('/admin/newsletter/campaigns?success=created');
        } else {
            $this->redirect('/admin/newsletter/campaigns/create?error=failed');
        }
    }
    
    /**
     * Campaign Details
     */
    public function campaignDetails($id)
    {
        $campaign = Newsletter::getCampaign($id);
        
        if (!$campaign) {
            $this->redirect('/admin/newsletter/campaigns?error=notfound');
        }
        
        $stats = Newsletter::getCampaignStats($id);
        
        $this->view('admin/newsletter/campaign_details', [
            'campaign' => $campaign,
            'stats' => $stats
        ]);
    }
    
    /**
     * Settings
     */
    public function settings()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::validateCSRF();
            
            $settings = [
                'send_interval_minutes',
                'batch_size',
                'daily_limit',
                'from_name',
                'from_email',
                'reply_to',
                'double_optin',
                'track_opens',
                'track_clicks',
                'unsubscribe_link',
                'auto_cleanup_days'
            ];
            
            foreach ($settings as $key) {
                if (isset($_POST[$key])) {
                    Newsletter::updateSetting($key, $_POST[$key]);
                }
            }
            
            $this->redirect('/admin/newsletter/settings?success=saved');
        }
        
        // Load current settings
        $currentSettings = [];
        $settingsKeys = [
            'send_interval_minutes',
            'batch_size',
            'daily_limit',
            'from_name',
            'from_email',
            'reply_to',
            'double_optin',
            'track_opens',
            'track_clicks',
            'unsubscribe_link',
            'auto_cleanup_days'
        ];
        
        foreach ($settingsKeys as $key) {
            $currentSettings[$key] = Newsletter::getSetting($key);
        }
        
        $this->view('admin/newsletter/settings', ['settings' => $currentSettings]);
    }
}
