<?php

namespace App\Services\Spaces;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Search criteria for the best-fit space finder. All fields except
 * the date window are optional; null means "no constraint."
 */
class SpaceFinderCriteria
{
    public function __construct(
        public readonly DateTimeInterface $startAt,
        public readonly DateTimeInterface $endAt,
        public readonly ?int $attendance = null,
        public readonly ?int $minSqft = null,
        public readonly ?string $kind = null,
        public readonly ?int $venueId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            startAt: CarbonImmutable::parse((string) $data['starts_at']),
            endAt: CarbonImmutable::parse((string) $data['ends_at']),
            attendance: isset($data['attendance']) ? (int) $data['attendance'] : null,
            minSqft: isset($data['min_sqft']) ? (int) $data['min_sqft'] : null,
            kind: isset($data['kind']) && $data['kind'] !== ''
                ? (string) $data['kind']
                : null,
            venueId: isset($data['venue_id']) && $data['venue_id'] !== ''
                ? (int) $data['venue_id']
                : null,
        );
    }
}
