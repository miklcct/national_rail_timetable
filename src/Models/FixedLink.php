<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Models;

use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Miklcct\NationalRailTimetable\ValueObjects\Date;
use Miklcct\NationalRailTimetable\ValueObjects\Time;

class FixedLink extends Model {
    protected $table = 'bidirectional_fixed_link';
    public $timestamps = false;
    public $incrementing = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts() : array {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'start_time' => Time::class,
            'end_time' => Time::class,
            'monday' => 'bool',
            'tuesday' => 'bool',
            'wednesday' => 'bool',
            'thursday' => 'bool',
            'friday' => 'bool',
            'saturday' => 'bool',
            'sunday' => 'bool',
        ];
    }

    public function originStation() : BelongsTo {
        return $this->belongsTo(PhysicalStation::class, 'origin', 'crs_code')->primary();
    }
    
    public function destinationStation() : BelongsTo {
        return $this->belongsTo(PhysicalStation::class, 'destination', 'crs_code')->primary();
    }

    /**
     * Get the fixed link arrival time given a departure time
     *
     * @param DateTimeImmutable $departure
     * @param bool $reverse If true get the departure time from the arrival time instead
     * @return DateTimeImmutable|null
     */
    public function getArrivalTime(DateTimeImmutable $departure, bool $reverse = false) : ?DateTimeImmutable {
        $transfer_interval = new DateInterval(sprintf('PT%dM', $this->duration));
        if ($reverse) {
            $departure = $departure->sub($transfer_interval);
        }

        $time = Time::fromDateTimeInterface($departure);
        $date_valid = $this->isActiveOnDate(Date::fromDateTimeInterface($departure));
        $time_valid = $this->isActiveAtTime($time);
        if ($date_valid && $time_valid) {
            return $reverse ? $departure : $departure->add($transfer_interval);
        }
        if ($reverse) {
            if ($date_valid && $time->secondsFromOrigin > $this->start_time->secondsFromOrigin) {
                $next_time = $departure->setTime($this->end_time->hours, $this->end_time->minutes);
            } elseif ($this->start_date !== null && $departure < $this->start_date->toDateTimeImmutable()) {
                $next_time = null;
            } else {
                $next_time = $departure->sub(new DateInterval('P1D'))->setTime(23, 59);
            }
            if ($next_time !== null && $departure->getTimestamp() - $next_time->getTimestamp() < 60 * 60 * 6) {
                return $this->getArrivalTime($next_time->add($transfer_interval), true);
            }
            return null;
        }

        if ($date_valid && $time->secondsFromOrigin < $this->start_time->secondsFromOrigin) {
            $next_time = $departure->setTime($this->start_time->hours, $this->start_time->minutes);
        } elseif ($this->end_date !== null && $departure > $this->end_date->toDateTimeImmutable(new Time(23, 59, 59))) {
            $next_time = null;
        } else {
            $next_time = $departure->add(new DateInterval('P1D'))->setTime(0, 0);
        }
        if ($next_time !== null && $next_time->getTimestamp() - $departure->getTimestamp() < 60 * 60 * 6) {
            return $this->getArrivalTime($next_time);
        }
        return null;
    }
    
    public function weekdays() : Attribute {
        return Attribute::make(
            get: fn() => [
                $this->sunday,
                $this->monday,
                $this->tuesday,
                $this->wednesday,
                $this->thursday,
                $this->friday,
                $this->saturday,
            ]
        );
    }

    public function isActiveOnDate(Date $date) : bool {
        return $this->weekdays[$date->toDateTimeImmutable()->format('w')]
            && (!($this->start_date !== null)
                || $this->start_date->toDateTimeImmutable(new Time(0, 0))
                <= $date->toDateTimeImmutable())
            && (!($this->end_date !== null)
                || $this->end_date->toDateTimeImmutable(new Time(23, 59, 59))
                >= $date->toDateTimeImmutable());
    }

    public function isActiveAtTime(Time $time) : bool {
        return $this->start_time->secondsFromOrigin <= $time->secondsFromOrigin
            && $this->end_time->secondsFromOrigin >= $time->secondsFromOrigin;
    }
}