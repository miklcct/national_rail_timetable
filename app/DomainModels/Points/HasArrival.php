<?php
declare(strict_types=1);

namespace App\DomainModels\Points;

use App\DomainModels\Time;

interface HasArrival {
    public function getWorkingArrival() : Time;
    public function getPublicArrival() : ?Time;
    public function getPublicOrWorkingArrival() : Time;
}
