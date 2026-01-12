<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Security;
use App\Core\Response;
use App\Services\Admin\AdminMediaService;
use App\Services\Admin\AdminMediaListService;
use App\Services\Admin\AdminMediaEditService;
use App\Services\Admin\AdminMediaChartsService;
use App\Models\Comment;

final class AdminMediaController
{
    private AdminMediaService $service;
    private AdminMediaListService $listService;
    private AdminMediaEditService $editService;
    private AdminMediaChartsService $chartsService;

    public function __construct()
    {
        Security::requireAdmin();
        
        $this->service = new AdminMediaService();
        $this->listService = new AdminMediaListService();
        $this->editService = new AdminMediaEditService();
        $this->chartsService = new AdminMediaChartsService();
    }

    /* =====================================================
       DASHBOARD
    ===================================================== */

    public function index(): void
    {
        $stats = [
            'pending' => $this->service->countPending(),
            'total' => $this->service->countAll(),
            'twitch' => $this->service->countTwitch(),
            'pendingComments' => count(Comment::pending())
        ];

        $viewsChart = $this->chartsService->viewsPerDay(14);
        $uploadsChart = $this->chartsService->uploadsPerDay(14);

        Response::view('admin/media/index', [
            'stats' => $stats,
            'viewsChart' => $viewsChart,
            'uploadsChart' => $uploadsChart
        ]);
    }

    /* =====================================================
       VIDEOS LIST
    ===================================================== */

    public function videos(): void
    {
        $page = (int)($_GET['page'] ?? 1);
        $filters = [
            'q' => $_GET['q'] ?? '',
            'source' => $_GET['source'] ?? '',
            'status' => $_GET['status'] ?? '',
            'content_type' => 'video'
        ];

        [$videos, $total, $perPage] = $this->listService->list($filters, $page);

        Response::view('admin/media/videos', [
            'videos' => $videos,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'filters' => $filters
        ]);
    }

    /* =====================================================
       CLIPS LIST
    ===================================================== */

    public function clips(): void
    {
        $page = (int)($_GET['page'] ?? 1);
        $filters = [
            'q' => $_GET['q'] ?? '',
            'source' => $_GET['source'] ?? '',
            'status' => $_GET['status'] ?? '',
            'content_type' => 'clip'
        ];

        [$clips, $total, $perPage] = $this->listService->list($filters, $page);

        Response::view('admin/media/clips', [
            'clips' => $clips,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'filters' => $filters
        ]);
    }

    /* =====================================================
       VODS LIST
    ===================================================== */

    public function vods(): void
    {
        $page = (int)($_GET['page'] ?? 1);
        $filters = [
            'q' => $_GET['q'] ?? '',
            'status' => $_GET['status'] ?? '',
            'source' => 'twitch',
            'content_type' => 'vod'
        ];

        [$vods, $total, $perPage] = $this->listService->list($filters, $page);

        Response::view('admin/media/vods', [
            'vods' => $vods,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'filters' => $filters
        ]);
    }

    /* =====================================================
       EDIT
    ===================================================== */

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $video = $this->editService->get($id);

        Response::view('admin/media/edit', ['video' => $video]);
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $visibility = $_POST['visibility'] ?? 'public';
        $status = $_POST['status'] ?? 'pending';

        $this->editService->update($id, $title, $description, $visibility, $status);

        Response::redirect("/admin/media/edit?id={$id}&success=1");
    }

    /* =====================================================
       MODERATION
    ===================================================== */

    public function approve(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $this->service->approve($id);

        Response::json(['success' => true]);
    }

    public function reject(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $this->service->reject($id);

        Response::json(['success' => true]);
    }

    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $this->service->delete($id);

        Response::json(['success' => true]);
    }

    /* =====================================================
       COMMENTS
    ===================================================== */

    public function comments(): void
    {
        $pending = Comment::pending();

        Response::view('admin/media/comments', [
            'comments' => $pending
        ]);
    }

    public function approveComment(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        Comment::approve($id);

        Response::json(['success' => true]);
    }

    public function deleteComment(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        Comment::delete($id);

        Response::json(['success' => true]);
    }

    /* =====================================================
       TWITCH INTEGRATION
    ===================================================== */

    public function syncTwitchVods(): void
    {
        $this->service->syncTwitchVods();

        Response::redirect('/admin/media/vods?success=synced');
    }

    public function downloadTwitchVod(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $this->service->downloadTwitchVod($id);

        Response::json(['success' => true]);
    }
}