<?php
declare(strict_types=1);

namespace App\DomainModels\Points;

use App\Models\PhysicalStation;
use App\Models\Tiploc;
use App\ValueObjects\Time;

readonly class DestinationPoint extends TimingPoint implements HasArrival {
    use ArrivalTrait;

    public function __construct(
        ?Tiploc $location,
        ?PhysicalStation $station,
        ?int $locationSuffix,
        ?string $platform,
        public ?string $path,
        Time $workingArrival,
        ?Time $publicArrival,
        array $activity
    ) {
        $this->publicArrival = $publicArrival;
        $this->workingArrival = $workingArrival;
        parent::__construct($location, $station, $locationSuffix, $platform, $activity);
    }
}
