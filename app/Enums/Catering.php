<?php
declare(strict_types=1);

namespace App\Enums;

enum Catering : string {
    case BUFFET = 'C';
    case FIRST_CLASS_RESTAURANT = 'F';
    case HOT_FOOD = 'H';
    case FIRST_CLASS_MEAL = 'M';
    case WHEELCHAIR_ONLY = 'P';
    case RESTAURANT = 'R';
    case TROLLEY = 'T';

    public function getDescription() : string {
        return match($this) {
            self::BUFFET => 'Buffet',
            self::FIRST_CLASS_RESTAURANT => 'First Class Restaurant',
            self::HOT_FOOD => 'Hot Food',
            self::FIRST_CLASS_MEAL => 'First Class Meal',
            self::WHEELCHAIR_ONLY => 'Wheelchair Only',
            self::RESTAURANT => 'Restaurant',
            self::TROLLEY => 'Trolley',
        };
    }
}
