<?php
declare(strict_types=1);

namespace App\Models;

use App\Casts\Caterings;
use App\DomainModels\Period;
use App\DomainModels\Service;
use App\DomainModels\ServiceProperty;
use App\Enums\Mode;
use App\Enums\Power;
use App\Enums\Reservation;
use App\Enums\ShortTermPlanning;
use App\Enums\TrainCategory;
use App\Enums\TrainClass;
use App\ValueObjects\Date;
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

    public abstract function stopTimes() : HasMany;

    public function getMode() : Mode {
        return match ($this->train_status) {
            'S', '4' => Mode::SHIP,
            'B', '5' => Mode::BUS,
            default => Mode::TRAIN,
        };
    }

    public function toDomainModel() : Service {
        $timingPoints = [];
        $serviceProperty = $this->getServiceProperty();
        foreach ($this->stopTimes as $stopTime) {
            if ($stopTime instanceof StopTime && $stopTime->serviceChange !== null) {
                $serviceProperty = $stopTime->serviceChange->getServiceProperty();
            }
            $timingPoints[] = $stopTime->toDomainModel($serviceProperty);
        }
        return new Service(
            $this->train_uid,
            new Period(
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
            $this->getMode(),
            $this->atoc_code,
            $timingPoints,
            $this->stp_indicator
        );
    }
}
