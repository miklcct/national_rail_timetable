<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Controllers;

use DateInterval;
use DateTimeImmutable;
use Miklcct\NationalRailTimetable\Exceptions\StationNotFound;
use Miklcct\RailOpenTimetableData\Enums\TimeType;
use Miklcct\RailOpenTimetableData\Models\Date;
use Miklcct\RailOpenTimetableData\Models\Location;
use Miklcct\RailOpenTimetableData\Models\Station;
use Miklcct\RailOpenTimetableData\Repositories\LocationRepositoryInterface;
use function array_filter;
use function array_map;

readonly class BoardQuery {
    /**
     * @param bool $arrivalMode
     * @param Location|null $station
     * @param Location[] $filter
     * @param Location[] $inverseFilter
     * @param Date|null $date
     * @param array|null $toc
     * @param DateTimeImmutable|null $connectingTime
     * @param string|null $connectingToc
     * @param bool $permanentOnly
     * @param array $otherQueryArguments
     */
    final public function __construct(
        public TimeType $timeType = TimeType::PUBLIC_DEPARTURE
        , public ?Location $station = null
        , public array $filter = []
        , public array $inverseFilter = []
        , public ?Date $date = null
        , public ?array $toc = null
        , public ?DateTimeImmutable $connectingTime = null
        , public ?string $connectingToc = null
        , public bool $permanentOnly = false
        , public array $otherQueryArguments = []
    ) {}

    public static function fromArray(array $query, LocationRepositoryInterface $location_repository) : static {
        return new static(
            TimeType::tryFrom($query['time_type'] ?? '') ?? (($query['mode'] ?? '') === 'arrivals' ? TimeType::PUBLIC_ARRIVAL : TimeType::PUBLIC_DEPARTURE)
            , empty($query['station']) ? null : static::getQueryStation($query['station'], $location_repository)
            , array_map(
                static fn(string $string) => static::getQueryStation($string, $location_repository)
                , array_values(array_filter((array)($query['filter'] ?? [])))
            )
            , array_map(
                static fn(string $string) => static::getQueryStation($string, $location_repository)
                , array_values(array_filter((array)($query['inverse_filter'] ?? [])))
            )
            , empty($query['date']) ? null : Date::fromDateTimeInterface(new \Safe\DateTimeImmutable($query['date']))
            , isset($query['toc']) ? (array)$query['toc'] : null
            , empty($query['connecting_time']) ? null : new \Safe\DateTimeImmutable($query['connecting_time'])
            , $query['connecting_toc'] ?? '' ?: null
            , !empty($query['permanent_only'])
            , array_diff_key($query, [
                'mode' => null, 
                'station' => null, 
                'filter' => null, 
                'inverse_filter' => null, 
                'date' => null, 
                'toc' => null, 
                'connecting_time' => null, 
                'connecting_toc' => null, 
                'permanent_only' => null, 
                'time_type' => null,
            ])
        );
    }

    public function toArray() : array {
        $filter = static function (array $array) use (&$filter) {
            return array_filter(
                array_map(
                    static fn($item) => is_array($item) ? $filter($item) : $item
                    , $array
                )
                , static fn($item) => $item !== [] && $item !== ''
            );
        };
        return $filter(
            [
                'station' => $this->station?->getCrsOrTiplocCode(),
                'filter' => array_map(
                    static fn(Location $location) => $location->getCrsOrTiplocCode()
                    , $this->filter
                ),
                'inverse_filter' => array_map(
                    static fn(Location $location) => $location->getCrsOrTiplocCode()
                    , $this->inverseFilter
                ),
                'date' => $this->date?->__toString() ?? '',
                'toc' => $this->toc,
                'connecting_time' => substr($this->connectingTime?->format('c') ?? '', 0, 16),
                'connecting_toc' => $this->connectingTime === null ? '' : $this->connectingToc ?? '',
            ] 
            + ($this->permanentOnly ? ['permanent_only' => '1'] : [])
            + ($this->timeType === TimeType::PUBLIC_DEPARTURE ? [] : ['time_type' => $this->timeType->value])
            + $this->otherQueryArguments
        );
    }

    public function getUrl(string $base_url) : string {
        $array = $this->toArray();
        return rtrim(
            sprintf(
                "%s/%s%s%s?%s",
                $base_url,
                $array['station'],
                implode(array_map(static fn(string $s) => "/$s", $array['filter'] ?? [])),
                !empty($array['date']) ? "/$array[date]" : '',
                http_build_query(array_diff_key($array, ['station' => null, 'date' => null, 'filter' => null]))
            )
            , '?'
        );
    }

    public function getFixedLinkDepartureTime() : ?DateTimeImmutable {
        return isset($this->connectingTime) && $this->station instanceof Station
            ? $this->timeType->isArrival()
                ? $this->connectingTime->sub(
                    new DateInterval(sprintf('PT%dM', $this->station->minimumConnectionTime))
                )
                : $this->connectingTime->add(
                    new DateInterval(sprintf('PT%dM', $this->station->minimumConnectionTime))
                )
            : null;
    }

    private static function getQueryStation(string $name_or_crs, LocationRepositoryInterface $location_repository) : ?Location {
        if ($name_or_crs === '') {
            return null;
        }
        $station = $location_repository->getLocationByCrs($name_or_crs)
            ?? $location_repository->getLocationByName($name_or_crs)
            ?? $location_repository->getLocationByTiploc($name_or_crs);
        if (!$station instanceof Location) {
            throw new StationNotFound($name_or_crs);
        }
        return $station;
    }
}