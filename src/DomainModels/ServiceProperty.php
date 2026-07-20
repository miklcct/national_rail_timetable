<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\DomainModels;

use Miklcct\NationalRailTimetable\Enums\Catering;
use Miklcct\NationalRailTimetable\Enums\Power;
use Miklcct\NationalRailTimetable\Enums\Reservation;
use Miklcct\NationalRailTimetable\Enums\TrainCategory;
use Miklcct\NationalRailTimetable\Enums\TrainClass;

readonly class ServiceProperty {
    public function __construct(
        public ?TrainCategory $trainCategory,
        public ?string $identity,
        public ?string $headcode,
        public ?string $portionId,
        public ?Power $power,
        public ?string $timingLoad,
        public ?int $speedMph,
        public bool $doo,
        public ?TrainClass $seatingClass,
        public ?TrainClass $sleeperClass,
        public ?Reservation $reservation,
        /** @var Catering[] */
        public array $catering,
        public ?string $rsid
    ) {
    }
}
