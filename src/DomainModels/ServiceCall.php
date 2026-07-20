<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\DomainModels;

use DateInterval;
use DateTimeImmutable;
use Miklcct\NationalRailTimetable\DomainModels\Points\HasArrival;
use Miklcct\NationalRailTimetable\DomainModels\Points\HasDeparture;
use Miklcct\NationalRailTimetable\DomainModels\Points\TimingPoint;
use Miklcct\NationalRailTimetable\Enums\TimeType;
use Miklcct\NationalRailTimetable\Models\PhysicalStation;

readonly class ServiceCall {
    public function __construct(public Service $service, public int $callIndex) {
        $this->timingPoint = $service->timingPoints[$this->callIndex];
    }
    
    public TimingPoint $timingPoint;

    /**
     * @return self[]
     */
    public function getPrecedingPublicCalls() : array {
        $filter = fn(TimingPoint $item) => $item instanceof HasDeparture && $item->getPublicDeparture() !== null;
        $calls_of_this_portion = array_map(
            fn(int $key) => new self($this->service, $key)
            , array_filter(array_keys(array_filter($this->service->timingPoints, $filter)), fn(int $item) => $item < $this->callIndex)
        );
        $calls_from_divided_from = $this->service->divideFrom 
            ? array_map(
                fn($key) => new self($this->service->divideFrom, $key)
                , array_keys(array_filter($this->service->divideFrom->timingPoints, $filter)), fn(int $item) => $item < count($this->service->divideFrom->timingPoints)
            )
            : [];
        $calls_from_joined_portions = array_map(
            fn(Service $portion) => array_map(
                fn(int $key) => new self($portion, $key)
                , array_keys(array_filter($portion->timingPoints, $filter))
            )
            , $this->service->joins
        );
        return array_merge(array_merge($calls_from_divided_from, ...$calls_from_joined_portions), $calls_of_this_portion);
    }

    /**
     * @return self[]
     */
    public function getSubsequentPublicCalls() : array {
        $filter = fn(TimingPoint $item) => $item instanceof HasArrival && $item->getPublicArrival() !== null;
        $calls_of_this_portion = array_map(
            fn(int $key) => new self($this->service, $key)
            , array_filter(
                array_keys(
                    array_filter($this->service->timingPoints, $filter),
                )
                , fn(int $item) => $item > $this->callIndex
            )
        );
        $calls_from_joined_to = $this->service->joinTo
            ? array_map(
                fn($key) => new self($this->service->joinTo, $key)
                , array_keys(array_filter($this->service->joinTo->timingPoints, $filter))
            )
            : [];
        $calls_from_divided_portions = array_map(
            fn(Service $portion) => array_map(
                fn(int $key) => new self($portion, $key)
                , array_keys(array_filter($portion->timingPoints, $filter))
            )
            , $this->service->divides
        );
        return array_merge(
            array_merge(
                $calls_of_this_portion
                , ...$calls_from_divided_portions
            )
            , $calls_from_joined_to
        );
    }

    public function getTimestamp(TimeType $time_type) : ?DateTimeImmutable {
        $time = $this->timingPoint->getTime($time_type);
        if ($time === null) {
            return null;
        }
        return $this->service->date->toDateTimeImmutable($time, $this->service->getAbsoluteTimeZone());
    }

    public function isValidConnection(TimeType $time_type, DateTimeImmutable $time, ?string $other_toc) : bool {
        $station = $this->timingPoint->location;
        if (!$station instanceof PhysicalStation) {
            return false;
        }
        $timestamp = $this->getTimestamp($time_type);
        return match ($time_type) {
            TimeType::PUBLIC_DEPARTURE => $timestamp >= $time->add(new DateInterval(sprintf('PT%dM', $station->getConnectionTime($other_toc, $this->service->toc)))),
            TimeType::PUBLIC_ARRIVAL => $time >= $timestamp->add(new DateInterval(sprintf('PT%dM', $station->getConnectionTime($this->service->toc, $other_toc)))),
            default => false,
        };

    }
}