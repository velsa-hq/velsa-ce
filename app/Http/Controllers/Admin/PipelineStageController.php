<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Pipeline\PipelineStageConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Editor for pipeline-stage labels and default probabilities. Labels editable
 * on every stage; probabilities only on open stages (Won/Lost fixed at 100%/0%).
 */
class PipelineStageController extends Controller
{
    public function index(PipelineStageConfig $stages): Response
    {
        return Inertia::render('admin/pipeline-stages/index', [
            'stages' => $stages->all(),
        ]);
    }

    public function update(Request $request, PipelineStageConfig $stages): RedirectResponse
    {
        $data = $request->validate([
            'stages' => ['required', 'array'],
            'stages.*.label' => ['required', 'string', 'max:50'],
            'stages.*.probability' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        $stages->save($data['stages'], $request->user()?->id);

        return to_route('admin.pipeline-stages.index')
            ->with('toast', ['type' => 'success', 'message' => 'Pipeline stages saved.']);
    }
}
