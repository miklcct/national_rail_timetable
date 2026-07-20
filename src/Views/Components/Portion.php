<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Views\Components;

use DateInterval;
use DateTimeImmutable;
use Miklcct\NationalRailTimetable\Controllers\BoardQuery;
use Miklcct\NationalRailTimetable\DomainModels\Points\TimingPoint;
use Miklcct\NationalRailTimetable\DomainModels\Service;
use Miklcct\NationalRailTimetable\Enums\TimeType;
use Miklcct\NationalRailTimetable\Models\Location;
use Miklcct\NationalRailTimetable\Models\PhysicalStation;
use Miklcct\NationalRailTimetable\ValueObjects\Date;
use Miklcct\NationalRailTimetable\Views\ViewMode;
use Miklcct\ThinPhpApp\View\PhpTemplate;
use Psr\Http\Message\StreamFactoryInterface;
use function Miklcct\NationalRailTimetable\Views\get_tiploc_data;
use function Miklcct\NationalRailTimetable\Views\show_time;

class Portion extends PhpTemplate {
    /**
     * @param StreamFactoryInterface $streamFactory
     * @param Date $dateFromOrigin
     * @param Service $portion
     * @param bool $permanentOnly
     * @param ViewMode $fromViewMode
     */
    public function __construct(
        StreamFactoryInterface $streamFactory
        , protected readonly Date $dateFromOrigin
        , protected readonly Service $portion
        , protected readonly bool $permanentOnly
        , protected readonly ViewMode $fromViewMode
    ) {
        parent::__construct($streamFactory);
        $line = [];
        $points = $this->portion->timingPoints;
        foreach ($points as $point) {
            if ($point instanceof TimingPoint) {
                $tiploc = $point->location->tiploc_code;
                $tiploc_row = get_tiploc_data()[$tiploc] ?? null;
                if ($tiploc_row !== null && $tiploc_row["easting"] !== null && $tiploc_row["northing"] !== null) {
                    $line[] = [$tiploc_row["easting"], $tiploc_row["northing"]];
                } elseif ($point->location instanceof PhysicalStation) {
                    $line[] = [$point->location->easting, $point->location->northing];
                }
            }
        }
        $this->line = $line;
    }

    protected function getPathToTemplate() : string {
        return __DIR__ . '/../../../resource/templates/portion.phtml';
    }

    protected function showTime(TimingPoint $point, TimeType $timeType) : string {
        $time = $point->getTime($timeType);
        if ($time === null) {
            return '';
        }
        $date_time = $this->portion->date->toDateTimeImmutable($time, $this->portion->getAbsoluteTimeZone());
        return show_time(
            $date_time
            , $this->dateFromOrigin
            , $this->getBoardLink(
                $date_time
                , $point->location
                , $timeType === TimeType::WORKING_DEPARTURE || $timeType === TimeType::PUBLIC_DEPARTURE
            )
        );
    }

    private function getBoardLink(DateTimeImmutable $timestamp, Location $location, bool $arrival_mode) : ?string {
        return (
            new BoardQuery(
                $arrival_mode
                , $location
                , []
                , []
                , Date::fromDateTimeInterface($timestamp->sub(new DateInterval($arrival_mode ? 'PT4H30M' : 'P0D')))
                , null
                , $timestamp
                , $this->portion->toc
                , $this->permanentOnly
            )
        )->getUrl($this->fromViewMode->getUrl());
    }

    /** @var int[][] */
    protected readonly array $line;
}
