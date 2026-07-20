<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\DomainModels;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Miklcct\NationalRailTimetable\Enums\TimeType;
use Miklcct\NationalRailTimetable\Models\BaseSchedule;
use Miklcct\NationalRailTimetable\Models\Location;
use Miklcct\NationalRailTimetable\Models\PhysicalStation;
use Miklcct\NationalRailTimetable\Models\Schedule;
use Miklcct\NationalRailTimetable\Models\ZSchedule;
use Miklcct\NationalRailTimetable\ValueObjects\Date;
use Miklcct\NationalRailTimetable\ValueObjects\Time;

readonly class DepartureBoard {
    /**
     * @param TimeType $timeType
     * @param ServiceCall[] $calls
     */
    public function __construct(
        public TimeType $timeType
        , public array $calls
    ) {
        $this->callMatrix = $this->buildCallMatrix();
    }

    public static function loadDepartureBoardOfStation(Location $location, Date $date, TimeType $time_type, bool $exclude_stp = false) : DepartureBoard {
        $crs = $location instanceof PhysicalStation ? $location->crs_code : null;
        $tiplocs = $location instanceof PhysicalStation ? PhysicalStation::where('crs_code', '=', $location->crs_code)->pluck('tiploc_code') : new Collection($location->tiploc_code);
        $date_and_stp_filter = function (EloquentBuilder $builder) use ($date, $exclude_stp) {
            $builder->dateAndStp($date->addDays(-1), $date->addDays(1), $exclude_stp);
        };
        $uids = Schedule::whereHas('stopTimes', function (EloquentBuilder $query) use ($tiplocs) {
            $query->whereIn('location', $tiplocs);
        })
            ->where($date_and_stp_filter)
            ->distinct()
            ->pluck('train_uid')->union(
                ZSchedule::whereHas('stopTimes', function (EloquentBuilder $query) use ($crs) {
                    $query->where('location', '=', $crs);
                })
                    ->where($date_and_stp_filter)
                    ->distinct()
                    ->pluck('train_uid')
            );
        
        $schedules = Schedule::whereIn('train_uid', $uids)
            ->with([
                'stopTimes' => function ($query) {
                    $query->withoutGlobalScopes();
                },
                'parentAssociations',
                'childAssociations',
            ])
            ->where($date_and_stp_filter)
            ->orderBy('stp_indicator')
            ->get();
        $z_schedules = ZSchedule::whereIn('train_uid', $uids)
            ->with([
                'stopTimes' => function ($query) {
                    $query->withoutGlobalScopes();
                },
            ])
            ->where($date_and_stp_filter)
            ->orderBy('stp_indicator')
            ->get();

        // this block handles STP
        $prospective_schedules = [];
        $tiplocs_array = $tiplocs->all();
        foreach ([$date->addDays(-1), $date->addDays(0), $date->addDays(1)] as $day) {
            /**
             * @var BaseSchedule $schedule
             */
            foreach ([...$schedules->all(), ...$z_schedules->all()] as $schedule) {
                if ($schedule->period->isActive($day)) {
                    // the list is sorted in STP order, permanent last
                    $prospective_schedules["{$schedule->train_uid}_$day"] ??= [$schedule, $day];
                }
            }
        }

        $begin = $date->toDateTimeImmutable(new Time(0, 0));
        $end = $date->toDateTimeImmutable(new Time(28, 30));
        $begin_seconds = 0;
        $end_seconds = 28.5 * 3600;

        // Filter out schedules that don't stop at the requested location or are outside the time window
        foreach ($prospective_schedules as $key => [$schedule, $day]) {
            $stops_in_window = false;
            $current_time = null;
            $day_diff = $day->compare($date);
            $day_offset_seconds = $day_diff * 86400;

            foreach ($schedule->stopTimes as $stopTime) {
                $time = $stopTime->public_departure_time ?? $stopTime->public_arrival_time ?? $stopTime->scheduled_departure_time ?? $stopTime->scheduled_pass_time ?? $stopTime->scheduled_arrival_time;
                $current_time = $time->applyDayOffset($current_time);

                if (in_array($stopTime->location, $tiplocs_array, true) || ($crs !== null && $stopTime->location === $crs)) {
                    $absolute_seconds = $current_time->secondsFromOrigin + $day_offset_seconds;
                    if ($absolute_seconds >= $begin_seconds && $absolute_seconds < $end_seconds) {
                        $stops_in_window = true;
                        break;
                    }
                }
            }
            if (!$stops_in_window) {
                unset($prospective_schedules[$key]);
            }
        }

        // Collect all schedules that are actually needed and load their stop times in bulk
        $needed_schedules = [];
        foreach ($prospective_schedules as [$schedule, $day]) {
            $needed_schedules[get_class($schedule)][$schedule->getKey()] = $schedule;
        }
        foreach ($needed_schedules as $class => $models) {
            (new \Illuminate\Database\Eloquent\Collection($models))->load([
                'stopTimes' => function (Relation $relation) {
                    $relation->with(['physicalStation', 'tiploc', 'serviceChange']);
                }
            ]);
        }

        $prospective_services = array_filter(array_map(fn($item) => Service::processScheduleEntry($item[0], $item[1], $exclude_stp), $prospective_schedules));

        $calls = [];
        foreach ($prospective_services as $service) {
            array_push($calls, ...array_filter($service->findCallInSameUid($location), function (ServiceCall $call) use ($end, $begin, $time_type) {
                $timestamp = $call->getTimestamp($time_type);
                return $timestamp !== null && $timestamp >= $begin && $timestamp < $end;
            }));
        }

        usort($calls, fn(ServiceCall $a, ServiceCall $b) => $a->getTimestamp($time_type) <=> $b->getTimestamp($time_type));

        return new DepartureBoard($time_type, $calls);
    }

    public function isPortionOvertaken(ServiceCall $self_departure, string $destination_crs, string $portion_uid) : bool {
        $arrival_mode = $this->timeType->isArrival();

        $self_arrival = null;
        foreach ($this->getSubsequentOrPrecedingCalls($self_departure) as $arrival) {
            $timingPoint = $arrival->timingPoint;
            $location = $timingPoint->location;
            if (
                array_key_exists(
                    $portion_uid
                    , $arrival_mode 
                        ? array_map(fn(Service $portion) => $portion->uid, $arrival->service->getOriginPortions()) 
                        : array_map(fn(Service $portion) => $portion->uid, $arrival->service->getDestinationPortions())
                )
                && $location->getCrsOrTiplocCode() === $destination_crs
            ) {
                $self_arrival = $arrival;
                break;
            }
        }
        if ($self_arrival === null) {
            return true;
        }

        return $this->isCallOvertaken($self_departure, $self_arrival);
    }

    public function isCallOvertaken(
        ServiceCall $self_departure
        , ServiceCall $self_arrival
    ) : bool {
        $arrival_mode = $this->timeType->isArrival();
        $timingPoint = $self_arrival->timingPoint;
        $location = $timingPoint->location;
        $destination_crs = $location->getCrsOrTiplocCode();
        foreach ($this->callMatrix[$destination_crs] as [$other_departure, $other_arrival]) {
            if (
                $arrival_mode
                    ? $other_arrival->getTimestamp(TimeType::PUBLIC_DEPARTURE) > $self_arrival->getTimestamp(TimeType::PUBLIC_DEPARTURE)
                    && $other_departure->getTimestamp(TimeType::PUBLIC_ARRIVAL) <= $self_departure->getTimestamp(TimeType::PUBLIC_ARRIVAL)
                    : $other_arrival->getTimestamp(TimeType::PUBLIC_ARRIVAL) < $self_arrival->getTimestamp(TimeType::PUBLIC_ARRIVAL)
                    && $other_departure->getTimestamp(TimeType::PUBLIC_DEPARTURE) >= $self_departure->getTimestamp(TimeType::PUBLIC_DEPARTURE)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filter the departure board by preceding / subsequent calls
     *
     * @param Location[] $filter
     * @param Location[] $inverse_filter
     * @return static
     */
    public function filterByDestination(array $filter, array $inverse_filter) : static {
        return new static(
            $this->timeType
            , array_values(
                array_filter(
                    array_map(
                        function (ServiceCall $call) use ($filter, $inverse_filter) {
                            $valid_portions = [];
                            // need to do the inverse filter for each destination portion
                            foreach ($this->getPortions($call->service) as $portion) {
                                $arrival_calls_for_portion = array_filter(
                                    $this->timeType->isArrival() ? array_reverse($call->getPrecedingPublicCalls()) : array_values($call->getSubsequentPublicCalls())
                                    , fn(ServiceCall $arrival_call) => array_key_exists($portion->uid, $this->getPortions($arrival_call->service))
                                );
                                $min_filtered_index = null;
                                if ($filter !== []) {
                                    $filtered = array_filter(
                                        $arrival_calls_for_portion,
                                        static fn(ServiceCall $arrival_call) => in_array(
                                            $arrival_call->timingPoint->location->getCrsOrTiplocCode(),
                                            array_map(fn($x) => $x->getCrsOrTiplocCode(), $filter)
                                        )
                                    );
                                    if ($filtered === []) {
                                        continue;
                                    }
                                    $min_filtered_index = array_key_first($filtered);
                                }
                                if ($inverse_filter !== []) {
                                    $inverse_filtered = array_filter(
                                        $arrival_calls_for_portion,
                                        static fn(ServiceCall $arrival_call) => in_array(
                                            $arrival_call->timingPoint->location->getCrsOrTiplocCode(),
                                            array_map(fn($x) => $x->getCrsOrTiplocCode(), $inverse_filter)
                                        )
                                    );
                                    if (array_find_key($inverse_filtered, fn($value, $key) => $min_filtered_index === null || $key < $min_filtered_index) !== null) {
                                        continue;
                                    }
                                }
                                $valid_portions[] = $portion->uid;
                            }
                            if ($valid_portions === []) {
                                return null;
                            }
                            return new ServiceCall(new Service(
                                $call->service->uid
                                , $call->service->date
                                , $call->service->period
                                , $call->service->mode
                                , $call->service->toc
                                , $call->service->timingPoints
                                , $call->service->shortTermPlanning
                                , $this->timeType->isArrival() ? array_filter($call->service->joins, fn(Service $portion) => in_array($portion->uid, $valid_portions)) : $call->service->joins
                                , $this->timeType->isDeparture() ? array_filter($call->service->divides, fn(Service $portion) => in_array($portion->uid, $valid_portions)) : $call->service->divides
                                , $call->service->divideFrom
                                , $call->service->joinTo
                            ), $call->callIndex);
                        }
                        , $this->calls
                    )
                )
            )
        );
    }

    /**
     * Group the services into sets which don't share calls.
     *
     * @return static[]
     */
    public function groupServices() : array {
        $station_groups = [];
        $result = [];
        foreach ($this->calls as $call) {
            $group_id = $station_groups === [] ? 0 : max($station_groups) + 1;
            foreach ($this->timeType->isArrival() ? $call->getPrecedingPublicCalls() : $call->getSubsequentPublicCalls() as $subsequent_call) {
                $timingPoint = $subsequent_call->timingPoint;
                $location = $timingPoint->location;
                if ($location->getCrsOrTiplocCode() !== null) {
                    $subsequent_crs = $location->getCrsOrTiplocCode();
                    if (isset($station_groups[$subsequent_crs])) {
                        $group_to_be_joined = $station_groups[$subsequent_crs];
                        if ($group_to_be_joined !== $group_id) {
                            foreach ($station_groups as &$station_group) {
                                if ($station_group === $group_id) {
                                    $station_group = $group_to_be_joined;
                                }
                            }
                            unset($station_group);
                            $result[$group_to_be_joined] = array_merge($result[$group_to_be_joined], $result[$group_id] ?? []);
                            unset($result[$group_id]);
                            $group_id = $group_to_be_joined;
                        }
                    } else {
                        $station_groups[$subsequent_crs] = $group_id;
                    }
                }
            }
            $result[$group_id][] = $call;
        }
        foreach ($result as &$group) {
            usort($group, fn(ServiceCall $a, ServiceCall $b) => $a->getTimestamp($this->timeType) <=> $b->getTimestamp($this->timeType));
        }
        unset($group);
        return array_map(
            fn(array $calls) => new static($this->timeType, $calls)
            , $result
        );
    }

    /**
     * @return Location[]
     */
    public function getDestinations() : array {
        $destinations = [];
        foreach ($this->calls as $service_call) {
            foreach (
                $this->getPortions($service_call->service) as $portion
            ) {
                $destination = ($this->timeType->isArrival() ? array_first($portion->timingPoints) : array_last($portion->timingPoints))->location;
                if (
                    array_filter(
                        $destinations,
                        static fn(Location $location) => $location->getCrsOrTiplocCode() === $destination->getCrsOrTiplocCode()
                    ) === []
                ) {
                    $destinations[] = $destination;
                }
            }
        }
        
        // remove destinations that are intermediate stations on other services
        foreach ($this->calls as $service_call) {
            $to_be_removed = [];
            foreach ($this->getSubsequentOrPrecedingCalls($service_call) as $subsequent_call) {
                $timingPoint = $subsequent_call->timingPoint;
                $intermediate_location = $timingPoint->location;
                foreach ($this->getPortions($subsequent_call->service) as $portion) {
                    $removing = $to_be_removed[$portion->uid] ?? [];
                    $destinations = array_filter($destinations, static fn(Location $location) => !in_array($location->getCrsOrTiplocCode(), $removing));
                    if (in_array($intermediate_location->getCrsOrTiplocCode(), array_map(fn(Location $location) => $location->getCrsOrTiplocCode(), $destinations))) {
                        $to_be_removed[$portion->uid][] = $intermediate_location->getCrsOrTiplocCode();
                    }
                }
            }
        }
        return array_values($destinations);
    }
    
    public function getViaPoint() : ?Location {
        $call = array_first($this->calls);
        if ($call === null) {
            return null;
        }
        $portions = $this->getPortions($call->service);
        $subsequent_calls = $this->getSubsequentOrPrecedingCalls($call);
        foreach ($subsequent_calls as $subsequent_call) {
            if (
                array_filter(
                    $portions
                    , fn(Service $portion) => !array_key_exists($portion->uid, $this->getPortions($subsequent_call->service))
                ) === []
            ) {
                // the subsequent call covers all portions of the current call
                $all_services_called = true;
                foreach ($this->calls as $other_call) {
                    $other_portions = $this->getPortions($other_call->service);
                    $called = false;
                    foreach ($this->getSubsequentOrPrecedingCalls($other_call) as $other_subsequent_call) {
                        $timingPoint1 = $subsequent_call->timingPoint;
                        $timingPoint2 = $other_subsequent_call->timingPoint;
                        if ($timingPoint2->location->getCrsOrTiplocCode() === $timingPoint1->location->getCrsOrTiplocCode()) {
                            if (
                                array_filter(
                                    $other_portions
                                    , fn(Service $portion) => !array_key_exists($portion->uid, $this->getPortions($other_subsequent_call->service))
                                ) === []
                            ) {
                                $called = true;
                            }
                        }
                    }
                    if (!$called) {
                        $all_services_called = false;
                        break;
                    }
                }
                if ($all_services_called) {
                    $timingPoint = $subsequent_call->timingPoint;
                    return $timingPoint->location;
                }
            }
        }
        return null;
    }

    /**
     * @param Service $service
     * @return Service[]
     */
    private function getPortions(Service $service) : array {
        return $this->timeType->isArrival() ? $service->getOriginPortions() : $service->getDestinationPortions();
    } 

    private function buildCallMatrix() : array {
        $result = [];
        foreach ($this->calls as $here_call) {
            $arrival_mode = $this->timeType->isArrival();
            foreach ($arrival_mode ? $here_call->getPrecedingPublicCalls() : $here_call->getSubsequentPublicCalls() as $there_call) {
                $timingPoint = $there_call->timingPoint;
                $location = $timingPoint->location;
                if ($location->getCrsOrTiplocCode() !== null) {
                    /** @var ServiceCall[]|null $existing */
                    $existing = &$result[$location->getCrsOrTiplocCode()][$there_call->service->uid . '_' . $there_call->service->date];
                    $timestamp = $there_call->getTimestamp($arrival_mode ? TimeType::PUBLIC_DEPARTURE : TimeType::PUBLIC_ARRIVAL);
                    if ($existing === null || ($arrival_mode
                            ? $existing[1]->getTimestamp(TimeType::PUBLIC_DEPARTURE) < $timestamp
                            : $existing[1]->getTimestamp(TimeType::PUBLIC_ARRIVAL) > $timestamp
                    )) {
                        $existing = [$here_call, $there_call];
                    }
                    unset($existing);
                }
            }
        }
        return $result;
    }

    /** @var array<string, array<string, ServiceCall[]>> */
    private array $callMatrix;

    /** 
     * @return ServiceCall[] 
     */
    private function getSubsequentOrPrecedingCalls(ServiceCall $service_call) : array {
        return $this->timeType->isArrival() ? array_reverse($service_call->getPrecedingPublicCalls())
            : $service_call->getSubsequentPublicCalls();
    }

}