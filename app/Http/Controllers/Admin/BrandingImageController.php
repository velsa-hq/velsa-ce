<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrandingImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Welcome / sign-in background-image pool. Gated on system.settings via the admin route group.
 */
class BrandingImageController extends Controller
{
    public function index(): Response
    {
        $images = BrandingImage::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (BrandingImage $i) => [
                'id' => $i->id,
                'label' => $i->label,
                'is_active' => $i->is_active,
                'url' => $i->imageUrl(),
            ]);

        return Inertia::render('admin/branding-images/index', [
            'images' => $images,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $image = BrandingImage::create([
            'label' => $validated['label'] ?? null,
            'sort_order' => (int) BrandingImage::max('sort_order') + 1,
            'is_active' => true,
        ]);

        $image->addMediaFromRequest('image')->toMediaCollection('image');

        return back()->with('toast', ['type' => 'success', 'message' => 'Background image added.']);
    }

    public function update(Request $request, BrandingImage $brandingImage): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ]);

        $brandingImage->update([
            'label' => $validated['label'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Background image updated.']);
    }

    public function destroy(BrandingImage $brandingImage): RedirectResponse
    {
        $brandingImage->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Background image removed.']);
    }
}
