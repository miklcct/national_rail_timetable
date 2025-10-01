<?php
declare(strict_types=1);

namespace App\DomainModels\Points;

use App\DomainModels\Time;
use App\Enums\Activity;

trait DepartureTrait {
    public readonly Time $workingDeparture;
    public readonly ?Time $publicDeparture;

    public function getWorkingDeparture() : Time {
        return $this->workingDeparture;
    }

    public function getPublicDeparture() : ?Time {
        return in_array(Activity::UNADVERTISED, $this->activities, true) ? null : $this->publicDeparture;
    }

    public function getPublicOrWorkingDeparture() : Time {
        return $this->getPublicDeparture() ?? $this->getWorkingDeparture();
    }

}
