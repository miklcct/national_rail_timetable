<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    protected static function booted(): void
    {
        static::addGlobalScope('join_extra', static function (Builder $builder) {
            $builder->join('schedule_extra', 'schedule.id', '=', 'schedule_extra.schedule');
        });
    }

    public function stopTimes(): HasMany {
        return $this->hasMany(StopTime::class, 'schedule')
            ->orderBy('id');
    }
}
