<?php

namespace App\Http\Requests;

use App\Enums\ExportFormat;
use App\Services\Accounting\ExportSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportTemplateUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $formats = array_map(fn (ExportFormat $f) => $f->value, ExportFormat::cases());
        $sources = array_column(ExportSource::options(), 'value');
        $template = $this->route('template');

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('export_templates', 'slug')->ignore($template?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'format' => ['required', Rule::in($formats)],
            'delimiter' => ['nullable', 'string', 'max:4'],
            'quote_char' => ['nullable', 'string', 'max:2'],
            'line_ending' => ['required', Rule::in(['lf', 'crlf'])],
            'encoding' => ['required', 'string', 'max:32'],
            'include_header' => ['boolean'],
            'include_footer' => ['boolean'],
            'is_default' => ['boolean'],
            'file_extension' => ['required', 'string', 'max:8'],

            'columns' => ['required', 'array', 'min:1'],
            'columns.*.label' => ['required', 'string', 'max:80'],
            'columns.*.source' => ['required', Rule::in($sources)],
            'columns.*.format_mask' => ['nullable', 'string', 'max:80'],
            'columns.*.default_value' => ['nullable', 'string', 'max:255'],
            'columns.*.width' => ['nullable', 'integer', 'min:1', 'max:500'],
            'columns.*.align' => ['nullable', Rule::in(['left', 'right'])],
            'columns.*.pad_char' => ['nullable', 'string', 'max:1'],
        ];
    }

    public function prepareForValidation(): void
    {
        foreach (['include_header', 'include_footer', 'is_default'] as $bool) {
            if ($this->has($bool)) {
                $this->merge([$bool => $this->boolean($bool)]);
            }
        }
    }

    public function preparedForModel(): array
    {
        $data = $this->validated();
        $data['line_ending'] = ($data['line_ending'] ?? 'lf') === 'crlf' ? "\r\n" : "\n";

        return $data;
    }
}
