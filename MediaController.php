<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Security;
use App\Core\Request;
use App\Core\Response;
use App\Services\Media\MediaService;
use App\Services\Media\FavoriteService;
use App\Services\Media\PlaylistService;
use App\Models\Like;
use App\Models\Comment;
use App\Models\Report;

final class MediaController
{
    private MediaService $mediaService;
    private FavoriteService $favoriteService;
    private PlaylistService $playlistService;

    public function __construct()
    {
        $this->mediaService = new MediaService();
        $this->favoriteService = new FavoriteService();
        $this->playlistService = new PlaylistService();
    }

    /* =====================================================
       OVERVIEW PAGES
    ===================================================== */

    public function videos(): void
    {
        $page = (int)($_GET['page'] ?? 1);
        $filters = [
            'q' => $_GET['q'] ?? '',
            'source' => $_GET['source'] ?? '',
            'type' => 'video'
        ];

        [$videos, $total, $perPage] = $this->mediaService->list($filters, $page);
        
        Response::view('media/videos', [
            'videos' => $videos,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'filters' => $filters,
            'sources' => $this->mediaService->sources()
        ]);
    }

    public function clips(): void
    {
        $page = (int)($_GET['page'] ?? 1);
        $filters = [
            'q' => $_GET['q'] ?? '',
            'source' => $_GET['source'] ?? '',
            'type' => 'clip'
        ];

        [$clips, $total, $perPage] = $this->mediaService->list($filters, $page);
        
        Response::view('media/clips', [
            'clips' => $clips,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'filters' => $filters
        ]);
    }

    public function vods(): void
    {
        $page = (int)($_GET['page'] ?? 1);
        $filters = [
            'q' => $_GET['q'] ?? '',
            'source' => 'twitch',
            'type' => 'vod'
        ];

        [$vods, $total, $perPage] = $this->mediaService->list($filters, $page);
        
        Response::view('media/vods', [
            'vods' => $vods,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'filters' => $filters
        ]);
    }

    /* =====================================================
       SINGLE VIDEO VIEW
    ===================================================== */

    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        
        $video = $this->mediaService->get($id);
        if (!$video) {
            Response::redirect('/media/videos?error=not_found');
            return;
        }

        // Track view
        $this->mediaService->trackView($id, Request::ip());

        $userId = Security::userId();
        
        // Get engagement data
        $likes = Like::count('media_video', $id);
        $userLiked = Like::userHasLiked('media_video', $id, $userId, Request::ip());
        $comments = Comment::approvedForVideo($id);
        $isFavorite = $userId ? $this->favoriteService->isFavorite($userId, $id) : false;
        $userPlaylists = $userId ? $this->playlistService->userPlaylists($userId) : [];

        Response::view('media/show', [
            'video' => $video,
            'likes' => $likes,
            'userLiked' => $userLiked,
            'comments' => $comments,
            'isFavorite' => $isFavorite,
            'userPlaylists' => $userPlaylists
        ]);
    }

    /* =====================================================
       LIKE / UNLIKE
    ===================================================== */

    public function toggleLike(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $userId = Security::userId();
        
        $liked = Like::toggle('media_video', $id, $userId, Request::ip());
        $count = Like::count('media_video', $id);

        Response::json([
            'success' => true,
            'liked' => $liked,
            'count' => $count
        ]);
    }

    /* =====================================================
       COMMENT
    ===================================================== */

    public function addComment(): void
    {
        Security::requireLogin();

        $id = (int)($_POST['id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

        if (mb_strlen($content) < 3) {
            Response::json(['error' => 'Kommentar zu kurz'], 400);
            return;
        }

        Comment::create($id, Security::userId(), $content, $parentId);

        Response::json(['success' => true, 'message' => 'Kommentar wird geprüft']);
    }

    /* =====================================================
       REPORT
    ===================================================== */

    public function report(): void
    {
        Security::requireLogin();

        $id = (int)($_POST['id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($reason)) {
            Response::json(['error' => 'Grund erforderlich'], 400);
            return;
        }

        Report::create(
            'media_video',
            $id,
            $reason,
            $message ?: null,
            Security::userId(),
            Request::ip()
        );

        Response::json(['success' => true, 'message' => 'Meldung eingereicht']);
    }

    /* =====================================================
       FAVORITES
    ===================================================== */

    public function toggleFavorite(): void
    {
        Security::requireLogin();

        $id = (int)($_POST['id'] ?? 0);
        $userId = Security::userId();

        $isFavorite = $this->favoriteService->toggle($userId, $id);

        Response::json([
            'success' => true,
            'isFavorite' => $isFavorite
        ]);
    }

    public function favorites(): void
    {
        Security::requireLogin();

        $userId = Security::userId();
        $page = (int)($_GET['page'] ?? 1);

        [$favorites, $total, $perPage] = $this->favoriteService->list($userId, $page);

        Response::view('media/favorites', [
            'favorites' => $favorites,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage
        ]);
    }

    /* =====================================================
       PLAYLISTS
    ===================================================== */

    public function playlists(): void
    {
        Security::requireLogin();

        $playlists = $this->playlistService->userPlaylists(Security::userId());

        Response::view('media/playlists', [
            'playlists' => $playlists
        ]);
    }

    public function createPlaylist(): void
    {
        Security::requireLogin();

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $visibility = $_POST['visibility'] ?? 'private';

        if (mb_strlen($name) < 3) {
            Response::json(['error' => 'Name zu kurz'], 400);
            return;
        }

        $id = $this->playlistService->create(
            Security::userId(),
            $name,
            $description,
            $visibility
        );

        Response::json(['success' => true, 'id' => $id]);
    }

    public function addToPlaylist(): void
    {
        Security::requireLogin();

        $playlistId = (int)($_POST['playlist_id'] ?? 0);
        $videoId = (int)($_POST['video_id'] ?? 0);

        $this->playlistService->addVideo($playlistId, $videoId, Security::userId());

        Response::json(['success' => true]);
    }

    public function removeFromPlaylist(): void
    {
        Security::requireLogin();

        $playlistId = (int)($_POST['playlist_id'] ?? 0);
        $videoId = (int)($_POST['video_id'] ?? 0);

        $this->playlistService->removeVideo($playlistId, $videoId, Security::userId());

        Response::json(['success' => true]);
    }

    public function playlistDetail(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $userId = Security::userId();

        $playlist = $this->playlistService->get($id, $userId);
        if (!$playlist) {
            Response::redirect('/media/playlists?error=not_found');
            return;
        }

        $videos = $this->playlistService->videos($id);

        Response::view('media/playlist_detail', [
            'playlist' => $playlist,
            'videos' => $videos
        ]);
    }
}