<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Config;

use Miklcct\RailOpenTimetableData\Repositories\MemoryRepository;
use Miklcct\RailOpenTimetableData\Repositories\RepositoryInterface;

readonly class MemoryConfig extends Config {
    public function __construct(
        public string $directory
        , string $siteName
    ) {
        parent::__construct($siteName);
    }
    
    public function getRepository() : RepositoryInterface {
        return new MemoryRepository($this->directory);
    }
    
    public function getWriteRepository() : RepositoryInterface {
        return new MemoryRepository();
    }
}