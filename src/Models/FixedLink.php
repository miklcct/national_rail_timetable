<?php
declare(strict_types=1);

namespace Miklcct\NationalRailJourneyPlanner\Models;

use DateTimeImmutable;

class FixedLink {
    public function __construct(
        public readonly string $mode
        , public readonly Station $origin
        , public readonly Station $destination
        , public readonly int $transferTime
        , public readonly Time $startTime
        , public readonly Time $endTime
        , public readonly int $priority
        , public readonly ?DateTimeImmutable $startDate
        , public readonly ?DateTimeImmutable $endDate
        , ?array $weekdays
    ) {
        $this->weekdays = $weekdays;
    }

    /** @var bool[] 7 bits specifying if it is active on each of the weekdays */
    public readonly array $weekdays;
}