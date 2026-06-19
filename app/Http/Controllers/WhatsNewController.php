<?php

namespace App\Http\Controllers;

use App\Services\ReleaseNotes;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsNewController extends Controller
{
    public function __construct(protected ReleaseNotes $releaseNotes) {}

    public function index(Request $request): Response
    {
        $releases = $this->releaseNotes->all();

        // opening the feed clears the unread indicator
        $user = $request->user();
        if ($user !== null) {
            $user->whats_new_seen_at = now();
            $user->save();
        }

        return Inertia::render('whats-new/index', [
            'releases' => $releases->all(),
        ]);
    }
}
