<?php

namespace App\Services\Import\Importers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Space;
use App\Models\Venue;
use App\Services\Import\AbstractImporter;
use App\Services\Import\ImportField;
use App\Services\Import\ImportRowResult;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Imports bookings. Each row is one booking and, optionally, one space
 * placement (BookingSpace) on a named space within the venue. FKs resolve by
 * name: venue (required), client (optional), space (optional). Status defaults
 * to Definite for historical migrations.
 *
 * Multi-space bookings aren't expressed in one row; import the primary space
 * and add the rest in-app.
 *
 * Save-time overlap rules still apply: a Definite/Completed row colliding with
 * a blocking booking on the same space + window is rejected, not double-booked.
 */
class BookingImporter extends AbstractImporter
{
    public function key(): string
    {
        return 'bookings';
    }

    public function label(): string
    {
        return 'Bookings';
    }

    public function description(): string
    {
        return 'Events with their venue, client, dates, and (optionally) a space placement. Resolves venue/client/space by name.';
    }

    public function requiresReadOnly(): bool
    {
        // bulk booking migration touches the availability graph
        return true;
    }

    public function fields(): array
    {
        return [
            new ImportField('name', 'Event name', required: true,
                hint: 'e.g. "Annual Gala 2024".', aliases: ['event', 'title', 'booking']),
            new ImportField('venue', 'Venue', required: true,
                hint: 'Venue name, must already exist.', aliases: ['location', 'facility']),
            new ImportField('client', 'Client',
                hint: 'Client name; must already exist (import clients first).',
                aliases: ['customer', 'organization', 'account']),
            new ImportField('status', 'Status',
                hint: 'inquiry, hold, tentative, definite, completed, cancelled. Default definite.',
                aliases: ['state']),
            new ImportField('start_at', 'Start', required: true,
                hint: 'Event start, e.g. "2024-06-01 09:00".', aliases: ['start', 'start date', 'from']),
            new ImportField('end_at', 'End', required: true,
                hint: 'Event end, e.g. "2024-06-01 17:00".', aliases: ['end', 'end date', 'to']),
            new ImportField('space', 'Space',
                hint: 'Space name within the venue; creates the placement.',
                aliases: ['room', 'hall']),
            new ImportField('attendance_estimate', 'Attendance',
                hint: 'Estimated headcount.', aliases: ['headcount', 'guests', 'pax']),
            new ImportField('total', 'Total',
                hint: 'Contract total in dollars, e.g. "5000.00".', aliases: ['amount', 'value', 'contract total']),
        ];
    }

    public function import(array $row, bool $dryRun): ImportRowResult
    {
        $data = [
            'name' => $this->clean($row['name'] ?? null),
            'venue' => $this->clean($row['venue'] ?? null),
            'client' => $this->clean($row['client'] ?? null),
            'status' => $this->clean($row['status'] ?? null),
            'start_at' => $this->clean($row['start_at'] ?? null),
            'end_at' => $this->clean($row['end_at'] ?? null),
            'space' => $this->clean($row['space'] ?? null),
            'attendance_estimate' => $this->clean($row['attendance_estimate'] ?? null),
            'total' => $this->clean($row['total'] ?? null),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'venue' => ['required', 'string'],
            'start_at' => ['required', 'string'],
            'end_at' => ['required', 'string'],
            'attendance_estimate' => ['nullable', 'integer', 'min:0'],
            'total' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return ImportRowResult::failures($this->failuresFrom($validator));
        }

        $start = $this->parseDate($data['start_at']);
        $end = $this->parseDate($data['end_at']);

        if ($start === null || $end === null) {
            return ImportRowResult::failure('Could not parse the start or end date/time.', $start === null ? 'start_at' : 'end_at');
        }
        if ($end->lessThanOrEqualTo($start)) {
            return ImportRowResult::failure('End must be after start.', 'end_at');
        }

        $status = $this->resolveStatus($data['status']);
        if ($data['status'] !== null && $status === null) {
            return ImportRowResult::failure("Unrecognized status \"{$data['status']}\".", 'status');
        }
        $status ??= BookingStatus::Definite;

        $venue = Venue::query()->whereRaw('lower(name) = ?', [Str::lower($data['venue'])])->first();
        if ($venue === null) {
            return ImportRowResult::failure("Venue \"{$data['venue']}\" not found.", 'venue');
        }

        $clientId = null;
        if ($data['client'] !== null) {
            $client = Client::query()->whereRaw('lower(name) = ?', [Str::lower($data['client'])])->first();
            if ($client === null) {
                return ImportRowResult::failure("Client \"{$data['client']}\" not found - import clients first.", 'client');
            }
            $clientId = $client->getKey();
        }

        $space = null;
        if ($data['space'] !== null) {
            $space = Space::query()
                ->where('venue_id', $venue->getKey())
                ->whereRaw('lower(name) = ?', [Str::lower($data['space'])])
                ->first();
            if ($space === null) {
                return ImportRowResult::failure("Space \"{$data['space']}\" not found in venue \"{$venue->name}\".", 'space');
            }
        }

        if ($dryRun) {
            return ImportRowResult::success();
        }

        $booking = Booking::query()->create([
            'venue_id' => $venue->getKey(),
            'client_id' => $clientId,
            'name' => $data['name'],
            'status' => $status->value,
            'start_at' => $start,
            'end_at' => $end,
            'attendance_estimate' => $data['attendance_estimate'] !== null ? (int) $data['attendance_estimate'] : null,
            'total_cents' => $data['total'] !== null ? Money::toCents($data['total']) : 0,
        ]);

        $created = [$booking];

        if ($space !== null) {
            // BookingSpace save hook enforces overlap; a collision throws and
            // the service records the row as an error
            $bookingSpace = $booking->spaces()->create([
                'space_id' => $space->getKey(),
                'start_at' => $start,
                'end_at' => $end,
            ]);
            $created[] = $bookingSpace;
        }

        return ImportRowResult::success($created);
    }

    /**
     * Referenced once it has invoices or contracts; reversal leaves it (and its
     * spaces) intact rather than deleting downstream financial/legal records.
     */
    public function isReferenced(Model $model): bool
    {
        return $model instanceof Booking
            && ($model->invoices()->exists()
                || Contract::query()->where('booking_id', $model->getKey())->exists());
    }

    private function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveStatus(?string $value): ?BookingStatus
    {
        if ($value === null) {
            return null;
        }

        $token = Str::of($value)->lower()->replaceMatches('/[^a-z0-9]+/', '')->value();

        return match ($token) {
            'inquiry', 'inquiries', 'lead', 'prospect' => BookingStatus::Inquiry,
            'hold', 'held' => BookingStatus::Hold,
            'tentative', 'pending' => BookingStatus::Tentative,
            'definite', 'confirmed', 'booked' => BookingStatus::Definite,
            'completed', 'complete', 'done', 'past' => BookingStatus::Completed,
            'cancelled', 'canceled', 'void' => BookingStatus::Cancelled,
            default => null,
        };
    }
}
