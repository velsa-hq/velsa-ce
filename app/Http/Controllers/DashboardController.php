<?php

namespace App\Http\Controllers;

use App\Dashboard\DashboardTile;
use App\Dashboard\DashboardTileRegistry;
use App\Dashboard\Tiles\QuickLinksTile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-user dashboard portlets. Registry is the catalog; user prefs (JSON
 * array of tile keys) decide which render and in what order. First-time
 * users get the default tile set.
 */
class DashboardController extends Controller
{
    public function index(Request $request, DashboardTileRegistry $registry): Response
    {
        /** @var User $user */
        $user = $request->user();

        // visible if no permission required, or the user holds it at any venue
        $canSee = fn (DashboardTile $tile): bool => $tile->permission() === null
            || $user->hasVenuePermission($tile->permission());

        $selectedKeys = array_values(array_filter(
            $this->resolveSelectedKeys($user, $registry),
            fn (string $key) => ($tile = $registry->get($key)) !== null && $canSee($tile),
        ));

        $tiles = collect($selectedKeys)
            ->map(fn (string $key) => $registry->get($key))
            ->filter(fn (?DashboardTile $tile) => $tile !== null)
            ->map(fn (DashboardTile $tile) => [
                'key' => $tile->key(),
                'label' => $tile->label(),
                'component' => $tile->component(),
                'column_span' => $tile->columnSpan(),
                'data' => $tile->render($user),
            ])
            ->values()
            ->all();

        $catalog = collect($registry->all())
            ->filter(fn (DashboardTile $tile) => $canSee($tile))
            ->map(fn (DashboardTile $tile) => [
                'key' => $tile->key(),
                'label' => $tile->label(),
                'description' => $tile->description(),
                'column_span' => $tile->columnSpan(),
            ])
            ->values()
            ->all();

        return Inertia::render('dashboard', [
            'tiles' => $tiles,
            'catalog' => $catalog,
            'selected_keys' => $selectedKeys,
            // previous successful sign-in (AC-9 / APSC-DV-000580); null on first login
            'last_sign_in_at' => $user->previous_login_at?->toIso8601String(),
        ]);
    }

    public function updatePreferences(
        Request $request,
        DashboardTileRegistry $registry,
    ): RedirectResponse {
        $data = $request->validate([
            'tiles' => ['present', 'array'],
            'tiles.*' => ['string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        // drop unknown tile keys; registry is the canonical catalog
        $known = array_keys($registry->all());
        $valid = array_values(array_unique(array_filter(
            $data['tiles'],
            fn (string $key) => in_array($key, $known, true),
        )));

        $prefs = is_array($user->dashboard_preferences) ? $user->dashboard_preferences : [];
        $prefs['tiles'] = $valid;

        $user->forceFill(['dashboard_preferences' => $prefs])->save();

        return to_route('dashboard');
    }

    public function updateQuickLinks(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'keys' => ['present', 'array'],
            'keys.*' => ['string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $known = $this->knownQuickLinkKeys();
        $valid = array_values(array_unique(array_filter(
            $data['keys'],
            fn (string $key) => in_array($key, $known, true),
        )));

        $prefs = is_array($user->dashboard_preferences) ? $user->dashboard_preferences : [];
        $prefs['quick_link_keys'] = $valid;

        $user->forceFill(['dashboard_preferences' => $prefs])->save();

        return to_route('dashboard');
    }

    /**
     * @return list<string>
     */
    protected function knownQuickLinkKeys(): array
    {
        $tile = app(QuickLinksTile::class);
        $keys = [];
        foreach ($tile->availableGroups() as $group) {
            foreach ($group['items'] as $item) {
                $keys[] = $item['key'];
            }
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    protected function resolveSelectedKeys(User $user, DashboardTileRegistry $registry): array
    {
        $prefs = $user->dashboard_preferences;

        if (is_array($prefs) && isset($prefs['tiles']) && is_array($prefs['tiles'])) {
            return array_values(array_filter(
                $prefs['tiles'],
                fn ($key) => is_string($key) && $registry->get($key) !== null,
            ));
        }

        return $registry->defaultTileKeys();
    }
}
