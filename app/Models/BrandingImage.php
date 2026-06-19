<?php

namespace App\Models;

use Database\Factories\BrandingImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Admin-managed background photo for the public welcome / sign-in pages. The
 * file lives in the single-file "image" media collection; the row carries
 * caption, sort order, and active flag. The rotation draws from active rows
 * (see HandleInertiaRequests).
 *
 * @property int $id
 * @property string|null $label
 * @property int $sort_order
 * @property bool $is_active
 */
class BrandingImage extends Model implements HasMedia
{
    /** @use HasFactory<BrandingImageFactory> */
    use HasFactory, InteractsWithMedia;

    /** presigned S3 image URL lifetime */
    private const IMAGE_URL_TTL_MINUTES = 60;

    protected $fillable = [
        'label',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /** Uploaded image URL, or null if none. Presigned on a private S3 disk, plain URL otherwise. */
    public function imageUrl(): ?string
    {
        $media = $this->getFirstMedia('image');
        if ($media === null) {
            return null;
        }

        if ($media->disk !== '' && config("filesystems.disks.{$media->disk}.driver") === 's3') {
            return $media->getTemporaryUrl(now()->addMinutes(self::IMAGE_URL_TTL_MINUTES));
        }

        return $media->getUrl();
    }
}
