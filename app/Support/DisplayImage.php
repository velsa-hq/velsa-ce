<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\HasMedia;

/**
 * Applies display-image form inputs (photo upload, remove_image flag) to a
 * HasDisplayImage model. Precedence: remove wins, then upload; untouched
 * leaves the existing image alone.
 */
class DisplayImage
{
    public static function apply(Model&HasMedia $model, Request $request): void
    {
        if ($request->boolean('remove_image')) {
            $model->clearMediaCollection('photo');

            return;
        }

        if ($request->hasFile('photo')) {
            $model->addMediaFromRequest('photo')->toMediaCollection('photo');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'photo' => ['nullable', 'image', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }
}
