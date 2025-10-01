<?php
declare(strict_types=1);

namespace App\DomainModels\Points;

use App\ValueObjects\Time;

interface HasDeparture {
    public function getWorkingDeparture() : Time;
    public function getPublicDeparture() : ?Time;
    public function getPublicOrWorkingDeparture() : Time;
}
