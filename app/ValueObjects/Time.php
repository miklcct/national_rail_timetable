<?php
declare(strict_types=1);

namespace App\ValueObjects;

use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

final readonly class Time implements Castable {
    public const SECONDS_PER_MINUTE = 60;
    public const MINUTES_PER_HOUR = 60;
    public const HOURS_PER_DAY = 24;
    public const SECONDS_PER_DAY = self::SECONDS_PER_MINUTE * self::MINUTES_PER_HOUR * self::HOURS_PER_DAY;

    public const TWENTY_FOUR_HOUR_CLOCK = 0;
    public const THIRTY_HOUR_CLOCK = 1;
    public const SHOW_PLUS_DAYS = 2;

    public int $secondsFromOrigin;
    public bool $negative;
    public int $hours;
    public int $minutes;
    public int $seconds;

    public function __construct(
        int $hours,
        int $minutes,
        int $seconds = 0,
        bool $negative = false,
    ) {
        $this->secondsFromOrigin =
            (($hours * self::MINUTES_PER_HOUR + $minutes) * self::SECONDS_PER_MINUTE + $seconds)
            * ($negative ? -1 : 1);
        $this->negative = $this->secondsFromOrigin < 0;
        $this->hours = intdiv(abs($this->secondsFromOrigin), self::SECONDS_PER_MINUTE * self::MINUTES_PER_HOUR);
        $this->minutes = intdiv(abs($this->secondsFromOrigin), self::SECONDS_PER_MINUTE) % self::MINUTES_PER_HOUR;
        $this->seconds = abs($this->secondsFromOrigin) % self::SECONDS_PER_MINUTE;
    }

    public static function fromString(string $time): self {
        if ($time[0] === '-') {
            return new self(0, 0, self::fromString(substr($time, 1))->secondsFromOrigin, true);
        }
        $components = explode(':', $time);
        return new self(...$components);
    }

    public function moduloDay(): self {
        $seconds = $this->secondsFromOrigin % self::SECONDS_PER_DAY;
        while ($seconds < 0) {
            $seconds += self::SECONDS_PER_DAY;
        }
        return new self(0, 0, $seconds);
    }

    public static function fromDateTimeInterface(DateTimeInterface $datetime) : static {
        return new static(
            hours: (int)$datetime->format('G')
            , minutes: (int)$datetime->format('i')
            , halfMinute: (int)$datetime->format('s') >= 30
        );
    }

    public function addDay() : static {
        return new static(
            $this->hours + 24
            , $this->minutes
            , $this->seconds
        );
    }

    public function toString(int $format = self::TWENTY_FOUR_HOUR_CLOCK) : string {
        return sprintf(
                "%02d:%02d"
                ,
                $format === self::THIRTY_HOUR_CLOCK
                    ? $this->hours
                    : $this->hours % 24
                ,
                $this->minutes
            )
            . ($this->seconds >= 30 ? '½' : '')
            . ($format === self::SHOW_PLUS_DAYS && $this->hours >= 24
                ? '+' . intdiv($this->hours, 24)
                : '');
    }

    public function __toString() : string {
        return $this->toString();
    }


    public static function castUsing(array $arguments) : CastsAttributes {
        return new class implements CastsAttributes {
            public function get(Model $model, string $key, mixed $value, array $attributes) : Time {
                return Time::fromString($value);
            }
            public function set(Model $model, string $key, mixed $value, array $attributes) : string {
                return $model->__toString();
            }
        };
    }
}
