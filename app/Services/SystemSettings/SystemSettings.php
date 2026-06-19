<?php

namespace App\Services\SystemSettings;

use App\Models\SystemSetting;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Throwable;

/**
 * Read + write API for system settings.
 *
 * Read resolution order: DB row (decrypted if secret) -> .env (envKey) -> default.
 * Reads cached forever; writes invalidate.
 */
class SystemSettings
{
    protected const CACHE_KEY = 'system_settings.v1';

    public function __construct(
        protected SystemSettingsRegistry $registry,
    ) {}

    /**
     * Effective value, coerced to the definition's type.
     */
    public function get(string $key, mixed $fallback = null): mixed
    {
        $def = $this->registry->get($key);
        if ($def === null) {
            return $fallback;
        }

        $values = $this->loaded();
        $raw = $values[$key] ?? null;

        if ($raw === null && $def->envKey !== null) {
            // Env::get works after config:cache, bare env() does not
            $envValue = Env::get($def->envKey);
            $raw = $envValue === false ? null : (string) $envValue;
        }
        if ($raw === null || $raw === '') {
            return $def->default ?? $fallback;
        }

        return $this->coerce($raw, $def->type);
    }

    /**
     * Persist a value, encrypting secrets. Throws on an unregistered key.
     */
    public function set(string $key, mixed $value, ?int $userId = null): SystemSetting
    {
        $def = $this->registry->get($key);
        if ($def === null) {
            throw new RuntimeException("Unknown system setting: {$key}");
        }

        $stored = $this->stringifyForStorage($value, $def);

        $row = SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $stored,
                'is_secret' => $def->isSecret,
                'updated_by_user_id' => $userId,
            ],
        );

        $this->forget();

        return $row;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Raw DB value (decrypted if secret) with no env/default fallback.
     * ConfigOverlay uses this so env-only settings aren't re-applied to config.
     */
    public function getStored(string $key): ?string
    {
        $def = $this->registry->get($key);
        if ($def === null) {
            return null;
        }

        $values = $this->loaded();

        return $values[$key] ?? null;
    }

    /**
     * Every defined setting as a key->value map.
     *
     * @return array<string, mixed>
     */
    public function allValues(): array
    {
        $out = [];
        foreach ($this->registry->all() as $key => $def) {
            $out[$key] = $this->get($key);
        }

        return $out;
    }

    /**
     * Like allValues() but with present secrets replaced by a mask, safe to ship to the client.
     *
     * @return array<string, mixed>
     */
    public function allValuesMasked(): array
    {
        $out = [];
        foreach ($this->registry->all() as $key => $def) {
            $value = $this->get($key);
            if ($def->isSecret && $value !== null && $value !== '') {
                $value = '••••••••';
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /** @return array<string, string|null> */
    protected function loaded(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $map = [];
            foreach (SystemSetting::query()->get() as $row) {
                $map[$row->key] = $this->decryptIfNeeded($row);
            }

            return $map;
        });
    }

    protected function decryptIfNeeded(SystemSetting $row): ?string
    {
        if (! $row->is_secret || $row->value === null || $row->value === '') {
            return $row->value;
        }

        try {
            return Crypt::decryptString($row->value);
        } catch (Throwable) {
            // undecryptable with the current app key (rotated or hand-edited); fall through
            return null;
        }
    }

    protected function stringifyForStorage(mixed $value, SettingDefinition $def): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $stringified = match ($def->type) {
            'integer' => (string) (int) $value,
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };

        if ($def->isSecret) {
            return Crypt::encryptString($stringified);
        }

        return $stringified;
    }

    protected function coerce(string $raw, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $raw,
            'boolean' => in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true),
            default => $raw,
        };
    }
}
