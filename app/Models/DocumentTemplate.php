<?php

namespace App\Models;

use App\Enums\TemplateKind;
use App\Models\Concerns\IsVenueScoped;
use Database\Factories\DocumentTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends Model
{
    /** @use HasFactory<DocumentTemplateFactory> */
    use HasFactory, IsVenueScoped;

    protected $fillable = [
        'venue_id',
        'kind',
        'name',
        'version',
        'body_html',
        'variables_json',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'kind' => TemplateKind::class,
            'version' => 'integer',
            'variables_json' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'template_id');
    }

    // venue-specific template, falling back to the global default (venue_id NULL)
    public function scopeForVenueKind(Builder $query, ?int $venueId, TemplateKind $kind): Builder
    {
        return $query->where('kind', $kind->value)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('venue_id', $venueId)->orWhereNull('venue_id'))
            ->orderByRaw('venue_id IS NULL') // venue-specific first
            ->orderByDesc('version');
    }

    /**
     * Render body_html, substituting {{path.to.value}} from $vars.
     *
     * Substituted values are HTML-escaped: body_html is trusted admin markup
     * but $vars are user-controlled and output via dangerouslySetInnerHTML,
     * so escaping prevents stored XSS (STIG APSC-DV-002490 / NIST SI-10).
     *
     * @param  array<string, mixed>  $vars
     */
    public function render(array $vars): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            fn ($matches) => htmlspecialchars(
                (string) data_get($vars, $matches[1], ''),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8',
            ),
            $this->body_html,
        );
    }
}
