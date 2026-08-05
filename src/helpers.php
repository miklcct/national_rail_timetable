<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable;

use Miklcct\RailOpenTimetableData\DomainModels\Service;
use Miklcct\RailOpenTimetableData\Enums\TimeType;
use Miklcct\RailOpenTimetableData\Models\Date;
use Miklcct\RailOpenTimetableData\Models\Points\TimingPoint;
use Miklcct\RailOpenTimetableData\Models\Time;
use Miklcct\RailOpenTimetableData\Repositories\RepositoryInterface;
use Psr\SimpleCache\CacheInterface;

function show_departure_board(RepositoryInterface $repository, string $station, Date $date, bool $arrival_mode, array $filter = [], array $inverse_filter = [], ?CacheInterface $cache = null) : void {
    $time_type = $arrival_mode ? TimeType::PUBLIC_ARRIVAL : TimeType::PUBLIC_DEPARTURE;
    $get_location = $repository->getLocationRepository()->getLocation(...);
    $location = $get_location($station);
    $from = $date->toDateTimeImmutable();
    $to = $date->toDateTimeImmutable(new Time(28, 30));

    $cache_key = sprintf(
        'board_%s_%s_%012d_%012d_%s_%d%s',
        $repository->getGeneratedDate(),
        $location->getCrsOrTiplocCode(),
        $from->getTimestamp(),
        $to->getTimestamp(),
        $time_type->value,
        false,
        ""
    );

    $board = $repository->getServiceRepository()->getDepartureBoard(
        $location
        , $from
        , $to
        , $time_type
    );
    $cache->set($cache_key, $board);
    
    $board = $board->filterByDestination(array_map($get_location, $filter), array_map($get_location, $inverse_filter));
    
    foreach ($board->calls as $call) {
        printf(
            "%s\t%s\t%s\t%s\n"
            , $call->service->shortTermPlanning->getAbbreviation()
            , $call->getTimestamp($time_type)->format('H:i')
            , substr($call->timingPoint->serviceProperty->rsid ?? '', 0, 6)
            , implode(
                ' and '
                , array_map(
                    static fn(TimingPoint $point) => $point->location->getShortName()
                    , $arrival_mode 
                        ? array_map(fn(Service $portion) => array_first($portion->timingPoints), $call->service->getOriginPortions()) 
                        : array_map(fn(Service $portion) => array_last($portion->timingPoints), $call->service->getDestinationPortions())
                )
            )
        );
    }
}

