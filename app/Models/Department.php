<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\IsTaxonomy;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * User-definable taxonomy of operations departments for outline items.
 *
 * Outline items reference a department by the `key` slug, not a foreign
 * key, so renaming/removing a department doesn't break existing rows.
 * `is_system` rows are seeded defaults and protected from deletion.
 *
 * @property string|null $default_role
 */
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use Auditable, HasFactory, IsTaxonomy;

    /**
     * Allowed chip-color palette keys; map to safelisted Tailwind classes
     * in resources/js/lib/department-colors.ts. Shared by admin form + validation.
     *
     * @var list<string>
     */
    public const COLORS = [
        'slate', 'blue', 'indigo', 'violet', 'purple', 'fuchsia',
        'pink', 'rose', 'orange', 'amber', 'emerald', 'teal', 'sky', 'cyan',
    ];

    protected $fillable = [
        'key',
        'label',
        'color',
        'default_role',
        'sort_order',
        'is_active',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    // joined on `key` slug, not a foreign key
    public function outlineItems(): HasMany
    {
        return $this->hasMany(OutlineItem::class, 'department', 'key');
    }
}
