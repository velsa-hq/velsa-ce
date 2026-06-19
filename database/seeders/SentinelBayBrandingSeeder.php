<?php

namespace Database\Seeders;

use App\Models\BrandingImage;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Writes the demo tenant branding overrides into system settings (app
 * shell, login, welcome page, outbound email). Targets the same keys an
 * admin edits via /admin/system-settings.
 *
 * Idempotent - SystemSettings::set is upsert-style.
 */
class SentinelBayBrandingSeeder extends Seeder
{
    public function run(SystemSettings $settings): void
    {
        $settings->set('branding.app_name', 'Sentinel Bay');
        $settings->set('branding.app_title', 'Sentinel Bay County Tourism & Convention Bureau');
        $settings->set('branding.app_subtitle', 'Sentinel Bay County, CA - Tourism & Convention Bureau');
        $settings->set('branding.app_tagline', 'Booking the Pacific Coast since 1853.');
        $settings->set('branding.logo_path', '/branding/sentinel-bay/logo.svg');
        $settings->set('branding.logo_alt', 'Sentinel Bay County seal');
        $settings->set('branding.stock_background_folder', 'branding/sentinel-bay/stock');

        $this->seedManagedBackgrounds();

        $this->command?->info('SentinelBayBrandingSeeder: applied Sentinel Bay County branding overrides.');
    }

    /**
     * Populate the admin-managed background pool from the shipped stock
     * photos so /admin/branding-images has a live gallery.
     * Idempotent - skips once any managed image exists.
     */
    private function seedManagedBackgrounds(): void
    {
        if (BrandingImage::query()->exists()) {
            return;
        }

        $files = glob(public_path('branding/sentinel-bay/stock/*.webp')) ?: [];

        foreach ($files as $index => $path) {
            $caption = Str::of(basename($path, '.webp'))->replace('-', ' ')->title()->toString();

            $image = BrandingImage::query()->create([
                'label' => $caption,
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);

            $image->addMedia($path)->preservingOriginal()->toMediaCollection('image');
        }
    }
}
