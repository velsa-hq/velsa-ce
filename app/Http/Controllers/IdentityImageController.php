<?php

namespace App\Http\Controllers;

use App\Support\IdentityImage;
use Illuminate\Http\Response;

/**
 * Serves a deterministic low-poly identity image for a seed. Pure function
 * of the seed, no entity data, so it's auth-free and cached immutably.
 */
class IdentityImageController extends Controller
{
    public function __invoke(string $seed): Response
    {
        $svg = IdentityImage::svg($seed);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
