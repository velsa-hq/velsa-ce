<?php

namespace App\Reports;

use App\Reports\Datasources\BookingsDatasource;
use App\Reports\Datasources\DatasourceDescriptor;
use App\Reports\Datasources\ExhibitorOrdersDatasource;
use App\Reports\Datasources\InvoicesDatasource;
use App\Reports\Datasources\JournalEntriesDatasource;
use App\Reports\Datasources\LeadsDatasource;
use App\Reports\Datasources\WorkOrdersDatasource;
use RuntimeException;

/**
 * Maps ReportDatasource enum cases to descriptor classes. A new datasource
 * needs an enum case, a descriptor class, and one line in all().
 */
class DatasourceRegistry
{
    /**
     * @return array<string, DatasourceDescriptor> keyed by enum value
     */
    public function all(): array
    {
        return [
            ReportDatasource::Bookings->value => app(BookingsDatasource::class),
            ReportDatasource::ExhibitorOrders->value => app(ExhibitorOrdersDatasource::class),
            ReportDatasource::Leads->value => app(LeadsDatasource::class),
            ReportDatasource::WorkOrders->value => app(WorkOrdersDatasource::class),
            ReportDatasource::JournalEntries->value => app(JournalEntriesDatasource::class),
            ReportDatasource::Invoices->value => app(InvoicesDatasource::class),
        ];
    }

    public function get(ReportDatasource|string $key): DatasourceDescriptor
    {
        $value = $key instanceof ReportDatasource ? $key->value : $key;
        $descriptor = $this->all()[$value] ?? null;
        if ($descriptor === null) {
            throw new RuntimeException("Unknown datasource: {$value}");
        }

        return $descriptor;
    }

    /**
     * Every datasource with label, fields, aggregations and operators.
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalog(): array
    {
        $out = [];
        foreach ($this->all() as $value => $descriptor) {
            $fields = [];
            foreach ($descriptor->fields() as $field) {
                $fields[] = [
                    'key' => $field->key,
                    'label' => $field->label,
                    'type' => $field->type,
                    'options' => $field->options,
                    'aggregatable' => $field->aggregatable,
                    'operators' => $field->operators(),
                ];
            }
            $out[] = [
                'value' => $value,
                'label' => $descriptor->label(),
                'fields' => $fields,
                'aggregations' => $descriptor->aggregations(),
            ];
        }

        return $out;
    }
}
