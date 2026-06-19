<?php

namespace App\Http\Controllers\Admin;

use App\Dashboard\DashboardTileRegistry;
use App\Http\Controllers\Controller;
use App\Services\SystemSettings\SettingDefinition;
use App\Services\SystemSettings\SystemSettings;
use App\Services\SystemSettings\SystemSettingsRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Single admin surface for every tunable system setting. The registry is the
 * authoritative catalog; this controller shuttles values between it and the
 * UI. Secrets never reach the client in clear - the UI shows a masked
 * placeholder, and unchanged secrets come back as a sentinel the controller
 * refuses to overwrite.
 */
class SystemSettingController extends Controller
{
    public const KEEP_CURRENT = '__KEEP_CURRENT__';

    /**
     * Outbound-integration URL settings whose host must stay within the
     * provider's own domains. Without this an admin could repoint them at an
     * arbitrary host and turn the server into an SSRF proxy (CWE-918). Value
     * must be an https URL on a listed host (exact or subdomain).
     *
     * @var array<string, list<string>>
     */
    private const URL_HOST_ALLOWLIST = [
        'integrations.docusign.base_uri' => ['docusign.net', 'docusign.com'],
        'integrations.docusign.oauth_base' => ['docusign.net', 'docusign.com'],
    ];

    public function index(
        SystemSettings $settings,
        SystemSettingsRegistry $registry,
        DashboardTileRegistry $tiles,
    ): Response {
        return Inertia::render('admin/system-settings/index', [
            'categories' => $this->shapeCategories($registry, $this->dynamicChoices($tiles)),
            'values' => $settings->allValuesMasked(),
        ]);
    }

    /**
     * Choice lists for multiselect settings whose options are sourced
     * from a live catalog rather than declared statically.
     *
     * @return array<string, list<array{value: string, label: string}>>
     */
    protected function dynamicChoices(DashboardTileRegistry $tiles): array
    {
        return [
            'dashboard_tiles' => array_values(array_map(
                fn ($tile) => ['value' => $tile->key(), 'label' => $tile->label()],
                $tiles->all(),
            )),
        ];
    }

    public function update(
        Request $request,
        SystemSettings $settings,
        SystemSettingsRegistry $registry,
    ): RedirectResponse {
        $input = $request->validate([
            'values' => ['required', 'array'],
        ])['values'];

        // validate up front so a bad value fails the whole save (no partial
        // write): URL hosts, and integers against their declared min/max
        // (otherwise only client-side hints)
        foreach ($input as $key => $value) {
            if (isset(self::URL_HOST_ALLOWLIST[$key])
                && is_string($value) && $value !== '' && $value !== self::KEEP_CURRENT) {
                $this->assertAllowedUrlHost($key, $value, self::URL_HOST_ALLOWLIST[$key]);
            }

            // Meilisearch host is deployment-specific (no domain allowlist),
            // but must never be repointed at the link-local / metadata range -
            // that turns search calls into an SSRF on the credentials endpoint
            if ($key === 'integrations.meilisearch.host'
                && is_string($value) && $value !== '' && $value !== self::KEEP_CURRENT) {
                $this->assertNotLinkLocalUrl($key, $value);
            }

            $def = $registry->get($key);
            if ($def?->type === 'integer' && $value !== '' && $value !== null && $value !== self::KEEP_CURRENT) {
                $this->assertIntegerInRange($key, $value, $def);
            }
        }

        $userId = $request->user()?->id;

        foreach ($input as $key => $value) {
            $def = $registry->get($key);
            if ($def === null) {
                continue;
            }
            // a secret sent back as the sentinel means "no change" (masked
            // placeholder, not overwritten); don't clobber the stored value
            if ($def->isSecret && $value === self::KEEP_CURRENT) {
                continue;
            }
            // empty string on a non-secret means "clear back to env/default";
            // pass null so the row is removed
            if ($value === '') {
                $value = null;
            }

            $settings->set($key, $value, $userId);
        }

        return to_route('admin.system-settings.index')
            ->with('toast', ['type' => 'success', 'message' => 'Settings saved.']);
    }

