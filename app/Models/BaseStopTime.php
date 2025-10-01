<?php
declare(strict_types=1);

namespace App\Models;

use App\Casts\Activities;
use App\Casts\Allowance;
use App\DomainModels\Points\CallingPoint;
use App\DomainModels\Points\DestinationPoint;
use App\DomainModels\Points\OriginPoint;
use App\DomainModels\Points\PassingPoint;
use App\DomainModels\Points\TimingPoint;
use App\DomainModels\ServiceProperty;
use App\Enums\Activity;
use App\ValueObjects\Time;
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

    public function toDomainModel(ServiceProperty $serviceProperty) : TimingPoint {
        if (in_array(Activity::TRAIN_BEGINS, $this->activity)) {
            return new OriginPoint(
                $this->tiploc,
                $this->physicalStation,
                $this->suffix,
                $this->platform,
                $this->line,
                $this->scheduled_departure_time,
                $this->public_departure_time,
                $this->engineering_allowance,
                $this->pathing_allowance,
                $this->performance_allowance,
                $this->activity,
                $serviceProperty
            );
        } elseif (in_array(Activity::TRAIN_FINISHES, $this->activity)) {
            return new DestinationPoint(
                $this->tiploc,
                $this->physicalStation,
                $this->suffix,
                $this->platform,
                $this->path,
                $this->scheduled_arrival_time,
                $this->public_arrival_time,
                $this->activity
            );
        } elseif ($this->scheduled_pass_time !== null) {
            return new PassingPoint(
                $this->tiploc,
                $this->physicalStation,
                $this->suffix,
                $this->platform,
                $this->path,
                $this->line,
                $this->scheduled_pass_time,
                $this->engineering_allowance,
                $this->pathing_allowance,
                $this->performance_allowance,
                $this->activity,
                $serviceProperty
            );
        } else {
            return new CallingPoint(
                $this->tiploc,
                $this->physicalStation,
                $this->suffix,
                $this->platform,
                $this->path,
                $this->line,
                $this->scheduled_arrival_time,
                $this->public_arrival_time,
                $this->scheduled_departure_time,
                $this->public_departure_time,
                $this->engineering_allowance,
                $this->pathing_allowance,
                $this->performance_allowance,
                $this->activity,
                $serviceProperty
            );
        }
    }
}
