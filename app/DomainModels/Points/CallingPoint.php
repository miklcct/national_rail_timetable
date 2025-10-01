<?php
declare(strict_types=1);

namespace App\DomainModels\Points;

use App\DomainModels\Locations\Location;
use App\DomainModels\ServiceProperty;
use App\DomainModels\Time;
use App\Models\Tiploc;

readonly class CallingPoint extends IntermediatePoint implements HasDeparture, HasArrival {
    use ArrivalTrait;
    use DepartureTrait;

    public function __construct(
        Tiploc $location,
        string $locationSuffix,
        string $platform,
        string $path,
        string $line,
        Time $workingArrival,
        ?Time $publicArrival,
        Time $workingDeparture,
        ?Time $publicDeparture,
        int $allowanceHalfMinutes,
        array $activities,
        ?ServiceProperty $serviceProperty
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
            $allowanceHalfMinutes,
            $activities,
            $serviceProperty
        );
    }
}
