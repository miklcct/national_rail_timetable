<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable;

use MongoDB\Database;
use MongoDB\Driver\CursorInterface;
use MongoDB\Driver\Manager;
use Psr\Log\LoggerInterface;

class LoggedDatabase extends Database {
    public function __construct(
        Manager $manager,
        string $databaseName,
        array $options = [],
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($manager, $databaseName, $options);
    }

    public static function fromDatabase(Database $database, LoggerInterface $logger): static {
        return new static(
            $database->getManager(),
            $database->getDatabaseName(),
            [
                'readConcern' => $database->getReadConcern(),
                'readPreference' => $database->getReadPreference(),
                'typeMap' => $database->getTypeMap(),
                'writeConcern' => $database->getWriteConcern(),
            ],
            $logger
        );
    }

    public function selectCollection(string $collectionName, array $options = []): LoggedCollection {
        $options += [
            'readConcern' => $this->getReadConcern(),
            'readPreference' => $this->getReadPreference(),
            'typeMap' => $this->getTypeMap(),
            'writeConcern' => $this->getWriteConcern(),
        ];

        return new LoggedCollection(
            $this->getManager(),
            $this->getDatabaseName(),
            $collectionName,
            $options,
            $this->logger
        );
    }

    public function getCollection(string $collectionName, array $options = []): LoggedCollection {
        return $this->selectCollection($collectionName, $options);
    }

    public function __get(string $collectionName): LoggedCollection {
        return $this->selectCollection($collectionName);
    }

    public function command(array|object $command, array $options = []): CursorInterface {
        $start = microtime(true);
        try {
            return parent::command($command, $options);
        } finally {
            $this->logger->info('MongoDB command', ['database' => $this->getDatabaseName(), 'command' => $command, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function aggregate(array|\MongoDB\Builder\Pipeline $pipeline, array $options = []): CursorInterface {
        $start = microtime(true);
        try {
            return parent::aggregate($pipeline, $options);
        } finally {
            $this->logger->info('MongoDB database aggregate', ['database' => $this->getDatabaseName(), 'pipeline' => $pipeline, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function createCollection(string $collectionName, array $options = []): void {
        $start = microtime(true);
        try {
            parent::createCollection($collectionName, $options);
        } finally {
            $this->logger->info('MongoDB create collection', ['database' => $this->getDatabaseName(), 'collection' => $collectionName, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function drop(array $options = []): void {
        $start = microtime(true);
        try {
            parent::drop($options);
        } finally {
            $this->logger->info('MongoDB drop database', ['database' => $this->getDatabaseName(), 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function listCollections(array $options = []): \Iterator {
        $start = microtime(true);
        try {
            return parent::listCollections($options);
        } finally {
            $this->logger->info('MongoDB listCollections', ['database' => $this->getDatabaseName(), 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function modifyCollection(string $collectionName, array $collectionOptions, array $options = []): array|object {
        $start = microtime(true);
        try {
            return parent::modifyCollection($collectionName, $collectionOptions, $options);
        } finally {
            $this->logger->info('MongoDB modifyCollection', ['database' => $this->getDatabaseName(), 'collection' => $collectionName, 'collectionOptions' => $collectionOptions, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function renameCollection(string $fromCollectionName, string $toCollectionName, ?string $toDatabaseName = null, array $options = []): void {
        $start = microtime(true);
        try {
            parent::renameCollection($fromCollectionName, $toCollectionName, $toDatabaseName, $options);
        } finally {
            $this->logger->info('MongoDB renameCollection', ['database' => $this->getDatabaseName(), 'from' => $fromCollectionName, 'to' => $toCollectionName, 'toDatabase' => $toDatabaseName, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function watch(array|\MongoDB\Builder\Pipeline $pipeline = [], array $options = []): \MongoDB\ChangeStream {
        $start = microtime(true);
        try {
            return parent::watch($pipeline, $options);
        } finally {
            $this->logger->info('MongoDB watch database', ['database' => $this->getDatabaseName(), 'pipeline' => $pipeline, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function withOptions(array $options = []): static {
        $options += [
            'readConcern' => $this->getReadConcern(),
            'readPreference' => $this->getReadPreference(),
            'typeMap' => $this->getTypeMap(),
            'writeConcern' => $this->getWriteConcern(),
        ];

        return new static($this->getManager(), $this->getDatabaseName(), $options, $this->logger);
    }
}