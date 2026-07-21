<?php
declare(strict_types = 1);

namespace Miklcct\NationalRailTimetable\Config;
use Miklcct\NationalRailTimetable\LoggedDatabase;
use Miklcct\RailOpenTimetableData\Models\Date;
use Miklcct\RailOpenTimetableData\Repositories\MongodbRepository;
use Miklcct\RailOpenTimetableData\Repositories\RepositoryInterface;
use MongoDB\Client;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

readonly class MongodbConfig extends Config {
    public function __construct(
        public ?string $mongodbUri
        , public ?array $mongodbUriOptions
        , public string $databaseName
        , public string $alternativeDatabaseName
        , string $siteName
        , private ?LoggerInterface $logger = null
    ) {
        parent::__construct($siteName);
    }

    public function getRepository() : RepositoryInterface {
        return new MongodbRepository($this->getDatabases()[0]);
    }

    public function getWriteRepository() : RepositoryInterface {
        return new MongodbRepository($this->getDatabases()[1]);
    }

    public function getDatabases() : array {
        $container = get_container();
        $databases = array_map(
            function (string $name) use ($container) {
                $database = $container->get(Client::class)->selectDatabase($name);
                return $this->logger ? LoggedDatabase::fromDatabase($database, $this->logger) : $database;
            }
            , [$this->databaseName, $this->alternativeDatabaseName]
        );
        /** @var (Date|null)[] $generated_dates */
        $generated_dates = array_map(
            static fn (Database $database) => new MongodbRepository($database, null)->getGeneratedDate()
            , $databases
        );
        if ($generated_dates[0]?->toDateTimeImmutable() < $generated_dates[1]?->toDateTimeImmutable()) {
            return [$databases[1], $databases[0]];
        }
        return $databases;
    }
}