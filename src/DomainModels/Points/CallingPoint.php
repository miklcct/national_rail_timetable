<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\DomainModels\Points;

use Miklcct\NationalRailTimetable\DomainModels\ServiceProperty;
use Miklcct\NationalRailTimetable\Models\Location;
use Miklcct\NationalRailTimetable\Models\PhysicalStation;
use Miklcct\NationalRailTimetable\Models\Tiploc;
use Miklcct\NationalRailTimetable\ValueObjects\Time;

readonly class CallingPoint extends IntermediatePoint implements HasDeparture, HasArrival {
    use ArrivalTrait;
    use DepartureTrait;

    public function __construct(
        Location $location,
        ?int $locationSuffix,
        ?string $platform,
        ?string $path,
        ?string $line,
        Time $workingArrival,
        ?Time $publicArrival,
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
        $this->publicArrival = $publicArrival;
        $this->workingArrival = $workingArrival;
        parent::__construct(
            $location,
            $locationSuffix,
            $platform,
            $path,
            $line,
            $engineeringAllowance,
            $pathingAllowance,
            $performanceAllowance,
            $activities,
            $serviceProperty
        );
    }
}
