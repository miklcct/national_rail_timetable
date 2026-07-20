<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\DomainModels\Points;

use Miklcct\NationalRailTimetable\ValueObjects\Time;

interface HasArrival {
    public function getWorkingArrival() : Time;
    public function getPublicArrival() : ?Time;
    public function getPublicOrWorkingArrival() : Time;
}
