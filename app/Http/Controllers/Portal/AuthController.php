<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Mail\ExhibitorPortalLink;
use App\Models\Exhibitor;
use App\Services\MagicLinkService;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function access(): Response
    {
        return Inertia::render('portal/access');
    }

    /**
     * Email a magic link. Response is identical on hit or miss so the
     * endpoint can't enumerate exhibitors.
     */
    public function requestLink(Request $request, MagicLinkService $service, SystemSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $exhibitor = Exhibitor::query()
            ->whereNotNull('email')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])
            ->first();

        if ($exhibitor !== null) {
            $ttlDays = (int) $settings->get('security.portal_magic_link_ttl_days', MagicLinkService::DEFAULT_TTL_DAYS);
            $token = $service->issue($exhibitor, $ttlDays);
            Mail::to($exhibitor->email)->send(new ExhibitorPortalLink(
                $exhibitor,
                URL::to($service->loginUrl($token)),
                $ttlDays,
            ));
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'If that email is registered as an exhibitor, we just sent an access link. Check your inbox.',
        ]);
    }

    public function login(string $token, MagicLinkService $service, Request $request): RedirectResponse
    {
        $exhibitor = $service->verify($token);

        if ($exhibitor === null) {
            return redirect('/')->with('toast', [
                'type' => 'error',
                'message' => 'That portal link is invalid or has expired. Ask your event coordinator for a fresh one.',
            ]);
        }

        // consume now so a copied link can't be replayed
        $service->consume($exhibitor);

        Auth::guard('exhibitor')->login($exhibitor);
        $request->session()->regenerate();

        return redirect()->route('portal.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('exhibitor')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('toast', [
            'type' => 'success',
            'message' => 'Signed out of the exhibitor portal.',
        ]);
    }
}
