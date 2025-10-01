<?php
declare(strict_types=1);

namespace App\DomainModels\Points;

use App\DomainModels\ServiceProperty;
use App\Models\Tiploc;
use App\ValueObjects\Time;

readonly class PassingPoint extends IntermediatePoint {

    public function __construct(
        Tiploc $location,
        ?int $locationSuffix,
        ?string $platform,
        ?string $path,
        ?string $line,
        public Time $pass,
        Time $engineeringAllowance,
        Time $pathingAllowance,
        Time $performanceAllowance,
        array $activity,
        ?ServiceProperty $serviceProperty
    ) {
        parent::__construct(
            $location,
            $locationSuffix,
            $platform,
            $path,
            $line,
            $engineeringAllowance,
            $pathingAllowance,
            $performanceAllowance,
            $activity,
            $serviceProperty
        );
    }

    public function getPass() : Time {
        return $this->pass;
    }
}
