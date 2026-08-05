<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Controllers;

use GuzzleHttp\Psr7\Response;
use Miklcct\NationalRailTimetable\Config\Config;
use Miklcct\NationalRailTimetable\Exceptions\AmbiguousStation;
use Miklcct\NationalRailTimetable\Exceptions\StationNotFound;
use Miklcct\NationalRailTimetable\Middlewares\CacheMiddleware;
use Miklcct\RailOpenTimetableData\DomainModels\DepartureBoard;
use Miklcct\RailOpenTimetableData\Enums\TimeType;
use Miklcct\RailOpenTimetableData\Models\Date;
use Miklcct\RailOpenTimetableData\Models\FixedLink;
use Miklcct\RailOpenTimetableData\Models\Location;
use Miklcct\RailOpenTimetableData\Models\Station;
use Miklcct\RailOpenTimetableData\Models\Time;
use Miklcct\RailOpenTimetableData\Repositories\RepositoryInterface;
use Miklcct\NationalRailTimetable\Views\ScheduleFormView;
use Miklcct\NationalRailTimetable\Views\ScheduleView;
use Miklcct\NationalRailTimetable\Views\ViewMode;
use Miklcct\ThinPhpApp\Controller\Application;
use Miklcct\ThinPhpApp\Response\ViewResponseFactoryInterface;
use Miklcct\ThinPhpApp\View\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Teapot\HttpException;
use Teapot\StatusCode\Http;
use Teapot\StatusCode\WebDAV;
use function array_map;
use function in_array;
use function usort;

abstract class ScheduleController extends Application {
    public function __construct(
        protected readonly ViewResponseFactoryInterface $viewResponseFactory
        , protected readonly StreamFactoryInterface $streamFactory
        , private readonly CacheMiddleware $cacheMiddleware
        , protected readonly RepositoryInterface $repository
        , private readonly Config $config
        , private readonly CacheInterface $cache
    ) {}
    abstract protected function getInnerView(Date $date, DepartureBoard $board) : View;
    abstract protected function getViewMode() : ViewMode;

    public function getQuery() : BoardQuery {
        return $this->query;
    }

    protected function getMiddlewares() : array {
        return array_merge(parent::getMiddlewares(), [$this->cacheMiddleware]);
    }

    protected function run(ServerRequestInterface $request) : ResponseInterface {
        $query = $request->getQueryParams();
        $path_info = explode('/', trim($request->getServerParams()['PATH_INFO'] ?? '', '/'));
        $station_assigned = false;
        foreach ($path_info as $segment) {
            if (\Safe\preg_match('/^\d{4}-\d{2}-\d{2}$/', $segment)) {
                $query['date'] ??= $segment;
            } elseif (!$station_assigned) {
                $query['station'] ??= $segment;
                $station_assigned = true;
            } else {
                $query['filter'][] = $segment;
            }
        }
        $location_repository = $this->repository->getLocationRepository();
        try {
            $this->query = BoardQuery::fromArray($query, $location_repository);
        } catch (StationNotFound|AmbiguousStation $e) {
            return $this->createEmptyFormResponse($e);
        }

        $canonical_url = $this->query->getUrl(static::URL);
        if ($request->getServerParams()['REQUEST_URI'] !== $canonical_url) {
            return new Response(
                Http::PERMANENT_REDIRECT
                , ['Location' => $canonical_url]
            );
        }

        $this->cacheMiddleware->query = $this->query;
        if ($this->query->station === null) {
            return $this->createEmptyFormResponse(null);
        }


        $date = $this->query->date ?? Date::today();
        $service_repository = $this->repository->getServiceRepository($this->query->permanentOnly);
        $updated = $this->repository->getGeneratedDate();
        $from = $date->toDateTimeImmutable();
        $to = $date->toDateTimeImmutable(new Time(28, 30));

        if ($updated !== null && $from->getTimestamp() < $updated->toDateTimeImmutable()->getTimestamp()) {
            throw new HttpException('The timetable in the past is no longer available.', Http::GONE);
        }
        $time_type = $this->query->arrivalMode ? TimeType::PUBLIC_ARRIVAL : TimeType::PUBLIC_DEPARTURE;
        $station = $this->query->station;

        $cache_key = sprintf(
            'board_%s_%s_%012d_%012d_%s_%d%s',
            $this->repository->getGeneratedDate(),
            $station->getCrsOrTiplocCode(),
            $from->getTimestamp(),
            $to->getTimestamp(),
            $time_type->value,
            $this->query->permanentOnly,
            $this->query->toc === null ? "" : "_" . implode("", $this->query->toc)
        );
        $cache_entry = $this->cache?->get($cache_key);
        if ($cache_entry !== null) {
            $board = $cache_entry;
        } else {
            $board = $service_repository->getDepartureBoard($station, $from, $to, $time_type, $this->query->toc);
            $this->cache?->set($cache_key, $board);
        }
        $board = $board->filterByDestination($this->query->filter, $this->query->inverseFilter);
        return ($this->viewResponseFactory)(
            new ScheduleView(
                $this->streamFactory
                , $location_repository->getAllStations()
                , $date
                , $this->query
                , $this->getFixedLinks()
                , $this->repository->getGeneratedDate()
                , $this->config->siteName
                , $this->getInnerView($date, $board)
            )
        );
    }

