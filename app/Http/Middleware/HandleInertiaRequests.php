<?php

namespace App\Http\Middleware;

use App\Models\BrandingImage;
use App\Models\User;
use App\Services\ReleaseNotes;
use App\Services\SystemSettings\SystemSettings;
use App\Support\AreaUnit;
use App\Support\SafeMode;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $settings = app(SystemSettings::class);

        return [
            ...parent::share($request),
            'name' => $settings->get('branding.app_name', config('app.name')),
            'version' => config('app.version'),
            'auth' => [
                'user' => $request->user(),
                // staff cross-venue permission union for hiding unreachable nav
                // items; presentation only, routes are still enforced server-side.
                // resolved on the web guard explicitly: on portal pages the default
                // guard is exhibitor, which has no venue permissions.
                'permissions' => ($webUser = $request->user('web')) instanceof User
                    ? $webUser->venuePermissionNames()
                    : [],
                // exhibitor session lives on a separate guard
                'exhibitor' => $request->user('exhibitor') ? [
                    'id' => $request->user('exhibitor')->id,
                    'company_name' => $request->user('exhibitor')->company_name,
                    'contact_name' => $request->user('exhibitor')->contact_name,
                    'email' => $request->user('exhibitor')->email,
                ] : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'stockBackgrounds' => $this->stockBackgrounds($settings),
            'branding' => [
                'app_name' => $settings->get('branding.app_name'),
                'app_title' => $settings->get('branding.app_title'),
                'app_subtitle' => $settings->get('branding.app_subtitle'),
                'app_tagline' => $settings->get('branding.app_tagline'),
                'logo_path' => $settings->get('branding.logo_path'),
                'logo_alt' => $settings->get('branding.logo_alt'),
            ],
            // SSO master toggle so the UI can hide SSO-only items when off
            'features' => [
                'sso_enabled' => (bool) config('sso.enabled'),
            ],
            // area-unit localization; front-end converts canonical sqft for display
            'measurement' => AreaUnit::config(),
            // drives the environment ribbon
            'safeMode' => SafeMode::enabled() ? ['label' => SafeMode::label()] : null,
            // drives the maintenance ribbon
            'readOnly' => (bool) $settings->get('operations.read_only', false),
            // unread indicator: a release was published since the user last looked
            'whatsNew' => [
                'unread' => $request->user() !== null
                    && app(ReleaseNotes::class)->hasUnreadSince($request->user()->whats_new_seen_at),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    protected function stockBackgrounds(SystemSettings $settings): array
    {
        // admin-managed pool wins when non-empty; else fall back to shipped stock
        // so a fresh install still renders before anything's uploaded
        $managed = BrandingImage::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (BrandingImage $i) => $i->imageUrl())
            ->filter()
            ->values()
            ->all();

        if ($managed !== []) {
            return $managed;
        }

        $folder = trim((string) $settings->get('branding.stock_background_folder'), '/');
        // confine glob to a relative path under public/: reject traversal and
        // absolute/backslash segments so a tampered setting can't escape the web root
        if ($folder === '' || preg_match('#(^|/)\.\.(/|$)#', $folder) || str_contains($folder, '\\') || str_contains($folder, "\0")) {
            return [];
        }

        $files = glob(public_path($folder.'/*.webp')) ?: [];

        return array_values(array_map(
            fn (string $path): string => '/'.$folder.'/'.basename($path),
            $files,
        ));
    }
}
