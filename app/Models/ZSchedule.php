<?php
declare(strict_types=1);

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
                DB::raw('(select schedule, atoc_code from z_schedule_extra) as z_schedule_extra'),
                'z_schedule.id',
                '=',
                'z_schedule_extra.schedule',
                'left'
            );
        });
    }

    public function stopTimes() : HasMany {
        return $this->hasMany(ZStopTime::class, 'z_schedule')
            ->orderBy('id');
    }
}
