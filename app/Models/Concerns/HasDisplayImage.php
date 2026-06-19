<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One display image per model: an uploaded photo (single-file "photo"
 * collection) when present, else a deterministic generated identity image.
 * Always resolves to a URL.
 */
trait HasDisplayImage
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 600, 400)
            ->nonQueued();
    }

    private const IMAGE_URL_TTL_MINUTES = 30;

    // built from the immutable primary key so the generated image is stable across renames
    protected function identitySeed(): string
    {
        return Str::lower(class_basename($this)).'-'.$this->getKey();
    }

    // private S3 disks get a short-lived presigned URL; local/public disks get a plain URL
    public function imageUrl(string $conversion = ''): string
    {
        $media = $this->getFirstMedia('photo');
        if ($media !== null) {
            if (self::diskUsesPresignedUrls((string) $media->disk)) {
                return $media->getTemporaryUrl(
                    now()->addMinutes(self::IMAGE_URL_TTL_MINUTES),
                    $conversion,
                );
            }

            return $media->getUrl($conversion);
        }

        return route('identity.image', ['seed' => $this->identitySeed()]);
    }

    protected static function diskUsesPresignedUrls(string $disk): bool
    {
        return $disk !== ''
            && config("filesystems.disks.{$disk}.driver") === 's3';
    }

    public function thumbUrl(): string
    {
        return $this->imageUrl('thumb');
    }

    public function hasUploadedPhoto(): bool
    {
        return $this->hasMedia('photo');
    }
}
