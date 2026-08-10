<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Views\Components;

use DateInterval;
use DateTimeImmutable;
use Miklcct\NationalRailTimetable\Controllers\BoardQuery;
use Miklcct\NationalRailTimetable\Views\ViewMode;
use Miklcct\RailOpenTimetableData\DomainModels\Service;
use Miklcct\RailOpenTimetableData\Enums\TimeType;
use Miklcct\RailOpenTimetableData\Models\Date;
use Miklcct\RailOpenTimetableData\Models\Location;
use Miklcct\RailOpenTimetableData\Models\Points\TimingPoint;
use Miklcct\ThinPhpApp\View\PhpTemplate;
use Psr\Http\Message\StreamFactoryInterface;
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
        , protected readonly bool $wtt
        , protected readonly ViewMode $fromViewMode
    ) {
        parent::__construct($streamFactory);
        $line = [];
        $points = $this->portion->timingPoints;
        foreach ($points as $point) {
            $coordinates = $point->location->getCoordinates();
            if ($coordinates !== null) {
                $line[] = $coordinates;
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
                , $timeType->getCompanion()
            )
        );
    }

    private function getBoardLink(DateTimeImmutable $timestamp, Location $location, TimeType $time_type) : ?string {
        return new BoardQuery(
            $time_type
            , $location
            , []
            , []
            , Date::fromDateTimeInterface($timestamp->sub(new DateInterval($time_type->isArrival() ? 'PT4H30M' : 'P0D')))
            , null
            , $timestamp
            , $this->portion->toc
            , $this->permanentOnly
        )->getUrl($this->fromViewMode->getUrl());
    }

    /** @var int[][] */
    protected readonly array $line;
}
