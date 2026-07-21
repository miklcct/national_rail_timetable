<?php
declare(strict_types = 1);

namespace Miklcct\NationalRailTimetable\Config;
use Miklcct\RailOpenTimetableData\Repositories\RepositoryInterface;

readonly abstract class Config {
    public function __construct(
        public string $siteName
    ) {
    }
    
    abstract public function getRepository() : RepositoryInterface;
    abstract public function getWriteRepository() : RepositoryInterface;
}