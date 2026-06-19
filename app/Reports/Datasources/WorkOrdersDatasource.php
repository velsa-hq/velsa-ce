<?php

namespace App\Reports\Datasources;

use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use App\Reports\DatasourceField;
use App\Reports\ReportDatasource;
use Illuminate\Database\Eloquent\Builder;

class WorkOrdersDatasource extends DatasourceDescriptor
{
    public function key(): ReportDatasource
    {
        return ReportDatasource::WorkOrders;
    }

    public function label(): string
    {
        return 'Work orders';
    }

    public function query(): Builder
    {
        return WorkOrder::query()
            ->leftJoin('venues', 'venues.id', '=', 'work_orders.venue_id')
            ->leftJoin('users as assignees', 'assignees.id', '=', 'work_orders.assigned_to_user_id')
            ->select('work_orders.*');
    }

    public function fields(): array
    {
        return [
            'title' => new DatasourceField('title', 'Title', 'string', 'work_orders.title'),
            'status' => new DatasourceField(
                'status', 'Status', 'enum', 'work_orders.status',
                options: array_map(
                    fn (WorkOrderStatus $s) => ['value' => $s->value, 'label' => ucfirst(str_replace('_', ' ', $s->value))],
                    WorkOrderStatus::cases(),
                ),
            ),
            'priority' => new DatasourceField('priority', 'Priority', 'string', 'work_orders.priority'),
            'category' => new DatasourceField('category', 'Category', 'string', 'work_orders.category'),
            'venue_name' => new DatasourceField('venue_name', 'Venue', 'string', 'venues.name'),
            'assignee_name' => new DatasourceField('assignee_name', 'Assigned to', 'string', 'assignees.name'),
            'scheduled_for' => new DatasourceField('scheduled_for', 'Scheduled', 'date', 'work_orders.scheduled_for'),
            'completed_at' => new DatasourceField('completed_at', 'Completed', 'date', 'work_orders.completed_at'),
            'created_at' => new DatasourceField('created_at', 'Created', 'date', 'work_orders.created_at'),
        ];
    }
}
