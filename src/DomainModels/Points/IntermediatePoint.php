<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\DomainModels\Points;

use Miklcct\NationalRailTimetable\DomainModels\ServiceProperty;
use Miklcct\NationalRailTimetable\Models\Location;
use Miklcct\NationalRailTimetable\Models\PhysicalStation;
use Miklcct\NationalRailTimetable\Models\Tiploc;
use Miklcct\NationalRailTimetable\ValueObjects\Time;

readonly abstract class IntermediatePoint extends OriginOrIntermediatePoint {
    public function __construct(
        Location $location,
        ?int $locationSuffix,
        ?string $platform,
        public ?string $path,
        ?string $line,
        Time $engineeringAllowance,
        Time $pathingAllowance,
        Time $performanceAllowance,
        array $activities,
        ServiceProperty $serviceProperty
    ) {
        parent::__construct($location, $locationSuffix, $platform, $line, $engineeringAllowance, $pathingAllowance, $performanceAllowance, $activities, $serviceProperty);
    }
}
