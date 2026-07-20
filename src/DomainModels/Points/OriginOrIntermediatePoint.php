<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\DomainModels\Points;

use Miklcct\NationalRailTimetable\DomainModels\ServiceProperty;
use Miklcct\NationalRailTimetable\Models\Location;
use Miklcct\NationalRailTimetable\Models\PhysicalStation;
use Miklcct\NationalRailTimetable\Models\Tiploc;
use Miklcct\NationalRailTimetable\ValueObjects\Time;

readonly abstract class OriginOrIntermediatePoint extends TimingPoint {

    public function __construct(
        Location $location,
        ?int $locationSuffix,
        ?string $platform,
        public ?string $line,
        public Time $engineeringAllowance,
        public Time $pathingAllowance,
        public Time $performanceAllowance,
        array $activities,
        public ServiceProperty $serviceProperty
    ) {
        parent::__construct($location, $locationSuffix, $platform, $activities);
    }
}
