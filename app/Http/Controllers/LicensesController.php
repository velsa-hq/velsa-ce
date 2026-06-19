<?php

namespace App\Http\Controllers;

use App\Services\LicenseRegistry;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public attribution page for bundled third-party packages; satisfies the
 * preserve-copyright-notice obligation of MIT/BSD/Apache/MPL/OFL. Auth-free
 * so attribution stays accessible.
 */
class LicensesController extends Controller
{
    public function __construct(protected LicenseRegistry $registry) {}

    public function show(): Response
    {
        return Inertia::render('licenses', [
            'app_name' => config('app.name'),
            'php' => $this->registry->php(),
            'js' => $this->registry->js(),
        ]);
    }
}
