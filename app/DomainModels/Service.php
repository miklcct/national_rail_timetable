<?php
declare(strict_types=1);

namespace App\DomainModels;

use App\DomainModels\Points\TimingPoint;
use App\Enums\Mode;
use App\Enums\ShortTermPlanning;
use App\Models\BaseSchedule;
use App\Models\Schedule;
use App\Models\ZSchedule;
use App\ValueObjects\Date;
use App\ValueObjects\Time;
use Illuminate\Database\Eloquent\Relations\Relation;

readonly class Service {
    public function __construct(
        public string $uid,
        public Period $period,
        public Mode $mode,
        public string $toc,
        /** @var TimingPoint[] */
        public array $timingPoints,
        public ShortTermPlanning $shortTermPlanning
    ) {
    }

    public static function loadFromDatabase(string $uid, Date $date, bool $excludeStp = false) : ?Service {
        $weekday_columns = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $builder = str_starts_with($uid, 'Z')
            ? ZSchedule::where('train_uid', $uid)
            : Schedule::where(
                'train_uid',
                $uid
            );
        $builder = $builder
            ->whereDate('runs_from', '<=', $date->toDateTimeImmutable(new Time(23, 59)))
            ->whereDate('runs_to', '>=', $date->toDateTimeImmutable(new Time(0, 0)))
            ->where($weekday_columns[$date->getWeekday()], true);
        if ($excludeStp) {
            $builder->where('stp_indicator', ShortTermPlanning::PERMANENT->value);
        }
        $schedule = $builder->with([
            'stopTimes' => function (Relation $relation) {
                $relation->with('serviceChange');
            },
        ])->orderBy('stp_indicator')->first();

        return $schedule === null ? null : $schedule->toDomainModel();
    }
}
