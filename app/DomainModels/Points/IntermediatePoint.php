<?php
declare(strict_types=1);

namespace App\DomainModels\Points;
use App\DomainModels\ServiceProperty;
use App\Models\Tiploc;

readonly abstract class IntermediatePoint extends TimingPoint {
    public function __construct(
        Tiploc $location
        , string $locationSuffix
        , string $platform
        , public string $path
        , public string $line
        , public int $allowanceHalfMinutes
        , array $activity
        , public ?ServiceProperty $serviceProperty
    ) {
        parent::__construct($location, $locationSuffix, $platform, $activity);
    }
}
