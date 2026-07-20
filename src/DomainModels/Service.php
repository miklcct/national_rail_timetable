<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\DomainModels;

use DateTimeZone;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Miklcct\NationalRailTimetable\DomainModels\Points\CallingPoint;
use Miklcct\NationalRailTimetable\DomainModels\Points\DestinationPoint;
use Miklcct\NationalRailTimetable\DomainModels\Points\OriginPoint;
use Miklcct\NationalRailTimetable\DomainModels\Points\TimingPoint;
use Miklcct\NationalRailTimetable\Enums\AssociationCategory;
use Miklcct\NationalRailTimetable\Enums\AssociationDay;
use Miklcct\NationalRailTimetable\Enums\Mode;
use Miklcct\NationalRailTimetable\Enums\ShortTermPlanning;
use Miklcct\NationalRailTimetable\Enums\TimeType;
use Miklcct\NationalRailTimetable\Exceptions\ServiceNotFound;
use Miklcct\NationalRailTimetable\Models\Association;
use Miklcct\NationalRailTimetable\Models\BaseSchedule;
use Miklcct\NationalRailTimetable\Models\Location;
use Miklcct\NationalRailTimetable\Models\PhysicalStation;
use Miklcct\NationalRailTimetable\Models\Schedule;
use Miklcct\NationalRailTimetable\Models\StopTime;
use Miklcct\NationalRailTimetable\Models\ZSchedule;
use Miklcct\NationalRailTimetable\ValueObjects\Date;
use Miklcct\NationalRailTimetable\ValueObjects\Time;
use RuntimeException;
use function Miklcct\NationalRailTimetable\Views\get_all_tocs;
use function Safe\preg_match as preg_match;

