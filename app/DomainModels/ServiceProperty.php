<?php
declare(strict_types=1);

namespace App\DomainModels;

use App\Enums\Catering;
use App\Enums\Power;
use App\Enums\Reservation;
use App\Enums\TrainCategory;
use App\Enums\TrainClass;

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
