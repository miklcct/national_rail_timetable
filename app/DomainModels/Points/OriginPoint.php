<?php
declare(strict_types=1);

namespace App\DomainModels\Points;

use App\DomainModels\ServiceProperty;
use App\Models\PhysicalStation;
use App\Models\Tiploc;
use App\ValueObjects\Time;

readonly class OriginPoint extends TimingPoint implements HasDeparture {
    use DepartureTrait;

    public function __construct(
        ?Tiploc $location,
        ?PhysicalStation $station,
        ?int $locationSuffix,
        ?string $platform,
        public ?string $line,
        Time $workingDeparture,
        ?Time $publicDeparture,
        public Time $engineeringAllowance,
        public Time $pathingAllowance,
        public Time $performanceAllowance,
        array $activity,
        public ServiceProperty $serviceProperty
    ) {
        $this->publicDeparture = $publicDeparture;
        $this->workingDeparture = $workingDeparture;
        parent::__construct($location, $station, $locationSuffix, $platform, $activity);
    }
}
