<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\DomainModels\Points;

use Miklcct\NationalRailTimetable\ValueObjects\Time;

interface HasDeparture {
    public function getWorkingDeparture() : Time;
    public function getPublicDeparture() : ?Time;
    public function getPublicOrWorkingDeparture() : Time;
}
