<?php
declare(strict_types = 1);

namespace Miklcct\NationalRailTimetable\Views\Components;

use Miklcct\NationalRailTimetable\Controllers\BoardController;
use Miklcct\NationalRailTimetable\Controllers\BoardQuery;
use Miklcct\RailOpenTimetableData\DomainModels\DepartureBoard;
use Miklcct\RailOpenTimetableData\Models\Date;
use Miklcct\ThinPhpApp\View\PhpTemplate;
use Psr\Http\Message\StreamFactoryInterface;

class Board extends PhpTemplate {

    public function __construct(
        StreamFactoryInterface $streamFactory
        , protected readonly DepartureBoard $board
        , protected readonly Date $date
        , protected readonly BoardQuery $query
    ) {
        parent::__construct($streamFactory);
    }

    protected function getPathToTemplate() : string {
        return __DIR__ . '/../../../resource/templates/board.phtml';
    }

    protected function getDayOffsetLink(int $days) : string {
        return new BoardQuery(
            $this->query->timeType
            , $this->query->station
            , $this->query->filter
            , $this->query->inverseFilter
            , $this->date->addDays($days)
            , $this->query->toc
            , $this->query->connectingTime
            , $this->query->connectingToc
            , $this->query->permanentOnly
            , $this->query->signallingIdPrefixes
        )->getUrl(BoardController::URL);
    }

}