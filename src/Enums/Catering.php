<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Enums;

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

    public function getIcon() : ?string {
        return match($this) {
            self::BUFFET => 'buffet.png',
            self::FIRST_CLASS_RESTAURANT => 'first_class_restaurant.png',
            self::HOT_FOOD => 'first_class_restaurant.png',
            self::FIRST_CLASS_MEAL => 'first_class_meal.png',
            self::WHEELCHAIR_ONLY => null,
            self::RESTAURANT => 'restaurant.png',
            self::TROLLEY => 'trolley.png',
        };
    }
}
