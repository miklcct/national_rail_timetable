<?php
declare(strict_types=1);

namespace App\DomainModels;

use App\ValueObjects\Date;

readonly class Period {

    public function __construct(
        public Date $from,
        public Date $to,
        /** @var bool[] 7 bits specifying if it is active on each of the weekdays */
        public array $weekdays
    ) {
    }

    public function isActive(Date $date) : bool {
        return $date->compare($this->from) >= 0
            && $date->compare($this->to) <= 0
            && $this->weekdays[$date->getWeekday()];
    }

    public function getWeekdayString() : string {
        $days = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
        return implode(
            '',
            array_map(fn($i) => $days[$i], array_keys(array_filter($this->weekdays, fn($v) => $v === true)))
        );
    }
}
