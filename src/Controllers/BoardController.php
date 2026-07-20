<?php
declare(strict_types = 1);

namespace Miklcct\NationalRailTimetable\Controllers;

use Miklcct\NationalRailTimetable\DomainModels\DepartureBoard;
use Miklcct\NationalRailTimetable\Models\Location;
use Miklcct\NationalRailTimetable\ValueObjects\Date;
use Miklcct\NationalRailTimetable\Views\Components\Board;
use Miklcct\NationalRailTimetable\Views\ViewMode;

class BoardController extends ScheduleController {
    public const URL = '/board';

    protected function getInnerView(Date $date, DepartureBoard $board) : Board {
        $query = $this->getQuery();
        $station = $query->station;
        assert($station instanceof Location);
        $arrival_mode = $query->arrivalMode;
        $permanent_only = $query->permanentOnly;

        return new Board(
            $this->streamFactory
            , $board
            , $date
            , $query
        );
    }

    protected function getViewMode() : ViewMode {
        return ViewMode::BOARD;
    }
}