<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;
use Miklcct\NationalRailTimetable\DomainModels\Period;
use Miklcct\NationalRailTimetable\Enums\ShortTermPlanning;
use Miklcct\NationalRailTimetable\ValueObjects\Date;

class Schedule extends BaseSchedule {
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'schedule';

    /**
     * The "booted" method of the model.
     */
    protected static function booted() : void {
        static::addGlobalScope('join_extra', static function (Builder $builder) {
            $builder->join(
                $builder->getQuery()->raw('(select schedule, atoc_code, retail_train_id from schedule_extra) as schedule_extra'),
                'schedule.id',
                '=',
                'schedule_extra.schedule',
                'left'
            );
        });
    }

    public function stopTimes() : HasMany {
        return $this->hasMany(StopTime::class, 'schedule')
            ->orderBy('id');
    }
    
    public function period() : Attribute {
        return Attribute::make(
            get: fn() => new Period(
                Date::fromDateTimeInterface($this->runs_from),
                Date::fromDateTimeInterface($this->runs_to),
                [
                    $this->sunday,
                    $this->monday,
                    $this->tuesday,
                    $this->wednesday,
                    $this->thursday,
                    $this->friday,
                    $this->saturday,
                ]
            ),
        );
    }

    public function childAssociations() : HasMany {
        return $this->hasMany(Association::class, 'base_uid', 'train_uid')->orderBy('stp_indicator');
    }

    public function parentAssociations() : HasMany {
        return $this->hasMany(Association::class, 'assoc_uid', 'train_uid')->orderBy('stp_indicator');
    }

    public function serviceChanges() : HasManyThrough {
        return $this->throughStopTimes()->hasServiceChange();
    }
}
