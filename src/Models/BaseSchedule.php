<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Models;

use Illuminate\Database\Eloquent\Builder;
use Miklcct\NationalRailTimetable\Casts\Caterings;
use Miklcct\NationalRailTimetable\DomainModels\Period;
use Miklcct\NationalRailTimetable\DomainModels\Service;
use Miklcct\NationalRailTimetable\DomainModels\ServiceProperty;
use Miklcct\NationalRailTimetable\Enums\Mode;
use Miklcct\NationalRailTimetable\Enums\Power;
use Miklcct\NationalRailTimetable\Enums\Reservation;
use Miklcct\NationalRailTimetable\Enums\ShortTermPlanning;
use Miklcct\NationalRailTimetable\Enums\TrainCategory;
use Miklcct\NationalRailTimetable\Enums\TrainClass;
use Miklcct\NationalRailTimetable\ValueObjects\Date;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

abstract class BaseSchedule extends Model {
    use HasServiceProperty;

    public const SERVICE_PROPERTY_CASTS
        = [
            'train_category' => TrainCategory::class,
            'power_type' => Power::class,
            'speed' => 'int',
            'train_class' => TrainClass::class,
            'sleepers' => TrainClass::class,
            'reservations' => Reservation::class,
            'catering_code' => Caterings::class,
        ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts
        = [
            'runs_from' => 'immutable_date',
            'runs_to' => 'immutable_date',
            'monday' => 'bool',
            'tuesday' => 'bool',
            'wednesday' => 'bool',
            'thursday' => 'bool',
            'friday' => 'bool',
            'saturday' => 'bool',
            'sunday' => 'bool',
            'bank_holiday_running' => 'bool',
            'stp_indicator' => ShortTermPlanning::class,
        ] + self::SERVICE_PROPERTY_CASTS;

    public function scopeDateAndStp(Builder $builder, Date $from, Date $to, bool $permanent_only) : void {
        $builder->where('runs_from', '<=', $to->toDateTimeImmutable());
        $builder->where('runs_to', '>=', $from->toDateTimeImmutable());
        if ($permanent_only) {
            $builder->where('stp_indicator', ShortTermPlanning::PERMANENT);
        }
    }

    public abstract function stopTimes() : HasMany;

    public function getMode() : Mode {
        return match ($this->train_status) {
            'S', '4' => Mode::SHIP,
            'B', '5' => Mode::BUS,
            default => Mode::TRAIN,
        };
    }
}
