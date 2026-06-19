<?php

namespace App\Models\Concerns;

use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Attaches arbitrary documents to a record via the "documents" media collection.
 *
 * Mime/size validation lives at the controller, not acceptsMimeTypes, so
 * formats whose content-sniff is unreliable (docx -> zip, .eml) aren't
 * spuriously rejected.
 */
trait HasDocuments
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents')
            ->useDisk(config('media-library.private_disk'));
    }

    /**
     * Attached documents shaped for the frontend, newest first.
     *
     * @return list<array{id:int, name:string, file_name:string, size:int, url:string, uploaded_at:?string}>
     */
    public function documentsForDisplay(): array
    {
        return $this->getMedia('documents')
            ->sortByDesc('created_at')
            ->map(fn (Media $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'file_name' => $m->file_name,
                'size' => $m->size,
                'url' => $this->documentUrl($m),
                'uploaded_at' => $m->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function documentUrl(Media $media): string
    {
        // route through the authenticated gateway, never a public /storage URL, so downloads are authorized
        return route('media.show', $media);
    }
}
