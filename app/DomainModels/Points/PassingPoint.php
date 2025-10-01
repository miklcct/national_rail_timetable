<?php
declare(strict_types=1);

namespace App\DomainModels\Points;

use App\DomainModels\Locations\Location;
use App\DomainModels\ServiceProperty;
use App\DomainModels\Time;

readonly class PassingPoint extends IntermediatePoint {

    public function __construct(
        Location $location
        , string $locationSuffix
        , string $platform
        , string $path
        , string $line
        , public Time $pass
        , int $allowanceHalfMinutes
        , array $activity
        , ?ServiceProperty $serviceProperty
    ) {
        parent::__construct(
            $location
            , $locationSuffix
            , $platform
            , $path
            , $line
            , $allowanceHalfMinutes
            , $activity
            , $serviceProperty
        );
    }

    public function getPass() : Time {
        return $this->pass;
    }
}
