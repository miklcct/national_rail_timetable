<?php
declare(strict_types=1);

namespace App\DomainModels;

use App\DomainModels\Points\TimingPoint;
use App\Enums\BankHoliday;
use App\Enums\Mode;
use App\Enums\ShortTermPlanning;

readonly class Service {
    public function __construct(
        public string $uid,
        public Period $period,
        public BankHoliday $excludeBankHoliday,
        public Mode $mode,
        public string $toc,
        /** @var TimingPoint[] */
        public array $timingPoints,
        public ShortTermPlanning $shortTermPlanning
    ) {
    }
}
