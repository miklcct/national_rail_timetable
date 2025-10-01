<?php
declare(strict_types=1);

namespace App\DomainModels\Points;

use App\DomainModels\Locations\Location;
use App\DomainModels\Time;

readonly class DestinationPoint extends TimingPoint implements HasArrival {
    use ArrivalTrait;

    public function __construct(
        Location $location,
        string $locationSuffix,
        string $platform,
        public string $path,
        Time $workingArrival,
        ?Time $publicArrival,
        array $activity
    ) {
        $this->publicArrival = $publicArrival;
        $this->workingArrival = $workingArrival;
        parent::__construct($location, $locationSuffix, $platform, $activity);
    }
}
