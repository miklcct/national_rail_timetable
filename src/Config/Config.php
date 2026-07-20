<?php
declare(strict_types = 1);

namespace Miklcct\NationalRailTimetable\Config;
readonly class Config {
    public function __construct(
        public ?string $mysqlHost
        , public string $mysqlUsername
        , public string $mysqlPassword
        , public string $databaseName
        , public string $alternativeDatabaseName
        , public string $siteName
    ) {
        
    }
}