<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Views;

use LogicException;
use Miklcct\NationalRailTimetable\Controllers\BoardController;
use Miklcct\NationalRailTimetable\Controllers\BoardQuery;
use Miklcct\NationalRailTimetable\Controllers\TimetableController;
use Miklcct\NationalRailTimetable\DomainModels\DepartureBoard;
use Miklcct\NationalRailTimetable\Models\FixedLink;
use Miklcct\NationalRailTimetable\Models\Location;
use Miklcct\NationalRailTimetable\ValueObjects\Date;
use Miklcct\NationalRailTimetable\Views\Components\Board;
use Miklcct\ThinPhpApp\View\PhpTemplate;
use Miklcct\ThinPhpApp\View\View;
use Psr\Http\Message\StreamFactoryInterface;
use function array_map;
use function assert;
use function count;
use function implode;
use function Miklcct\ThinPhpApp\Escaper\html;
use function sprintf;

class ScheduleView extends PhpTemplate {
    /**
     * @param StreamFactoryInterface $streamFactory
     * @param array $stations
     * @param DepartureBoard $board
     * @param Date $date
     * @param BoardQuery $query
     * @param FixedLink[]|null $fixedLinks
     */
    public function __construct(
        StreamFactoryInterface $streamFactory
        , protected readonly array $stations
        , protected readonly Date $date
        , protected readonly BoardQuery $query
        , protected readonly ?array $fixedLinks
        , protected readonly string $siteName
        , protected readonly View $innerView
    ) {
        parent::__construct($streamFactory);
    }

    protected function getTitle() : string {
        return sprintf(
            '%s %s at %s %s %s %s%s - %s'
            , $this->query->arrivalMode ? 'Arrivals' : 'Departures'
            , strtolower($this->getViewMode()->name)
            , $this->query->station->getName() ?? ''
            , $this->query->filter !== []
                ? ($this->query->arrivalMode ? 'from ' : 'to ') . implode(
                    ', '
                    , array_map(static fn(Location $location) => $location->getName(), $this->query->filter)
                )
                : ''
            , $this->query->inverseFilter !== []
                ? 'not ' . implode(
                    ', '
                    , array_map(static fn(Location $location) => $location->getName(), $this->query->inverseFilter)
                )
                : ''
            , $this->query->date === null ? 'today' : 'on ' . $this->date
            , $this->query->permanentOnly ? ' (permanent timetable)' : ''
            , $this->siteName
        );
    }

    protected function showHeading() : string {
        $location = $this->query->station;
        assert($location instanceof Location);
        $filter = $this->query->filter;
        $inverse_filter = $this->query->inverseFilter;
        return sprintf(
            '<div class="heading"><h1>%s %s at %s %s</h1><p>%s %s</p></div>'
            , $this->query->arrivalMode ? 'Arrivals' : 'Departures'
            , strtolower($this->getViewMode()->name)
            , html($location->getName())
            , ' on ' . $this->date
            , $filter !== [] ? 'calling at ' . implode(
                ', '
                , array_map(
                    static fn(Location $location) => $location->getName()
                    , $filter
                )
            ) : ''
            , $inverse_filter !== [] ? 'but not ' . implode(
                ', '
                , array_map(
                    static fn(Location $location) => $location->getName()
                    , $inverse_filter
                )
            ) : ''
        );
    }

    protected function getReverseDirectionLink() : string {
        if (count($this->query->filter) !== 1) {
            throw new LogicException('Reversing is only allowed when filter count is exactly 1.');
        }
        return (new BoardQuery(
            $this->query->arrivalMode
            , $this->query->filter[0]
            , [$this->query->station]
            , $this->query->inverseFilter
            , $this->query->date
            , $this->query->toc
            , null
            , null
            , $this->query->permanentOnly
        ))->getUrl($this->getUrl());
    }

    protected function getViewMode() : ViewMode {
        return $this->innerView instanceof Board ? ViewMode::BOARD : ViewMode::TIMETABLE;
    }

    protected function getPathToTemplate() : string {
        return __DIR__ . '/../../resource/templates/schedule.phtml';
    }

    protected function getUrl() : string {
        return match($this->getViewMode()) {
            ViewMode::TIMETABLE => TimetableController::URL,
            ViewMode::BOARD => BoardController::URL,
        };
    }
}
