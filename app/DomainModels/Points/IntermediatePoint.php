<?php
declare(strict_types=1);

namespace App\DomainModels\Points;

use App\DomainModels\ServiceProperty;
use App\Models\PhysicalStation;
use App\Models\Tiploc;
use App\ValueObjects\Time;

readonly abstract class IntermediatePoint extends TimingPoint {
    public function __construct(
        ?Tiploc $location,
        ?PhysicalStation $station,
        ?int $locationSuffix,
        ?string $platform,
        public ?string $path,
        public ?string $line,
        Time $engineeringAllowance,
        Time $pathingAllowance,
        Time $performanceAllowance,
        array $activity,
        public ServiceProperty $serviceProperty
    ) {
        parent::__construct($location, $station, $locationSuffix, $platform, $activity);
    }
}
