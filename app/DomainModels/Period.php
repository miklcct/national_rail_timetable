<?php
declare(strict_types=1);

namespace App\DomainModels;

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
}
