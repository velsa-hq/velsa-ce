<?php

namespace App\Http\Controllers;

use App\Services\Handbook;
use Inertia\Inertia;
use Inertia\Response;

class DocsController extends Controller
{
    public function __construct(protected Handbook $handbook) {}

    public function index(): Response
    {
        $nav = $this->handbook->navTree();
        $first = $this->handbook->all()->first();

        return Inertia::render('docs/index', [
            'nav' => $nav,
            'first_slug' => $first['slug'] ?? null,
        ]);
    }

    public function show(string $slug): Response
    {
        $doc = $this->handbook->find($slug);

        abort_if($doc === null, 404);

        return Inertia::render('docs/show', [
            'nav' => $this->handbook->navTree(),
            'doc' => $doc,
        ]);
    }
}
