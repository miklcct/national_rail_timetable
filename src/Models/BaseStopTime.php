<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Models;

use Miklcct\NationalRailTimetable\Casts\Activities;
use Miklcct\NationalRailTimetable\Casts\Allowance;
use Miklcct\NationalRailTimetable\DomainModels\Points\CallingPoint;
use Miklcct\NationalRailTimetable\DomainModels\Points\DestinationPoint;
use Miklcct\NationalRailTimetable\DomainModels\Points\OriginPoint;
use Miklcct\NationalRailTimetable\DomainModels\Points\PassingPoint;
use Miklcct\NationalRailTimetable\DomainModels\Points\TimingPoint;
use Miklcct\NationalRailTimetable\DomainModels\ServiceProperty;
use Miklcct\NationalRailTimetable\Enums\Activity;
use Miklcct\NationalRailTimetable\ValueObjects\Time;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;

abstract class BaseStopTime extends Model {
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    public abstract function scheduleModel() : BelongsTo;

    public abstract function physicalStation() : Relation;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts() : array {
        return [
            'scheduled_arrival_time' => Time::class,
            'scheduled_departure_time' => Time::class,
            'scheduled_pass_time' => Time::class,
            'public_arrival_time' => Time::class,
            'public_departure_time' => Time::class,
            'activity' => Activities::class,
            'engineering_allowance' => Allowance::class,
            'pathing_allowance' => Allowance::class,
            'performance_allowance' => Allowance::class,
        ];
    }

    public function toDomainModel(ServiceProperty $serviceProperty, ?TimingPoint $previous) : TimingPoint {
        static $location_cache = [];
        $tiploc_code = $this->location;
        if (!isset($location_cache[$tiploc_code])) {
            $location_cache[$tiploc_code] = $this->physicalStation ?? $this->tiploc;
        }
        $location = $location_cache[$tiploc_code];

        $previous_time = $previous === null ? null : $previous->getAnyTime();
        if (in_array(Activity::TRAIN_BEGINS, $this->activity)) {
            return new OriginPoint(
                $location,
                $this->suffix,
                $this->platform,
                $this->line,
                $this->scheduled_departure_time->applyDayOffset($previous_time),
                $this->public_departure_time?->applyDayOffset($previous_time),
                $this->engineering_allowance,
                $this->pathing_allowance,
                $this->performance_allowance,
                $this->activity,
                $serviceProperty
            );
        } elseif (in_array(Activity::TRAIN_FINISHES, $this->activity)) {
            return new DestinationPoint(
                $location,
                $this->suffix,
                $this->platform,
                $this->path,
                $this->scheduled_arrival_time->applyDayOffset($previous_time),
                $this->public_arrival_time?->applyDayOffset($previous_time),
                $this->activity
            );
        } elseif ($this->scheduled_pass_time !== null) {
            return new PassingPoint(
                $location,
                $this->suffix,
                $this->platform,
                $this->path,
                $this->line,
                $this->scheduled_pass_time->applyDayOffset($previous_time),
                $this->engineering_allowance,
                $this->pathing_allowance,
                $this->performance_allowance,
                $this->activity,
                $serviceProperty
            );
        } else {
            return new CallingPoint(
                $location,
                $this->suffix,
                $this->platform,
                $this->path,
                $this->line,
                $this->scheduled_arrival_time->applyDayOffset($previous_time),
                $this->public_arrival_time?->applyDayOffset($previous_time),
                $this->scheduled_departure_time->applyDayOffset($previous_time),
                $this->public_departure_time?->applyDayOffset($previous_time),
                $this->engineering_allowance,
                $this->pathing_allowance,
                $this->performance_allowance,
                $this->activity,
                $serviceProperty
            );
        }
    }
}