    protected function getFixedLinks() : array {
        if ($this->query->toc !== null) {
            return [];
        }
        $station = $this->query->station;
        if (!$station instanceof Station) {
            return [];
        }
        /** @var FixedLink[] $fixed_links */
        $fixed_links = [];
        $fixed_link_departure_time = $this->query->getFixedLinkDepartureTime();
        $arrival_mode = $this->query->arrivalMode;
        $destinations = $this->query->filter;
        $date = $this->query->date ?? Date::today();
        foreach ($this->repository->getFixedLinkRepository()->get($arrival_mode ? null : $station->crsCode, $arrival_mode ? $station->crsCode : null) as $fixed_link) {
            if (
                $destinations === [] || in_array(
                    $arrival_mode ? $fixed_link->origin->crsCode : $fixed_link->destination->crsCode
                    , array_map(
                        static fn(Location $destination) => $destination->crsCode
                        , $destinations
                    )
                    , true
                )
            ) {
                if ($fixed_link_departure_time !== null) {
                    $arrival_time = $fixed_link->getArrivalTime($fixed_link_departure_time, $arrival_mode);
                    $existing = $fixed_links[$arrival_mode ? $fixed_link->origin->crsCode : $fixed_link->destination->crsCode] ?? null;
                    if (
                        $arrival_time !== null
                        && (
                            !$existing
                            || ($arrival_mode ? $arrival_time > $existing->getArrivalTime($fixed_link_departure_time, true)
                                : $arrival_time < $existing->getArrivalTime($fixed_link_departure_time))
                            || $arrival_time == $existing->getArrivalTime($fixed_link_departure_time, $arrival_mode)
                            && $fixed_link->priority > $existing->priority
                        )
                    ) {
                        $fixed_links[$arrival_mode ? $fixed_link->origin->crsCode : $fixed_link->destination->crsCode] = $fixed_link;
                    }
                } elseif ($fixed_link->isActiveOnDate($date)) {
                    $fixed_links[] = $fixed_link;
                }
            }
        }

        usort(
            $fixed_links
            , static fn(FixedLink $a, FixedLink $b) => $a->origin === $b->origin
                ? $a->destination === $b->destination
                    ? $a->start_time->secondsFromOrigin <=> $b->start_time->secondsFromOrigin
                    : $a->destination <=> $b->destination
                : $a->origin <=> $b->origin
        );
        return $fixed_links;
    }

    private function createEmptyFormResponse(?StationNotFound $e) : ResponseInterface {
        return ($this->viewResponseFactory)(
            new ScheduleFormView(
                $this->streamFactory
                , $this->repository->getLocationRepository()->getAllStations()
                , $this->getViewMode()
                , $this->config->siteName
                , $this->repository->getGeneratedDate()
                , $e?->getMessage()
            )
        )->withStatus($e ? WebDAV::UNPROCESSABLE_ENTITY : Http::OK);
    }

    private BoardQuery $query;
}
