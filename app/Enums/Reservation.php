<?php
declare(strict_types=1);

namespace App\Enums;

enum Reservation : string {
    case NONE = '';
    case BICYCLE = 'E';
    case AVAILABLE = 'S';
    case RECOMMENDED = 'R';
    case COMPULSORY = 'A';
    public function getDescription() {
        return match($this) {
            self::NONE => 'None',
            self::BICYCLE => 'Bicycle',
            self::AVAILABLE => 'Available',
            self::RECOMMENDED => 'Recommended',
            self::COMPULSORY => 'Compulsory',
        };
    }
}
