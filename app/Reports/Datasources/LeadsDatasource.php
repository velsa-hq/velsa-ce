<?php

namespace App\Reports\Datasources;

use App\Enums\LeadStage;
use App\Models\Lead;
use App\Reports\DatasourceField;
use App\Reports\ReportDatasource;
use Illuminate\Database\Eloquent\Builder;

class LeadsDatasource extends DatasourceDescriptor
{
    public function key(): ReportDatasource
    {
        return ReportDatasource::Leads;
    }

    public function label(): string
    {
        return 'Sales leads';
    }

    public function query(): Builder
    {
        return Lead::query()
            ->leftJoin('clients', 'clients.id', '=', 'leads.client_id')
            ->leftJoin('venues', 'venues.id', '=', 'leads.venue_id')
            ->leftJoin('users as owners', 'owners.id', '=', 'leads.owner_user_id')
            ->select('leads.*');
    }

    public function fields(): array
    {
        return [
            'name' => new DatasourceField('name', 'Lead name', 'string', 'leads.name'),
            'stage' => new DatasourceField(
                'stage', 'Stage', 'enum', 'leads.stage',
                options: array_map(
                    fn (LeadStage $s) => ['value' => $s->value, 'label' => ucfirst(str_replace('_', ' ', $s->value))],
                    LeadStage::cases(),
                ),
            ),
            'client_name' => new DatasourceField('client_name', 'Client', 'string', 'clients.name'),
            'venue_name' => new DatasourceField('venue_name', 'Venue', 'string', 'venues.name'),
            'owner_name' => new DatasourceField('owner_name', 'Owner', 'string', 'owners.name'),
            'estimated_value_cents' => new DatasourceField('estimated_value_cents', 'Estimated value ($)', 'money', 'leads.estimated_value_cents', aggregatable: true),
            'probability_pct' => new DatasourceField('probability_pct', 'Probability %', 'number', 'leads.probability_pct', aggregatable: true),
            'expected_close_date' => new DatasourceField('expected_close_date', 'Expected close', 'date', 'leads.expected_close_date'),
            'source' => new DatasourceField('source', 'Source', 'string', 'leads.source'),
            'created_at' => new DatasourceField('created_at', 'Created', 'date', 'leads.created_at'),
        ];
    }
}
