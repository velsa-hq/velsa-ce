<?php

namespace App\Reports;

/**
 * Datasources for the ad-hoc report builder. Each maps to a descriptor
 * in App\Reports\Datasources registered in DatasourceRegistry::all().
 */
enum ReportDatasource: string
{
    case Bookings = 'bookings';
    case ExhibitorOrders = 'exhibitor_orders';
    case Leads = 'leads';
    case WorkOrders = 'work_orders';
    case JournalEntries = 'journal_entries';
    case Invoices = 'invoices';

    public function label(): string
    {
        return match ($this) {
            self::Bookings => 'Bookings',
            self::ExhibitorOrders => 'Exhibitor orders',
            self::Leads => 'Sales leads',
            self::WorkOrders => 'Work orders',
            self::JournalEntries => 'Journal entries',
            self::Invoices => 'Invoices',
        };
    }
}
