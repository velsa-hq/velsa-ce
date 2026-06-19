<?php

namespace App\Reports\Datasources;

use App\Enums\DunningStage;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Reports\DatasourceField;
use App\Reports\ReportDatasource;
use Illuminate\Database\Eloquent\Builder;

class InvoicesDatasource extends DatasourceDescriptor
{
    public function key(): ReportDatasource
    {
        return ReportDatasource::Invoices;
    }

    public function label(): string
    {
        return 'Invoices';
    }

    public function query(): Builder
    {
        return Invoice::query()->select('invoices.*');
    }

    public function fields(): array
    {
        return [
            'number' => new DatasourceField('number', 'Invoice #', 'string', 'invoices.number'),
            'status' => new DatasourceField(
                'status', 'Status', 'enum', 'invoices.status',
                options: array_map(
                    fn (InvoiceStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                    InvoiceStatus::cases(),
                ),
            ),
            'dunning_stage' => new DatasourceField(
                'dunning_stage', 'Dunning stage', 'enum', 'invoices.dunning_stage',
                options: array_map(
                    fn (DunningStage $s) => ['value' => $s->value, 'label' => $s->label()],
                    DunningStage::cases(),
                ),
            ),
            'invoiceable_type' => new DatasourceField('invoiceable_type', 'Source type', 'string', 'invoices.invoiceable_type'),
            'subtotal_cents' => new DatasourceField('subtotal_cents', 'Subtotal ($)', 'money', 'invoices.subtotal_cents', aggregatable: true),
            'tax_cents' => new DatasourceField('tax_cents', 'Tax ($)', 'money', 'invoices.tax_cents', aggregatable: true),
            'total_cents' => new DatasourceField('total_cents', 'Total ($)', 'money', 'invoices.total_cents', aggregatable: true),
            'paid_cents' => new DatasourceField('paid_cents', 'Paid ($)', 'money', 'invoices.paid_cents', aggregatable: true),
            'issued_on' => new DatasourceField('issued_on', 'Issued', 'date', 'invoices.issued_on'),
            'due_on' => new DatasourceField('due_on', 'Due', 'date', 'invoices.due_on'),
            'paid_at' => new DatasourceField('paid_at', 'Paid at', 'date', 'invoices.paid_at'),
        ];
    }
}
