<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Enums;

enum Reservation : string {
    case NONE = '';
    case BICYCLE = 'E';
    case AVAILABLE = 'S';
    case RECOMMENDED = 'R';
    case COMPULSORY = 'A';

    public function getIcon() : ?string {
        return match($this) {
            self::AVAILABLE => 'reservation_available.png',
            self::RECOMMENDED => 'reservation_recommended.png',
            self::COMPULSORY => 'reservation_compulsory.png',
            default => null,
        };
    }

    public function getDescription() : string {
        return match($this) {
            self::NONE => 'No reservations',
            self::BICYCLE => 'Reservations for bicycle',
            self::AVAILABLE => 'Reservations available',
            self::RECOMMENDED => 'Reservations recommended',
            self::COMPULSORY => 'Reservations compulsory',
        };
    }
}
