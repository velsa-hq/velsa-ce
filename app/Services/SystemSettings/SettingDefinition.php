<?php

namespace App\Services\SystemSettings;

/**
 * One entry in the SystemSettings registry.
 */
class SettingDefinition
{
    public function __construct(
        public string $key,
        public string $category,
        public string $label,
        public string $description = '',
        public string $type = 'string',
        public mixed $default = null,
        public ?string $envKey = null,
        public bool $isSecret = false,
        /** @var array<string, mixed>|null */
        public ?array $options = null,
        public ?string $group = null,
        public ?string $groupLabel = null,
        // gate for the group: siblings stay hidden until this is enabled (boolean settings only)
        public bool $gatesGroup = false,
    ) {}
}
