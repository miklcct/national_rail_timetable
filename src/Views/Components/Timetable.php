<?php
declare(strict_types = 1);

namespace Miklcct\NationalRailTimetable\Views\Components;

use Miklcct\NationalRailTimetable\Controllers\BoardQuery;
use Miklcct\NationalRailTimetable\DomainModels\DepartureBoard;
use Miklcct\NationalRailTimetable\DomainModels\Timetable as TimetableModel;
use Miklcct\NationalRailTimetable\Models\Location;
use Miklcct\NationalRailTimetable\ValueObjects\Date;
use Miklcct\NationalRailTimetable\Views\ViewMode;
use Miklcct\ThinPhpApp\View\PhpTemplate;
use Psr\Http\Message\StreamFactoryInterface;

class Timetable extends PhpTemplate {

    /**
     * @param StreamFactoryInterface $streamFactory
     * @param Date $date
     * @param DepartureBoard[] $boards
     * @param BoardQuery $query
     */
    public function __construct(
        StreamFactoryInterface $streamFactory
        , protected readonly Date $date
        , protected readonly array $boards
        , protected readonly BoardQuery $query
    ) {
        parent::__construct(
            $streamFactory
        );
    }

   protected function getPathToTemplate() : string {
        return __DIR__ . '/../../../resource/templates/timetable.phtml';
    }

    public function getViewMode() : ViewMode {
        return ViewMode::TIMETABLE;
    }

    /**
     * @param TimetableModel $timetable
     * @return Location[]
     */
    protected function getShownRows(TimetableModel $timetable) : array {
        $filter_crs = array_map(
            static fn(Location $filter_station) => $filter_station->getCrsOrTiplocCode()
            , $this->query->filter
        );
        return array_filter(
            $timetable->stations
            , fn(Location $station, int $key) =>
                $this->query->filter === []
                || $key === 0
                || in_array($station->getCrsOrTiplocCode(), $filter_crs, true)
                || array_filter(
                    $timetable->stations
                    , fn(Location $other_station, int $other_key) =>
                        ($this->query->arrivalMode ? $other_key < $key : $other_key > $key)
                        && in_array($other_station->getCrsOrTiplocCode(), $filter_crs, true)
                        && array_filter(
                            $timetable->calls[$key]
                            , static fn(string $uid_date) => isset($timetable->calls[$other_key][$uid_date])
                            , ARRAY_FILTER_USE_KEY
                        ) !== []
                    , ARRAY_FILTER_USE_BOTH
                ) !== []
            , ARRAY_FILTER_USE_BOTH
        );
    }
}