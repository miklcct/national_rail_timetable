<?php
declare(strict_types = 1);

namespace Miklcct\NationalRailTimetable\Controllers;

use Miklcct\NationalRailTimetable\Config\Config;
use Miklcct\NationalRailTimetable\Enums\TimeType;
use Miklcct\NationalRailTimetable\Exceptions\StationNotFound;
use Miklcct\NationalRailTimetable\Models\Date;
use Miklcct\NationalRailTimetable\Models\LocationWithCrs;
use Miklcct\NationalRailTimetable\Models\Time;
use Miklcct\NationalRailTimetable\Repositories\FixedLinkRepositoryInterface;
use Miklcct\NationalRailTimetable\Repositories\LocationRepositoryInterface;
use Miklcct\NationalRailTimetable\Repositories\ServiceRepositoryFactoryInterface;
use Miklcct\NationalRailTimetable\Views\ScheduleFormView;
use Miklcct\NationalRailTimetable\Views\Components\Timetable;
use Miklcct\NationalRailTimetable\Views\ScheduleView;
use Miklcct\NationalRailTimetable\Views\ViewMode;
use Miklcct\ThinPhpApp\Controller\Application;
use Miklcct\ThinPhpApp\Response\ViewResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Teapot\StatusCode\WebDAV;

class TimetableController extends Application {
    use ScheduleTrait;

    // this number must be greater than the maximum number of calls for a train
    public const URL = '/timetable.php';
    private const MULTIPLIER = 1000;

    public function __construct(
        private readonly ViewResponseFactoryInterface $viewResponseFactory
        , private readonly StreamFactoryInterface $streamFactory
        , private readonly ServiceRepositoryFactoryInterface $serviceRepositoryFactory
        , private readonly LocationRepositoryInterface $locationRepository
        , private readonly FixedLinkRepositoryInterface $fixedLinkRepository
        , private readonly Config $config
    ) {}

    public function runWithoutCache(ServerRequestInterface $request, BoardQuery $query) : ResponseInterface {
        $station = $query->station;
        if ($station === null) {
            return ($this->viewResponseFactory)(
                new ScheduleFormView(
                    $this->streamFactory
                    , $this->locationRepository->getAllStations()
                    , ViewMode::TIMETABLE
                    , $this->config->siteName
                )
            );
        }
        $date = $query->date ?? Date::today();
        $service_repository = ($this->serviceRepositoryFactory)($query->permanentOnly);
        $board = $service_repository->getDepartureBoard(
            $station->getCrsCode()
            , $date->toDateTimeImmutable()
            , $date->toDateTimeImmutable(new Time(28, 30))
            , $query->arrivalMode ? TimeType::PUBLIC_ARRIVAL : TimeType::PUBLIC_DEPARTURE
        );
        $board = $board->filterByDestination(
            array_map(static fn(LocationWithCrs $location) => $location->getCrsCode(), $query->filter)
            , array_map(static fn(LocationWithCrs $location) => $location->getCrsCode(), $query->inverseFilter)
        );

        return ($this->viewResponseFactory)(
            new ScheduleView(
                $this->streamFactory
                , $this->locationRepository->getAllStations()
                , $date
                , $query
                , $this->getFixedLinks($query)
                , $service_repository->getGeneratedDate()
                , $this->config->siteName
                , new Timetable(
                    $this->streamFactory
                    , $date
                    , $board->groupServices()
                    , $query
                )
            )
        );
    }

    private function createStationNotFoundResponse(StationNotFound $e) : ResponseInterface {
        return ($this->viewResponseFactory)(
            new ScheduleFormView(
                $this->streamFactory
                , $this->locationRepository->getAllStations()
                , ViewMode::TIMETABLE
                , $this->config->siteName
                , $e->getMessage()
            )
        )->withStatus(WebDAV::UNPROCESSABLE_ENTITY);
    }
}