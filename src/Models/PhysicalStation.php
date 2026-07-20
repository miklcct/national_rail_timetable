<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use function Safe\file_get_contents;
use function Safe\json_decode;

class PhysicalStation extends Location
{
    /**
     * Value for cate_interchange_status to denote that this entry represents
     * a secondary TIPLOC for the station
     */
    public const INTERCHANGE_STATUS_SECONDARY = 9;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'physical_station';

    /**
     * The "booted" method of the model.
     */
    protected static function booted() : void {
        static::addGlobalScope(static function (Builder $builder) {
            $builder->whereNot('physical_station.id', 1);
        });
    }

    public function tiploc() : BelongsTo {
        return $this->belongsTo(Tiploc::class, 'tiploc_code', 'tiploc_code');
    }

    public function stopTimes() : HasManyThrough {
        return $this->hasManyThrough(StopTime::class, Tiploc::class, 'tiploc_code', 'location', 'tiploc_code', 'tiploc_code');
    }

    public function ZStopTimes() : HasMany {
        return $this->hasMany(ZStopTime::class, 'location', 'crs_code');
    }

    public function scopePrimary(Builder $query) : void {
        $query->where('cate_interchange_status', '<>', self::INTERCHANGE_STATUS_SECONDARY);
    }

    public function tocInterchanges() : HasMany {
        return $this->hasMany(TocInterchange::class, 'crs', 'crs_code');
    }
    
    public function incomingFixedLinks() : HasMany {
        return $this->hasMany(FixedLink::class, 'destination', 'crs_code');
    }
    
    public function outgoingFixedLinks() : HasMany {
        return $this->hasMany(FixedLink::class, 'origin', 'crs_code');
    }

    public function getConnectionTime(?string $from_toc, ?string $to_toc) : int {
        foreach ($this->tocInterchanges as $interchange) {
            if ($interchange->from_toc === $from_toc && $interchange->to_toc === $to_toc) {
                return $interchange->time;
            }
        }
        return $this->minimum_change_time;
    }

    public function getName() : string {
        return $this->station_name;
    }

    protected function easting() : Attribute {
        return Attribute::make(
            get: fn(int $value) => ($value - 10000) * 100,
            set: fn(int $value) => $value / 100 + 10000,
        );
    }

    protected function northing() : Attribute {
        return Attribute::make(
            get: fn(int $value) => ($value - 60000) * 100,
            set: fn(int $value) => $value / 100 + 60000,
        );
    }

    protected function coordinates() : Attribute {
        return Attribute::make(
            get: fn() => self::getCoordinates($this->tiploc_code) ?? [$this->easting, $this->northing]
        );
    }
    
    protected function stationName() : Attribute {
        static $mapping;
        $mapping ??= json_decode(file_get_contents(__DIR__ . '/../../resource/long_station_names.json'), true);

        return Attribute::make(
            get: fn(string $value) => $mapping[$value] ?? $value,
            set: fn(string $value) => substr($value, 0, 26),
        );
    }
}
