<?php
declare(strict_types=1);

namespace App\DomainModels\Points;

use App\DomainModels\Locations\Location;
use App\DomainModels\ServiceProperty;
use App\DomainModels\Time;

readonly class OriginPoint extends TimingPoint implements HasDeparture {
    use DepartureTrait;

    public function __construct(
        Location $location,
        string $locationSuffix,
        string $platform,
        public string $line,
        Time $workingDeparture,
        ?Time $publicDeparture,
        public int $allowanceHalfMinutes,
        array $activity,
        public ServiceProperty $serviceProperty
    ) {
        $this->publicDeparture = $publicDeparture;
        $this->workingDeparture = $workingDeparture;
        parent::__construct($location, $locationSuffix, $platform, $activity);
    }
}
