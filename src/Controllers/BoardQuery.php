<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Controllers;

use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use Miklcct\NationalRailTimetable\Exceptions\AmbiguousStation;
use Miklcct\NationalRailTimetable\Exceptions\StationNotFound;
use Miklcct\NationalRailTimetable\Models\Location;
use Miklcct\NationalRailTimetable\Models\PhysicalStation;
use Miklcct\NationalRailTimetable\Models\Tiploc;
use Miklcct\NationalRailTimetable\ValueObjects\Date;
use function array_filter;
use function array_map;

class BoardQuery {
    /**
     * @param bool $arrivalMode
     * @param Location|null $station
     * @param Location[] $filter
     * @param Location[] $inverseFilter
     * @param Date|null $date
     * @param DateTimeImmutable|null $connectingTime
     * @param string|null $connectingToc
     * @param bool $permanentOnly
     */
    final public function __construct(
        public readonly bool $arrivalMode = false
        , public readonly ?Location $station = null
        , public readonly array $filter = []
        , public readonly array $inverseFilter = []
        , public readonly ?Date $date = null
        , public readonly ?array $toc = null
        , public readonly ?DateTimeImmutable $connectingTime = null
        , public readonly ?string $connectingToc = null
        , public readonly bool $permanentOnly = false
        , public readonly array $otherQueryArguments = []
    ) {}

    public static function fromArray(array $query) : static {
        return new static(
            ($query['mode'] ?? '') === 'arrivals'
            , empty($query['station']) ? null : static::getQueryStation($query['station'])
            , array_map(
                static fn(string $string) => static::getQueryStation($string)
                , array_values(array_filter((array)($query['filter'] ?? [])))
            )
            , array_map(
                static fn(string $string) => static::getQueryStation($string)
                , array_values(array_filter((array)($query['inverse_filter'] ?? [])))
            )
            , empty($query['date']) ? null : Date::fromDateTimeInterface(new \Safe\DateTimeImmutable($query['date']))
            , isset($query['toc']) ? (array)$query['toc'] : null
            , empty($query['connecting_time']) ? null : new \Safe\DateTimeImmutable($query['connecting_time'])
            , $query['connecting_toc'] ?? '' ?: null
            , !empty($query['permanent_only'])
            , array_diff_key($query, ['mode', 'station', 'filter', 'inverse_filter', 'date', 'toc', 'connecting_time', 'connecting_toc', 'permanent_only'])
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
                'mode' => $this->arrivalMode ? 'arrivals' : '',
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
            ] + ($this->permanentOnly ? ['permanent_only' => '1'] : []) + $this->otherQueryArguments
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
        return isset($this->connectingTime) && $this->station instanceof PhysicalStation
            ? $this->arrivalMode
                ? $this->connectingTime->sub(
                    new DateInterval(sprintf('PT%dM', $this->station->minimum_change_time))
                )
                : $this->connectingTime->add(
                    new DateInterval(sprintf('PT%dM', $this->station->minimum_change_time))
                )
            : null;
    }

    private static function getQueryStation(string $name_or_crs) : ?Location {
        if ($name_or_crs === '') {
            return null;
        }
        $name_or_crs = substr($name_or_crs, 0, 26);
        $station = PhysicalStation::where('station_name', $name_or_crs)->orWhere('tiploc_code', $name_or_crs)->orWhere('crs_code', $name_or_crs)->orWhere('crs_reference_code', $name_or_crs)->first();
        if ($station === null) {
            /** @var Collection $stations */
            $stations = PhysicalStation::whereLike('station_name', "$name_or_crs (%")->get();
            if ($stations->count() > 1) {
                throw new AmbiguousStation($name_or_crs, array_map(fn($station) => $station->station_name, $stations->all()));
            }
            $station = $stations->first();
        }

        if ($station === null) {
            $station = Tiploc::where('description', $name_or_crs)->orWhere('tps_description', $name_or_crs)->orWhere('tiploc_code', $name_or_crs)->orWhere('crs_code', $name_or_crs)->first();
        }

        if ($station === null) {
            throw new StationNotFound($name_or_crs);
        }
        return $station;
    }
}