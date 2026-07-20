<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Enums;

enum Mode : string {
    case TRAIN = '';
    case BUS = 'B';
    case SHIP = 'S';

    public function getIcon() : ?string {
        return match($this) {
            self::BUS => 'bus.png',
            self::SHIP => 'ship.png',
        };
    }

    public function getDescription() : string {
        return match($this) {
            self::TRAIN => 'Train',
            self::BUS => 'Bus',
            self::SHIP => 'Ferry',
        };
    }

}
