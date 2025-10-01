<?php
declare(strict_types=1);

namespace App\DomainModels\Points;

use App\DomainModels\ServiceProperty;
use App\Models\PhysicalStation;
use App\Models\Tiploc;
use App\ValueObjects\Time;

readonly class CallingPoint extends IntermediatePoint implements HasDeparture, HasArrival {
    use ArrivalTrait;
    use DepartureTrait;

    public function __construct(
        ?Tiploc $location,
        ?PhysicalStation $station,
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
            $station,
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
