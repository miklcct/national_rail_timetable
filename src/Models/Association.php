<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Miklcct\NationalRailTimetable\DomainModels\Period;
use Miklcct\NationalRailTimetable\Enums\AssociationCategory;
use Miklcct\NationalRailTimetable\Enums\AssociationDay;
use Miklcct\NationalRailTimetable\Enums\AssociationType;
use Miklcct\NationalRailTimetable\Enums\ShortTermPlanning;
use Miklcct\NationalRailTimetable\ValueObjects\Date;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Association extends Model {
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'association';

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
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'monday' => 'bool',
            'tuesday' => 'bool',
            'wednesday' => 'bool',
            'thursday' => 'bool',
            'friday' => 'bool',
            'saturday' => 'bool',
            'sunday' => 'bool',
            'assoc_cat' => AssociationCategory::class,
            'assoc_date_ind' => AssociationDay::class,
            'association_type' => AssociationType::class,
            'stp_indicator' => ShortTermPlanning::class,
        ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted() : void {
        static::addGlobalScope(static function (Builder $builder) {
            $builder->where('association_type', '=', 'P')
                ->whereIn('assoc_cat', ['JJ', 'VV']);
        });
    }

    public function scopeDateAndStp(Builder $builder, Date $from, Date $to, bool $permanent_only) : void {
        $builder->where('start_date', '<=', $to->toDateTimeImmutable());
        $builder->where('end_date', '>=', $from->toDateTimeImmutable());
        if ($permanent_only) {
            $builder->where('stp_indicator', ShortTermPlanning::PERMANENT);
        }
    }

    public function baseSchedules() : HasMany {
        return $this->hasMany(Schedule::class, 'train_uid', 'base_uid')->orderBy('stp_indicator');
    }

    public function childSchedules() : HasMany {
        return $this->hasMany(Schedule::class, 'train_uid', 'assoc_uid')->orderBy('stp_indicator');
    }

    public function period() : Attribute {
        return Attribute::make(
            get: fn() => new Period(
                Date::fromDateTimeInterface($this->start_date),
                Date::fromDateTimeInterface($this->end_date),
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
}
