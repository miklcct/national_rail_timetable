<?php
declare(strict_types=1);

namespace App\DomainModels\Points;

use App\Enums\Activity;
use App\Enums\TimeType;
use App\Models\Tiploc;
use App\ValueObjects\Time;

readonly abstract class TimingPoint {
    public function __construct(
        public Tiploc $location,
        public ?int $locationSuffix,
        public ?string $platform,
        /** @var Activity[] */
        public array $activities
    ) {
    }

    public function getTime(TimeType $time_type) : ?Time {
        return match ($time_type) {
            TimeType::WORKING_ARRIVAL => $this instanceof HasArrival ? $this->getWorkingArrival() : null,
            TimeType::PUBLIC_ARRIVAL => $this instanceof HasArrival ? $this->getPublicArrival() : null,
            TimeType::PASS => $this instanceof PassingPoint ? $this->pass : null,
            TimeType::PUBLIC_DEPARTURE => $this instanceof HasDeparture ? $this->getPublicDeparture() : null,
            TimeType::WORKING_DEPARTURE => $this instanceof HasDeparture ? $this->getWorkingDeparture() : null,
        };
    }

    public function isPublicCall() : bool {
        $location = $this->location;
        return
            (
                $this instanceof HasDeparture && $this->getPublicDeparture() !== null
                || $this instanceof HasArrival && $this->getPublicArrival() !== null
            )
            // this filter out non-stations on rail services, but keeps bus stations without CRS
            && ($location->tiploc_code !== null || $location->stanox === null);
    }
}
