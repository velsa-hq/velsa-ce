<?php

namespace App\Reports\Datasources;

use App\Enums\ExhibitorOrderStatus;
use App\Models\ExhibitorOrder;
use App\Reports\DatasourceField;
use App\Reports\ReportDatasource;
use Illuminate\Database\Eloquent\Builder;

class ExhibitorOrdersDatasource extends DatasourceDescriptor
{
    public function key(): ReportDatasource
    {
        return ReportDatasource::ExhibitorOrders;
    }

    public function label(): string
    {
        return 'Exhibitor orders';
    }

    public function query(): Builder
    {
        return ExhibitorOrder::query()
            ->leftJoin('exhibitors', 'exhibitors.id', '=', 'exhibitor_orders.exhibitor_id')
            ->leftJoin('exhibitor_events', 'exhibitor_events.id', '=', 'exhibitors.exhibitor_event_id')
            ->select('exhibitor_orders.*');
    }

    public function fields(): array
    {
        return [
            'order_number' => new DatasourceField('order_number', 'Order #', 'string', 'exhibitor_orders.order_number'),
            'company_name' => new DatasourceField('company_name', 'Company', 'string', 'exhibitors.company_name'),
            'event_name' => new DatasourceField('event_name', 'Event', 'string', 'exhibitor_events.name'),
            'status' => new DatasourceField(
                'status', 'Status', 'enum', 'exhibitor_orders.status',
                options: array_map(
                    fn (ExhibitorOrderStatus $s) => ['value' => $s->value, 'label' => ucfirst(str_replace('_', ' ', $s->value))],
                    ExhibitorOrderStatus::cases(),
                ),
            ),
            'subtotal_cents' => new DatasourceField('subtotal_cents', 'Subtotal ($)', 'money', 'exhibitor_orders.subtotal_cents', aggregatable: true),
            'tax_cents' => new DatasourceField('tax_cents', 'Tax ($)', 'money', 'exhibitor_orders.tax_cents', aggregatable: true),
            'total_cents' => new DatasourceField('total_cents', 'Total ($)', 'money', 'exhibitor_orders.total_cents', aggregatable: true),
            'paid_cents' => new DatasourceField('paid_cents', 'Paid ($)', 'money', 'exhibitor_orders.paid_cents', aggregatable: true),
            'placed_at' => new DatasourceField('placed_at', 'Placed at', 'date', 'exhibitor_orders.placed_at'),
        ];
    }
}