    /**
     * Reject an integer setting outside its declared min/max. Without this the
     * bounds are only client-side hints - an admin PUTting the form could set,
     * e.g., a magic-link TTL of 99999 (non-expiring links) or 0 (DoS).
     */
    private function assertIntegerInRange(string $key, mixed $value, SettingDefinition $def): void
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        if ($int === false) {
            throw ValidationException::withMessages(["values.{$key}" => 'Must be a whole number.']);
        }

        $options = is_array($def->options) ? $def->options : [];
        $min = $options['min'] ?? null;
        $max = $options['max'] ?? null;

        if (($min !== null && $int < $min) || ($max !== null && $int > $max)) {
            $range = $min !== null && $max !== null
                ? "between {$min} and {$max}"
                : ($min !== null ? "at least {$min}" : "at most {$max}");
            throw ValidationException::withMessages(["values.{$key}" => "Must be {$range}."]);
        }
    }

    /**
     * Reject an integration URL whose scheme isn't https or whose host is
     * outside the provider's domains - the SSRF guard (CWE-918).
     *
     * @param  list<string>  $allowedHosts
     */
    private function assertAllowedUrlHost(string $key, string $url, array $allowedHosts): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        $hostAllowed = is_string($host) && array_filter(
            $allowedHosts,
            fn (string $allowed): bool => $host === $allowed || str_ends_with($host, '.'.$allowed),
        ) !== [];

        if ($scheme !== 'https' || ! $hostAllowed) {
            throw ValidationException::withMessages([
                "values.{$key}" => 'Must be an https URL on '.implode(' or ', $allowedHosts).'.',
            ]);
        }
    }

    /**
     * SSRF guard for an internal-service URL with no fixed domain allowlist
     * (e.g. Meilisearch). Require http(s) and reject a literal link-local
     * address (169.254.0.0/16 - the cloud-metadata endpoint). Legitimate hosts
     * (localhost, private DNS, RFC1918) are unaffected. (CWE-918.)
     */
    private function assertNotLinkLocalUrl(string $key, string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            throw ValidationException::withMessages([
                "values.{$key}" => 'Must be an http(s) URL with a host.',
            ]);
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false && str_starts_with($host, '169.254.')) {
            throw ValidationException::withMessages([
                "values.{$key}" => 'Host may not be a link-local / cloud-metadata address.',
            ]);
        }
    }

    /**
     * Reshape the registry into a category -> settings list for the React
     * payload, with labels + ordering. Multiselect `choices` are resolved from
     * the dynamic-choice map via each setting's `options.source`.
     *
     * @param  array<string, list<array{value: string, label: string}>>  $dynamicChoices
     * @return list<array{key: string, label: string, settings: list<array<string, mixed>>}>
     */
    protected function shapeCategories(SystemSettingsRegistry $registry, array $dynamicChoices = []): array
    {
        $labels = [
            'branding' => 'Branding',
            'defaults' => 'Defaults',
            'operations' => 'Operations',
            'security' => 'Security',
            'support' => 'Support',
            'compliance' => 'Compliance',
            'integrations' => 'Integrations',
        ];

        $shaped = [];
        foreach ($registry->byCategory() as $category => $defs) {
            $shaped[] = [
                'key' => $category,
                'label' => $labels[$category] ?? ucfirst($category),
                'settings' => array_map(
                    function (SettingDefinition $d) use ($dynamicChoices) {
                        $options = $d->options;
                        $source = is_array($options) ? ($options['source'] ?? null) : null;
                        if (is_array($options) && is_string($source) && isset($dynamicChoices[$source])) {
                            $options = array_merge($options, ['choices' => $dynamicChoices[$source]]);
                        }

                        return [
                            'key' => $d->key,
                            'label' => $d->label,
                            'description' => $d->description,
                            'type' => $d->type,
                            'is_secret' => $d->isSecret,
                            'options' => $options,
                            'has_env_fallback' => $d->envKey !== null,
                            'group' => $d->group,
                            'group_label' => $d->groupLabel,
                            'gates_group' => $d->gatesGroup,
                        ];
                    },
                    $defs,
                ),
            ];
        }

        return $shaped;
    }
}
