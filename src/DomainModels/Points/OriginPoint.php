<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\DomainModels\Points;

use Miklcct\NationalRailTimetable\DomainModels\ServiceProperty;
use Miklcct\NationalRailTimetable\Models\Location;
use Miklcct\NationalRailTimetable\Models\PhysicalStation;
use Miklcct\NationalRailTimetable\Models\Tiploc;
use Miklcct\NationalRailTimetable\ValueObjects\Time;

readonly class OriginPoint extends OriginOrIntermediatePoint implements HasDeparture {
    use DepartureTrait;

    public function __construct(
        Location $location,
        ?int $locationSuffix,
        ?string $platform,
        ?string $line,
        Time $workingDeparture,
        ?Time $publicDeparture,
        Time $engineeringAllowance,
        Time $pathingAllowance,
        Time $performanceAllowance,
        array $activities,
        ServiceProperty $serviceProperty
    ) {
        $this->publicDeparture = $publicDeparture;
        $this->workingDeparture = $workingDeparture;
        parent::__construct($location, $locationSuffix, $platform, $line, $engineeringAllowance, $pathingAllowance, $performanceAllowance, $activities, $serviceProperty);
    }
}