readonly class Service {
    public array $joins;
    public array $divides;

    public function __construct(
        public string $uid,
        public Date $date,
        public Period $period,
        public Mode $mode,
        public ?string $toc,
        /** @var TimingPoint[] */
        public array $timingPoints,
        public ShortTermPlanning $shortTermPlanning,
        /** @var Service[] */
        array $joins,
        /** @var Service[] */
        array $divides,
        public ?Service $divideFrom = null,
        public ?Service $joinTo = null,
        /** @var int[][]|null */
        ?array $line = null,
        /** @var array<string, int[]>|null */
        ?array $locationMap = null,
    ) {
        $this->divides = array_map(
            fn(Service $service) => new Service(
                $service->uid
                , $service->date
                , $service->period
                , $service->mode
                , $service->toc
                , $service->timingPoints
                , $service->shortTermPlanning
                , $service->joins
                , $service->divides
                , $this
                , $service->joinTo
                , $service->line
                , $service->locationMap
            )
            , $divides
        );
        $this->joins = array_map(
            fn(Service $service) => new Service(
                $service->uid
                , $service->date
                , $service->period
                , $service->mode
                , $service->toc
                , $service->timingPoints
                , $service->shortTermPlanning
                , $service->joins
                , $service->divides
                , $service->divideFrom
                , $this
                , $service->line
                , $service->locationMap
            )
            , $joins
        );
        if ($line === null) {
            $line = [];
            foreach ($timingPoints as $point) {
                $location = $point->location;
                $tiploc = $location->tiploc_code;
                $data = Location::getCoordinates($tiploc);
                if ($data !== null) {
                    $line[] = $data;
                } elseif ($location instanceof PhysicalStation) {
                    $line[] = [$location->easting, $location->northing];
                }
            }
        }
        $this->line = $line;

        if ($locationMap === null) {
            $locationMap = [];
            foreach ($timingPoints as $index => $point) {
                $code = $point->location->getCrsOrTiplocCode();
                if ($code !== null) {
                    $locationMap[$code][] = $index;
                }
            }
        }
        $this->locationMap = $locationMap;
    }

    /** @var array<string, int[]> */
    private array $locationMap;

    /** @var int[][] */
    public array $line;
    
    public static function loadFromDatabase(string $uid_or_rsid, Date $date, bool $excludeStp = false, ?string $recursed_from = null) : ?Service {
        static $cache = [];
        $cache_key = sprintf('%s|%s|%d|%s', $uid_or_rsid, $date, $excludeStp, $recursed_from ?? '');
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }
        $uid_or_rsid = strtoupper($uid_or_rsid);
        $weekday_columns = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $is_rsid = self::isRsid($uid_or_rsid);
        $is_z_schedule = !$is_rsid && str_starts_with($uid_or_rsid, 'Z');
        $builder = $is_rsid
            ? Schedule::where(function (EloquentBuilder $query) use ($uid_or_rsid) {
                strlen($uid_or_rsid) === 6
                    ? $query->where(function (EloquentBuilder $query) use ($uid_or_rsid) {
                    $query->whereLike('schedule_extra.retail_train_id', "$uid_or_rsid%")
                        ->orWhereHas('serviceChanges', function (EloquentBuilder $query) use ($uid_or_rsid) {
                            $query->whereLike('retail_train_id', "$uid_or_rsid%");
                        });
                })
                    ->whereDoesntHave('parentAssociations')
                    : $query->where('schedule_extra.retail_train_id', $uid_or_rsid)
                        ->orWhereRelation('serviceChanges', 'retail_train_id', $uid_or_rsid);
            })
            : ($is_z_schedule
                ? ZSchedule::where('train_uid', $uid_or_rsid)
                : Schedule::where(
                    'train_uid',
                    $uid_or_rsid
                ));
        $builder = $builder
            ->whereDate('runs_from', '<=', $date->toDateTimeImmutable(new Time(23, 59)))
            ->whereDate('runs_to', '>=', $date->toDateTimeImmutable(new Time(0, 0)))
            ->where($weekday_columns[$date->getWeekday()], true);
        if ($excludeStp) {
            $builder->where('stp_indicator', ShortTermPlanning::PERMANENT->value);
        }

        /** @var BaseSchedule $schedule */
        $schedule = $builder->with([
            'stopTimes' => function (Relation $relation) use ($is_z_schedule) {
                $relation->with('physicalStation');
                if (!$is_z_schedule) {
                    $relation->with('tiploc')->with('serviceChange');
                }
            },
            'parentAssociations',
            'childAssociations',
        ])->orderBy('stp_indicator')->first();

        if ($schedule === null) {
            throw new ServiceNotFound($uid_or_rsid, $date);
        }

        return $cache[$cache_key] = self::processScheduleEntry($schedule, $date, $excludeStp, $recursed_from);
    }

    public function getTocName() : string {
        return get_all_tocs()[$this->toc] ?? $this->toc;
    }


    public function getOriginPortions() : array {
        return $this->divideFrom?->getOriginPortions() 
            ?? ($this->joins ? array_merge(...array_map(fn(Service $service) => $service->getOriginPortions(), $this->joins)) : [$this->uid => $this]);
    }

    public function getDestinationPortions() : array {
        return $this->joinTo?->getDestinationPortions() 
            ?? ($this->divides ? array_merge(...array_map(fn(Service $service) => $service->getDestinationPortions(), $this->divides)) : [$this->uid => $this]);
    }

    /**
     * @param Location $location
     * @return ServiceCall[]
     */
    public function findCallInSameUid(Location $location, ?Service $recursed_from = null) : array {
        /** @var ServiceCall[] $result */
        $result = [];
        
        $code = $location->getCrsOrTiplocCode();
        if (isset($this->locationMap[$code])) {
            foreach ($this->locationMap[$code] as $index) {
                $result[] = new ServiceCall($this, $index);
            }
        }
        
        foreach (array_filter([$this->divideFrom, $this->joinTo, ...$this->joins, ...$this->divides], fn(?Service $portion) => $portion !== $recursed_from && $portion?->uid === $this->uid) as $portion) {
            $result = array_merge($result, $portion->findCallInSameUid($location, $this));
        }
        
        return $result;
    }

    public static function isRsid(string $uid_or_rsid) : int {
        return preg_match('/^[A-Z]{2}(\d{4}|\d{6})$/', $uid_or_rsid);
    }

    public function getAbsoluteTimeZone() : DateTimeZone {
        // handle extra departures on London Overground during autumn BST/GMT change
        $time = $this->timingPoints[0]->getTime(TimeType::WORKING_DEPARTURE);
        if (
            $this->toc === 'LO' && $this->shortTermPlanning === ShortTermPlanning::NEW
            && $this->period->from->compare($this->period->to) === 0
            && $this->period->from->month === 10
            && $this->period->from->day >= 25
            && $this->period->weekdays[0]
            && $time->hours === 1
        ) {
            return new DateTimeZone("UTC");
        }

        $date_time = $this->date->toDateTimeImmutable($time);
        // The difference is to handle departure time in the "missing hour" such as the 01:05 from Waterloo
        $utc_offset = $date_time->getOffset() + ($time->secondsFromOrigin - Time::fromDateTimeInterface($date_time)->secondsFromOrigin);
        $negative = $utc_offset < 0;
        $hours = intdiv(abs($utc_offset), 60 * 60);
        $minutes = intdiv(abs($utc_offset) - $hours * 60 * 60, 60);
        return new DateTimeZone(sprintf('%s%02d:%02d', $negative ? '-' : '+', $hours, $minutes));
    }
    
    private static function fromSchedule(
        BaseSchedule $schedule,
        Date $date,
        bool $excludeStp,
        array $associations,
        ?int $from_index = null,
        ?int $to_index = null,
        array $joins = [],
        array $divides = [],
        ?array $allTimingPoints = null,
        ?array $allCoordinates = null,
        ?array $locationMap = null
    ) : Service {
        static $timingPointsCache = [];
        $scheduleKey = $schedule->getTable() . ':' . $schedule->getKey();
        if ($allTimingPoints === null && isset($timingPointsCache[$scheduleKey])) {
            [$allTimingPoints, $allCoordinates, $locationMap] = $timingPointsCache[$scheduleKey];
        }
        if ($allTimingPoints === null) {
            $allTimingPoints = [];
            $allCoordinates = [];
            $locationMap = [];
            $lastTimingPoint = null;
            $serviceProperty = $schedule->getServiceProperty();
            foreach ($schedule->stopTimes as $i => $stopTime) {
                if ($stopTime instanceof StopTime && $stopTime->serviceChange !== null) {
                    $serviceProperty = $stopTime->serviceChange->getServiceProperty();
                }
                $timingPoint = $stopTime->toDomainModel($serviceProperty, $lastTimingPoint);
                $allTimingPoints[] = $timingPoint;
                $lastTimingPoint = $timingPoint;

                $location = $timingPoint->location;
                $tiploc = $location->tiploc_code;
                $data = Location::getCoordinates($tiploc);
                if ($data !== null) {
                    $allCoordinates[$i] = $data;
                } elseif ($location instanceof PhysicalStation) {
                    $allCoordinates[$i] = [$location->easting, $location->northing];
                }

                $code = $location->getCrsOrTiplocCode();
                if ($code !== null) {
                    $locationMap[$code][] = $i;
                }
            }
            $timingPointsCache[$scheduleKey] = [$allTimingPoints, $allCoordinates, $locationMap];
        }

        ksort($associations);
        $get_child = fn(array $data) => $data[1];

        foreach ($associations as $assoc_index => $association_data) {
            if ($assoc_index > ($from_index ?? 0) && $assoc_index < ($to_index ?? sizeof($schedule->stopTimes) - 1)) {
                $joins = array_map($get_child, array_filter($association_data, fn(array $data) => $data[0]->assoc_cat === AssociationCategory::JOIN));
                $divides = array_map($get_child, array_filter($association_data, fn(array $data) => $data[0]->assoc_cat === AssociationCategory::DIVIDE));
                if ($joins && $divides) {
                    throw new RuntimeException("This application currently doesn't support joins and divides simultaneously at the same location.");
                }
                if ($joins) {
                    return self::fromSchedule($schedule, $date, $excludeStp, $associations, $assoc_index, $to_index, [
                        self::fromSchedule($schedule, $date, $excludeStp, $associations, $from_index, $assoc_index, [], [], $allTimingPoints, $allCoordinates),
                        ...$joins,
                    ], [], $allTimingPoints, $allCoordinates);
                }
                if ($divides) {
                    return self::fromSchedule($schedule, $date, $excludeStp, $associations, $from_index, $assoc_index, [], [
                        self::fromSchedule($schedule, $date, $excludeStp, $associations, $assoc_index, $to_index, [], [], $allTimingPoints, $allCoordinates),
                        ...$divides,
                    ], $allTimingPoints, $allCoordinates);
                }
            }
        }

        $timingPoints = [];
        $line = [];
        $from_index_val = $from_index ?? 0;
        $to_index_val = $to_index ?? count($allTimingPoints) - 1;
        for ($i = $from_index_val; $i <= $to_index_val; ++$i) {
            $timingPoint = $allTimingPoints[$i];
            if ($i === $from_index_val && !($timingPoint instanceof OriginPoint)) {
                assert($timingPoint instanceof CallingPoint);
                $timingPoint = new OriginPoint($timingPoint->location, $timingPoint->locationSuffix, $timingPoint->platform, $timingPoint->line, $timingPoint->workingDeparture, $timingPoint->publicDeparture, $timingPoint->engineeringAllowance, $timingPoint->pathingAllowance, $timingPoint->performanceAllowance, $timingPoint->activities, $timingPoint->serviceProperty);
            }
            if ($i === $to_index_val && !($timingPoint instanceof DestinationPoint)) {
                assert($timingPoint instanceof CallingPoint);
                $timingPoint = new DestinationPoint($timingPoint->location, $timingPoint->locationSuffix, $timingPoint->platform, $timingPoint->path, $timingPoint->workingArrival, $timingPoint->publicArrival, $timingPoint->activities);
            }
            $timingPoints[] = $timingPoint;
            if (isset($allCoordinates[$i])) {
                $line[] = $allCoordinates[$i];
            }
        }
        return new Service(
            $schedule->train_uid,
            $date,
            $schedule->period,
            $schedule->getMode(),
            $schedule->atoc_code,
            $timingPoints,
            $schedule->stp_indicator,
            $joins,
            $divides,
            line: $line,
            locationMap: ($from_index === null && $to_index === null) ? $locationMap : null
        );
    }

    public static function processScheduleEntry(
        BaseSchedule $schedule,
        Date $date,
        bool $exclude_stp,
        ?string $recursed_from = null,
    ) : ?Service {
        static $cache = [];
        $cache_key = sprintf(
            '%s:%s:%s:%d:%s'
            , $schedule->getTable()
            , $schedule->getKey()
            , (string)$date
            , $exclude_stp ? 1 : 0
            , $recursed_from ?? ''
        );
        if (array_key_exists($cache_key, $cache)) {
            return $cache[$cache_key];
        }
        if ($schedule->stp_indicator === ShortTermPlanning::CANCEL) {
            return $cache[$cache_key] = null;
        }
        $associations = [];
        if ($schedule instanceof Schedule) {
            /** @var Association $association */
            foreach ($schedule->parentAssociations as $association) {
                if ($association->base_uid !== $recursed_from
                    && (!$exclude_stp
                        || $association->stp_indicator
                        === ShortTermPlanning::PERMANENT)) {
                    $association_date = $date->addDays(
                        match ($association->assoc_date_ind) {
                            AssociationDay::TOMORROW => -1,
                            AssociationDay::YESTERDAY => 1,
                            default => 0,
                        }
                    );
                    if ($association->period->isActive($association_date)) {
                        if ($association->stp_indicator === ShortTermPlanning::CANCEL) {
                            break;
                        }
                        if ($association->relationLoaded('baseSchedules')) {
                            foreach ($association->baseSchedules as $baseSchedule) {
                                if ((!$exclude_stp || $baseSchedule->stp_indicator === ShortTermPlanning::PERMANENT) && $baseSchedule->period->isActive($association_date)) {
                                    $result = self::processScheduleEntry($baseSchedule, $association_date, $exclude_stp);
                                    break;
                                }
                            }
                        } else {
                            $result = Service::loadFromDatabase($association->base_uid, $association_date, $exclude_stp);
                        }
                        if ($result !== null) {
                            foreach ($result->joins as $join) {
                                if ($join->uid === $association->assoc_uid) {
                                    return $cache[$cache_key] = $join;
                                }
                            }
                            foreach ($result->divides as $divide) {
                                if ($divide->uid === $association->assoc_uid) {
                                    return $cache[$cache_key] = $divide;
                                }
                            }
                        }
                        return $cache[$cache_key] = $result;
                    }
                }
            }

            $processed_child_uids = [];
            foreach ($schedule->childAssociations as $association) {
                if (!in_array($association->assoc_uid, $processed_child_uids)
                    && (!$exclude_stp || $association->stp_indicator === ShortTermPlanning::PERMANENT)
                    && $association->period->isActive($date)
                ) {
                    $processed_child_uids[] = $association->assoc_uid;
                    if ($association->stp_indicator !== ShortTermPlanning::CANCEL) {
                        $assoc_index = array_find_key(
                            $schedule->stopTimes->all(),
                            function (StopTime $stopTime) use ($association) {
                                return $stopTime->location === $association->assoc_location
                                    && (string)$stopTime->suffix === $association->base_location_suffix;
                            }
                        );
                        if ($assoc_index !== null) {
                            $child_date = $date->addDays(
                                match ($association->assoc_date_ind) {
                                    AssociationDay::TOMORROW => 1,
                                    AssociationDay::YESTERDAY => -1,
                                    default => 0,
                                }
                            );
                            if ($association->relationLoaded('childSchedules')) {
                                $child = null;
                                foreach ($association->childSchedules as $child_schedule) {
                                    if ((!$exclude_stp || $child_schedule->stp_indicator === ShortTermPlanning::PERMANENT) && $child_schedule->period->isActive($child_date)) {
                                        $child = self::processScheduleEntry($child_schedule, $child_date, $exclude_stp, $schedule->train_uid);
                                        break;
                                    }
                                }
                            } else {
                                $child = self::loadFromDatabase(
                                    $association->assoc_uid,
                                    $child_date,
                                    $exclude_stp,
                                    $schedule->train_uid
                                );
                            }
                            if ($child !== null) {
                                $associations[$assoc_index][] = [$association, $child];
                            }
                        }
                    }
                }
            }
        }

        return $cache[$cache_key] = self::fromSchedule($schedule, $date, $exclude_stp, $associations);
    }
}
