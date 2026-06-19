<?php

namespace App\Reports\Datasources;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Reports\DatasourceField;
use App\Reports\ReportDatasource;
use Illuminate\Database\Eloquent\Builder;

class BookingsDatasource extends DatasourceDescriptor
{
    public function key(): ReportDatasource
    {
        return ReportDatasource::Bookings;
    }

    public function label(): string
    {
        return 'Bookings';
    }

    public function query(): Builder
    {
        return Booking::query()
            ->leftJoin('venues', 'venues.id', '=', 'bookings.venue_id')
            ->leftJoin('clients', 'clients.id', '=', 'bookings.client_id')
            ->select('bookings.*');
    }

    /**
     * @return array<string, DatasourceField>
     */
    public function fields(): array
    {
        return [
            'reference' => new DatasourceField('reference', 'Reference', 'string', 'bookings.reference'),
            'name' => new DatasourceField('name', 'Event name', 'string', 'bookings.name'),
            'kind' => new DatasourceField('kind', 'Kind', 'string', 'bookings.kind'),
            'status' => new DatasourceField(
                'status', 'Status', 'enum', 'bookings.status',
                options: array_map(
                    fn (BookingStatus $s) => ['value' => $s->value, 'label' => ucfirst(str_replace('_', ' ', $s->value))],
                    BookingStatus::cases(),
                ),
            ),
            'venue_name' => new DatasourceField('venue_name', 'Venue', 'string', 'venues.name'),
            'client_name' => new DatasourceField('client_name', 'Client', 'string', 'clients.name'),
            'start_at' => new DatasourceField('start_at', 'Start date', 'date', 'bookings.start_at'),
            'end_at' => new DatasourceField('end_at', 'End date', 'date', 'bookings.end_at'),
            'total_cents' => new DatasourceField('total_cents', 'Total ($)', 'money', 'bookings.total_cents', aggregatable: true),
            'attendance_estimate' => new DatasourceField('attendance_estimate', 'Attendance est.', 'number', 'bookings.attendance_estimate', aggregatable: true),
            'attendance_actual' => new DatasourceField('attendance_actual', 'Attendance actual', 'number', 'bookings.attendance_actual', aggregatable: true),
            'cancelled_at' => new DatasourceField('cancelled_at', 'Cancelled at', 'date', 'bookings.cancelled_at'),
        ];
    }
}
