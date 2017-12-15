<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisNews;
use App\Services\EncryptedStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HrisNewsController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        if (!$user) return 1;
        if ($user->role === 'superadmin') {
            return $user->tenant_id ?? 1;
        }
        return $user->admin_id ?? $user->id;
    }

    /**
     * List all news/announcements
     */
    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $query = HrisNews::where('tenant_id', $tenantId)
            ->with('author')
            ->orderBy('id', 'desc');

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->filled('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('target_audience') && $request->target_audience !== 'all') {
            $query->where('target_audience', $request->target_audience);
        }

        if ($request->has('is_published') && $request->is_published !== '' && $request->is_published !== 'all') {
            $query->where('is_published', filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                    ->orWhere('content', 'like', "%{$s}%")
                    ->orWhere('category', 'like', "%{$s}%");
            });
        }

        return response()->json($query->get());
    }

    /**
     * Summary counts for dashboard metrics
     */
    public function summary(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $news = HrisNews::where('tenant_id', $tenantId)->get();

        $totalNews = $news->count();
        $urgentCount = $news->where('priority', 'urgent')->count();
        $publishedCount = $news->where('is_published', true)->count();
        $draftCount = $news->where('is_published', false)->count();
        $totalViews = $news->sum('views');

        return response()->json([
            'total_news' => $totalNews,
            'urgent_count' => $urgentCount,
            'published_count' => $publishedCount,
            'draft_count' => $draftCount,
            'total_views' => $totalViews,
        ]);
    }

    /**
     * Increment view count when user clicks/views news
     */
    public function incrementView(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $news = HrisNews::where('tenant_id', $tenantId)->findOrFail($id);
        $news->increment('views');

        return response()->json([
            'success' => true,
            'id' => $news->id,
            'views' => $news->fresh()->views,
        ]);
    }

    /**
     * Show news detail (and auto increment views)
     */
    public function show(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $news = HrisNews::where('tenant_id', $tenantId)->findOrFail($id);
        $news->increment('views');

        return response()->json($news->fresh()->load('author'));
    }

    /**
     * Create news/announcement
     */
    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'category' => 'required|string|max:100',
            'priority' => 'nullable|string|in:normal,urgent',
            'target_audience' => 'nullable|string',
            'is_published' => 'nullable|boolean',
            'banner_file' => 'nullable|file|max:10240',
            'banner_base64' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bannerPath = null;
        if ($request->hasFile('banner_file')) {
            $stored = EncryptedStorageService::storeEncrypted($request->file('banner_file'), $tenantId, 'news', 'news_banner');
            $bannerPath = $stored['path'];
        } elseif ($request->filled('banner_base64') && strlen($request->banner_base64) > 100) {
            $stored = EncryptedStorageService::storeEncrypted($request->banner_base64, $tenantId, 'news', 'news_banner');
            $bannerPath = $stored['path'];
        }

        $isPublished = $request->has('is_published') ? filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN) : true;

        $news = HrisNews::create([
            'tenant_id' => $tenantId,
            'created_by' => $request->user()->id,
            'title' => $request->title,
            'category' => $request->category,
            'content' => $request->content,
            'banner_path' => $bannerPath,
            'priority' => $request->priority ?? 'normal',
            'target_audience' => $request->target_audience ?? 'all',
            'is_published' => $isPublished,
            'views' => 0,
            'published_at' => $isPublished ? now() : null,
        ]);

        return response()->json($news->load('author'), 201);
    }

    /**
     * Update existing news/announcement
     */
    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $news = HrisNews::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:200',
            'content' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'priority' => 'nullable|string|in:normal,urgent',
            'target_audience' => 'nullable|string',
            'is_published' => 'nullable|boolean',
            'banner_file' => 'nullable|file|max:10240',
            'banner_base64' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = [];
        if ($request->has('title')) $data['title'] = $request->title;
        if ($request->has('content')) $data['content'] = $request->content;
        if ($request->has('category')) $data['category'] = $request->category;
        if ($request->has('priority')) $data['priority'] = $request->priority;
        if ($request->has('target_audience')) $data['target_audience'] = $request->target_audience;

        if ($request->has('is_published')) {
            $isPublished = filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN);
            $data['is_published'] = $isPublished;
            if ($isPublished && !$news->published_at) {
                $data['published_at'] = now();
            }
        }

        // Check if banner updated or removed
        if ($request->hasFile('banner_file')) {
            if ($news->banner_path) {
                EncryptedStorageService::deleteEncrypted($news->banner_path);
            }
            $stored = EncryptedStorageService::storeEncrypted($request->file('banner_file'), $tenantId, 'news', 'news_banner');
            $data['banner_path'] = $stored['path'];
        } elseif ($request->filled('banner_base64') && strlen($request->banner_base64) > 100) {
            if (str_starts_with($request->banner_base64, 'data:image')) {
                if ($news->banner_path) {
                    EncryptedStorageService::deleteEncrypted($news->banner_path);
                }
                $stored = EncryptedStorageService::storeEncrypted($request->banner_base64, $tenantId, 'news', 'news_banner');
                $data['banner_path'] = $stored['path'];
            }
        } elseif ($request->has('banner_base64') && $request->banner_base64 === null) {
            if ($news->banner_path) {
                EncryptedStorageService::deleteEncrypted($news->banner_path);
            }
            $data['banner_path'] = null;
        }

        $news->update($data);

        return response()->json($news->load('author'));
    }

    /**
     * Toggle publish status
     */
    public function togglePublish(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $news = HrisNews::where('tenant_id', $tenantId)->findOrFail($id);

        $newPublished = !$news->is_published;
        $news->update([
            'is_published' => $newPublished,
            'published_at' => $newPublished ? ($news->published_at ?? now()) : $news->published_at,
        ]);

        return response()->json($news->load('author'));
    }

    /**
     * Preview encrypted banner
     */
    public function previewBanner(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $news = HrisNews::where('tenant_id', $tenantId)->findOrFail($id);
        if (!$news->banner_path) {
            return response()->json(['message' => 'Banner berita tidak ditemukan.'], 404);
        }
        return EncryptedStorageService::streamResponse($news->banner_path, $news->title . '_banner', false);
    }

    /**
     * Delete news/announcement and its encrypted banner file
     */
    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $news = HrisNews::where('tenant_id', $tenantId)->findOrFail($id);

        if ($news->banner_path) {
            EncryptedStorageService::deleteEncrypted($news->banner_path);
        }

        $news->delete();

        return response()->json(['message' => 'Pengumuman / berita dan berkas banner berhasil dihapus.']);
    }
}
