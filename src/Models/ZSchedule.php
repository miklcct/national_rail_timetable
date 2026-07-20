<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Miklcct\NationalRailTimetable\Enums\ShortTermPlanning;

class ZSchedule extends BaseSchedule {
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'z_schedule';

    /**
     * The "booted" method of the model.
     */
    protected static function booted() : void {
        static::addGlobalScope('join_extra', static function (Builder $builder) {
            $builder->join(
                $builder->getQuery()->raw('(select schedule, atoc_code from z_schedule_extra) as z_schedule_extra'),
                'z_schedule.id',
                '=',
                'z_schedule_extra.schedule',
                'left'
            );
        });
    }

    public function scopePermanentOnly(Builder $builder) : void {
        $builder->where('stp_indicator', ShortTermPlanning::PERMANENT);
    }

    public function stopTimes() : HasMany {
        return $this->hasMany(ZStopTime::class, 'z_schedule')
            ->orderBy('id');
    }
}
