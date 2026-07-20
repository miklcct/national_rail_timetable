<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\DomainModels\Points;

use Miklcct\NationalRailTimetable\Models\Location;
use Miklcct\NationalRailTimetable\ValueObjects\Time;

readonly class DestinationPoint extends TimingPoint implements HasArrival {
    use ArrivalTrait;

    public function __construct(
        ?Location $location,
        ?int $locationSuffix,
        ?string $platform,
        public ?string $path,
        Time $workingArrival,
        ?Time $publicArrival,
        array $activities
    ) {
        $this->publicArrival = $publicArrival;
        $this->workingArrival = $workingArrival;
        parent::__construct($location, $locationSuffix, $platform, $activities);
    }
}
