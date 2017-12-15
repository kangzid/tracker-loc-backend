<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisNews;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HrisNewsController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        return $user->isAdmin() ? $user->id : ($user->admin_id ?? $user->id);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = HrisNews::where('tenant_id', $tenantId)->with('author');

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('target_audience')) {
            $query->where('target_audience', $request->target_audience);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('published_at', 'desc')->orderBy('id', 'desc')->get();
        return response()->json($items);
    }

    public function summary(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = HrisNews::where('tenant_id', $tenantId);

        $totalNews = (clone $query)->count();
        $urgentCount = (clone $query)->where('priority', 'urgent')->count();
        $publishedCount = (clone $query)->where('is_published', true)->count();
        $draftCount = (clone $query)->where('is_published', false)->count();

        return response()->json([
            'total_news' => $totalNews,
            'urgent_count' => $urgentCount,
            'published_count' => $publishedCount,
            'draft_count' => $draftCount,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'category' => 'nullable|string|max:50',
            'priority' => 'required|in:normal,urgent',
            'target_audience' => 'required|in:all,drivers_only,staff_only',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
            'banner_base64' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $news = HrisNews::create([
            'tenant_id' => $tenantId,
            'title' => $request->title,
            'category' => $request->category ?? 'Announcement',
            'content' => $request->content,
            'banner_base64' => $request->banner_base64,
            'priority' => $request->priority,
            'target_audience' => $request->target_audience,
            'is_published' => $request->is_published ?? true,
            'published_at' => $request->published_at ?? now(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json($news->load('author'), 201);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $news = HrisNews::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'category' => 'nullable|string|max:50',
            'priority' => 'required|in:normal,urgent',
            'target_audience' => 'required|in:all,drivers_only,staff_only',
            'is_published' => 'boolean',
            'banner_base64' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $news->update($request->only([
            'title', 'category', 'content', 'priority', 'target_audience', 'is_published', 'banner_base64'
        ]));

        return response()->json($news->load('author'));
    }

    public function togglePublish(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $news = HrisNews::where('tenant_id', $tenantId)->findOrFail($id);

        $news->is_published = !$news->is_published;
        if ($news->is_published && !$news->published_at) {
            $news->published_at = now();
        }
        $news->save();

        return response()->json([
            'message' => $news->is_published ? 'Berita berhasil dipublikasikan.' : 'Berita diubah menjadi draft.',
            'data' => $news->load('author')
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $news = HrisNews::where('tenant_id', $tenantId)->findOrFail($id);
        $news->delete();

        return response()->json(['message' => 'Berita berhasil dihapus.']);
    }
}
