<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\DomainModels\Points;

use Miklcct\NationalRailTimetable\DomainModels\ServiceProperty;
use Miklcct\NationalRailTimetable\Models\Location;
use Miklcct\NationalRailTimetable\Models\PhysicalStation;
use Miklcct\NationalRailTimetable\Models\Tiploc;
use Miklcct\NationalRailTimetable\ValueObjects\Time;

readonly class PassingPoint extends IntermediatePoint {

    public function __construct(
        Location $location,
        ?int $locationSuffix,
        ?string $platform,
        ?string $path,
        ?string $line,
        public Time $pass,
        Time $engineeringAllowance,
        Time $pathingAllowance,
        Time $performanceAllowance,
        array $activities,
        ServiceProperty $serviceProperty
    ) {
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

    public function getPass() : Time {
        return $this->pass;
    }
}
