<?php
declare(strict_types = 1);

namespace Miklcct\NationalRailTimetable\Controllers;

use Miklcct\NationalRailTimetable\Views\Components\Board;
use Miklcct\NationalRailTimetable\Views\ViewMode;
use Miklcct\RailOpenTimetableData\DomainModels\DepartureBoard;
use Miklcct\RailOpenTimetableData\Models\Date;
use Miklcct\RailOpenTimetableData\Models\Location;

class BoardController extends ScheduleController {
    public const URL = '/board';

    protected function getInnerView(Date $date, DepartureBoard $board) : Board {
        $query = $this->getQuery();
        $station = $query->station;
        assert($station instanceof Location);

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