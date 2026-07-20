<?php
declare(strict_types = 1);

namespace Miklcct\NationalRailTimetable\Controllers;

use Miklcct\NationalRailTimetable\DomainModels\DepartureBoard;
use Miklcct\NationalRailTimetable\ValueObjects\Date;
use Miklcct\NationalRailTimetable\Views\Components\Timetable;
use Miklcct\NationalRailTimetable\Views\ViewMode;

class TimetableController extends ScheduleController {
    public const URL = '/timetable';

    protected function getInnerView(Date $date, DepartureBoard $board) : Timetable {
        return new Timetable(
            $this->streamFactory
            , $date
            , $board->groupServices()
            , $this->getQuery()
        );
    }

    protected function getViewMode() : ViewMode {
        return ViewMode::TIMETABLE;
    }
}