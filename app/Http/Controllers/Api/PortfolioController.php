<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PortfolioController extends Controller
{
    private const ALLOWED_ICONS = ['globe', 'cpu', 'smartphone', 'code'];

    /**
     * Public: Get published portfolios + dynamic category list.
     */
    public function index(Request $request)
    {
        $query = Portfolio::published()->ordered();

        if ($request->filled('category') && $request->category !== 'All') {
            $query->where('category', $request->category);
        }

        $portfolios = $query->get();

        $categories = Portfolio::published()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->values();

        return response()->json([
            'data' => $portfolios,
            'categories' => $categories,
        ], 200);
    }

    /**
     * Public: Get a single published portfolio item.
     */
    public function show(int $id)
    {
        $portfolio = Portfolio::published()->findOrFail($id);

        return response()->json([
            'data' => $portfolio,
        ], 200);
    }

    /**
     * Admin: List all portfolios (published + drafts).
     */
    public function adminIndex()
    {
        $portfolios = Portfolio::ordered()->get();

        return response()->json([
            'data' => $portfolios,
        ], 200);
    }

    /**
     * Admin: Create a new portfolio item.
     */
    public function store(Request $request)
    {
        $this->normalizeFormData($request);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'image' => ['required_without:image_url', 'nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'image_url' => ['required_without:image', 'nullable', 'string', 'max:2048'],
            'tech_stack' => ['required', 'array', 'min:1'],
            'tech_stack.*' => ['required', 'string', 'max:60'],
            'icon' => ['nullable', 'string', 'in:' . implode(',', self::ALLOWED_ICONS)],
            'live_url' => ['nullable', 'url', 'max:2048'],
            'is_published' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $imageUrl = $validated['image_url'] ?? '';
        $imagePublicId = null;

        if ($request->hasFile('image')) {
            $upload = Cloudinary::uploadApi()->upload(
                $request->file('image')->getRealPath(),
                ['folder' => config('cloudinary.upload_preset', 'ict_solu_img') . '/portfolios']
            );
            $imageUrl = $upload['secure_url'];
            $imagePublicId = $upload['public_id'];
        }

        $portfolio = Portfolio::create([
            'title' => $validated['title'],
            'category' => $validated['category'],
            'description' => $validated['description'] ?? null,
            'image_url' => $imageUrl,
            'image_public_id' => $imagePublicId,
            'tech_stack' => array_values($validated['tech_stack']),
            'icon' => $validated['icon'] ?? 'globe',
            'live_url' => $validated['live_url'] ?? null,
            'is_published' => $validated['is_published'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'message' => 'Portfolio created successfully.',
            'data' => $portfolio,
        ], 201);
    }

    /**
     * Admin: Update an existing portfolio item.
     * Note: When uploading a file from React, send POST with _method=PUT.
     */
    public function update(Request $request, int $id)
    {
        $portfolio = Portfolio::findOrFail($id);
        $this->normalizeFormData($request);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'tech_stack' => ['sometimes', 'required', 'array', 'min:1'],
            'tech_stack.*' => ['required', 'string', 'max:60'],
            'icon' => ['nullable', 'string', 'in:' . implode(',', self::ALLOWED_ICONS)],
            'live_url' => ['nullable', 'url', 'max:2048'],
            'is_published' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($request->hasFile('image')) {
            $upload = Cloudinary::uploadApi()->upload(
                $request->file('image')->getRealPath(),
                ['folder' => config('cloudinary.upload_preset', 'ict_solu_img') . '/portfolios']
            );

            $this->deleteCloudinaryAsset($portfolio->image_public_id);

            $portfolio->image_url = $upload['secure_url'];
            $portfolio->image_public_id = $upload['public_id'];
        } elseif (!empty($validated['image_url'])) {
            $portfolio->image_url = $validated['image_url'];
        }

        $portfolio->fill(collect($validated)->except(['image', 'image_url'])->toArray());

        if (isset($validated['tech_stack'])) {
            $portfolio->tech_stack = array_values($validated['tech_stack']);
        }

        $portfolio->save();

        return response()->json([
            'message' => 'Portfolio updated successfully.',
            'data' => $portfolio,
        ], 200);
    }

    /**
     * Admin: Quick publish / draft toggle.
     */
    public function togglePublish(int $id)
    {
        $portfolio = Portfolio::findOrFail($id);
        $portfolio->is_published = !$portfolio->is_published;
        $portfolio->save();

        return response()->json([
            'message' => $portfolio->is_published
                ? 'Portfolio published.'
                : 'Portfolio moved to draft.',
            'data' => $portfolio,
        ], 200);
    }

    /**
     * Admin: Delete portfolio item and clean up Cloudinary asset.
     */
    public function destroy(int $id)
    {
        $portfolio = Portfolio::findOrFail($id);

        $this->deleteCloudinaryAsset($portfolio->image_public_id);
        $portfolio->delete();

        return response()->json([
            'message' => 'Portfolio deleted successfully.',
        ], 200);
    }

    /**
     * Normalize FormData stringified fields before Laravel validation.
     */
    private function normalizeFormData(Request $request): void
    {
        if ($request->has('tech_stack') && is_string($request->input('tech_stack'))) {
            $raw = trim($request->input('tech_stack'));
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $tags = $decoded;
            } else {
                $tags = array_filter(array_map('trim', explode(',', $raw)));
            }

            $request->merge(['tech_stack' => array_values($tags)]);
        }

        if ($request->has('is_published')) {
            $boolValue = filter_var(
                $request->input('is_published'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if ($boolValue !== null) {
                $request->merge(['is_published' => $boolValue]);
            }
        }

        if ($request->has('live_url') && trim((string) $request->input('live_url')) === '') {
            $request->merge(['live_url' => null]);
        }
    }

    private function deleteCloudinaryAsset(?string $publicId): void
    {
        if (!$publicId) {
            return;
        }

        try {
            Cloudinary::uploadApi()->destroy($publicId);
        } catch (\Throwable $e) {
            Log::warning("Failed to delete Cloudinary asset {$publicId}: {$e->getMessage()}");
        }
    }
}
