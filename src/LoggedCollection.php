<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable;

use MongoDB\Collection;
use MongoDB\Driver\CursorInterface;
use MongoDB\Driver\Manager;
use Psr\Log\LoggerInterface;

class LoggedCollection extends Collection {
    public function __construct(
        Manager $manager,
        string $databaseName,
        string $collectionName,
        array $options = [],
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($manager, $databaseName, $collectionName, $options);
    }

    public function find(array|object $filter = [], array $options = []): CursorInterface {
        $start = microtime(true);
        try {
            return parent::find($filter, $options);
        } finally {
            $this->logger->info('MongoDB find', ['collection' => $this->getCollectionName(), 'filter' => $filter, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function findOne(array|object $filter = [], array $options = []): array|object|null {
        $start = microtime(true);
        try {
            return parent::findOne($filter, $options);
        } finally {
            $this->logger->info('MongoDB findOne', ['collection' => $this->getCollectionName(), 'filter' => $filter, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function insertOne(array|object $document, array $options = []): \MongoDB\InsertOneResult {
        $start = microtime(true);
        try {
            return parent::insertOne($document, $options);
        } finally {
            $this->logger->info('MongoDB insertOne', ['collection' => $this->getCollectionName(), 'document' => $document, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function insertMany(array $documents, array $options = []): \MongoDB\InsertManyResult {
        $start = microtime(true);
        try {
            return parent::insertMany($documents, $options);
        } finally {
            $this->logger->info('MongoDB insertMany', ['collection' => $this->getCollectionName(), 'documents' => $documents, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function updateOne(array|object $filter, array|object $update, array $options = []): \MongoDB\UpdateResult {
        $start = microtime(true);
        try {
            return parent::updateOne($filter, $update, $options);
        } finally {
            $this->logger->info('MongoDB updateOne', ['collection' => $this->getCollectionName(), 'filter' => $filter, 'update' => $update, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function updateMany(array|object $filter, array|object $update, array $options = []): \MongoDB\UpdateResult {
        $start = microtime(true);
        try {
            return parent::updateMany($filter, $update, $options);
        } finally {
            $this->logger->info('MongoDB updateMany', ['collection' => $this->getCollectionName(), 'filter' => $filter, 'update' => $update, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function deleteOne(array|object $filter, array $options = []): \MongoDB\DeleteResult {
        $start = microtime(true);
        try {
            return parent::deleteOne($filter, $options);
        } finally {
            $this->logger->info('MongoDB deleteOne', ['collection' => $this->getCollectionName(), 'filter' => $filter, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function deleteMany(array|object $filter, array $options = []): \MongoDB\DeleteResult {
        $start = microtime(true);
        try {
            return parent::deleteMany($filter, $options);
        } finally {
            $this->logger->info('MongoDB deleteMany', ['collection' => $this->getCollectionName(), 'filter' => $filter, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function findOneAndDelete(array|object $filter, array $options = []): array|object|null {
        $start = microtime(true);
        try {
            return parent::findOneAndDelete($filter, $options);
        } finally {
            $this->logger->info('MongoDB findOneAndDelete', ['collection' => $this->getCollectionName(), 'filter' => $filter, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function findOneAndReplace(array|object $filter, array|object $replacement, array $options = []): array|object|null {
        $start = microtime(true);
        try {
            return parent::findOneAndReplace($filter, $replacement, $options);
        } finally {
            $this->logger->info('MongoDB findOneAndReplace', ['collection' => $this->getCollectionName(), 'filter' => $filter, 'replacement' => $replacement, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function findOneAndUpdate(array|object $filter, array|object $update, array $options = []): array|object|null {
        $start = microtime(true);
        try {
            return parent::findOneAndUpdate($filter, $update, $options);
        } finally {
            $this->logger->info('MongoDB findOneAndUpdate', ['collection' => $this->getCollectionName(), 'filter' => $filter, 'update' => $update, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function listIndexes(array $options = []): \Iterator {
        $start = microtime(true);
        try {
            return parent::listIndexes($options);
        } finally {
            $this->logger->info('MongoDB listIndexes', ['collection' => $this->getCollectionName(), 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function rename(string $toCollectionName, ?string $toDatabaseName = null, array $options = []): void {
        $start = microtime(true);
        try {
            parent::rename($toCollectionName, $toDatabaseName, $options);
        } finally {
            $this->logger->info('MongoDB rename collection', ['from' => $this->getNamespace(), 'to' => ($toDatabaseName ?? $this->getDatabaseName()) . '.' . $toCollectionName, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function replaceOne(array|object $filter, array|object $replacement, array $options = []): \MongoDB\UpdateResult {
        $start = microtime(true);
        try {
            return parent::replaceOne($filter, $replacement, $options);
        } finally {
            $this->logger->info('MongoDB replaceOne', ['collection' => $this->getCollectionName(), 'filter' => $filter, 'replacement' => $replacement, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function aggregate(array|\MongoDB\Builder\Pipeline $pipeline, array $options = []): CursorInterface {
        $start = microtime(true);
        try {
            return parent::aggregate($pipeline, $options);
        } finally {
            $this->logger->info('MongoDB aggregate', ['collection' => $this->getCollectionName(), 'pipeline' => $pipeline, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function bulkWrite(array $operations, array $options = []): \MongoDB\BulkWriteResult {
        $start = microtime(true);
        try {
            return parent::bulkWrite($operations, $options);
        } finally {
            $this->logger->info('MongoDB bulkWrite', ['collection' => $this->getCollectionName(), 'operations' => $operations, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function count(array|object $filter = [], array $options = []): int {
        $start = microtime(true);
        try {
            return parent::count($filter, $options);
        } finally {
            $this->logger->info('MongoDB count', ['collection' => $this->getCollectionName(), 'filter' => $filter, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function countDocuments(array|object $filter = [], array $options = []): int {
        $start = microtime(true);
        try {
            return parent::countDocuments($filter, $options);
        } finally {
            $this->logger->info('MongoDB countDocuments', ['collection' => $this->getCollectionName(), 'filter' => $filter, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function createIndexes(array $indexes, array $options = []): array {
        $start = microtime(true);
        try {
            return parent::createIndexes($indexes, $options);
        } finally {
            $this->logger->info('MongoDB createIndexes', ['collection' => $this->getCollectionName(), 'indexes' => $indexes, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function dropIndexes(array $options = []): void {
        $start = microtime(true);
        try {
            parent::dropIndexes($options);
        } finally {
            $this->logger->info('MongoDB dropIndexes', ['collection' => $this->getCollectionName(), 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function estimatedDocumentCount(array $options = []): int {
        $start = microtime(true);
        try {
            return parent::estimatedDocumentCount($options);
        } finally {
            $this->logger->info('MongoDB estimatedDocumentCount', ['collection' => $this->getCollectionName(), 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function distinct(string $fieldName, array|object $filter = [], array $options = []): array {
        $start = microtime(true);
        try {
            return parent::distinct($fieldName, $filter, $options);
        } finally {
            $this->logger->info('MongoDB distinct', ['collection' => $this->getCollectionName(), 'fieldName' => $fieldName, 'filter' => $filter, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function drop(array $options = []): void {
        $start = microtime(true);
        try {
            parent::drop($options);
        } finally {
            $this->logger->info('MongoDB drop collection', ['collection' => $this->getCollectionName(), 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function watch(array|\MongoDB\Builder\Pipeline $pipeline = [], array $options = []): \MongoDB\ChangeStream {
        $start = microtime(true);
        try {
            return parent::watch($pipeline, $options);
        } finally {
            $this->logger->info('MongoDB watch collection', ['collection' => $this->getCollectionName(), 'pipeline' => $pipeline, 'options' => $options, 'time' => microtime(true) - $start]);
        }
    }

    public function withOptions(array $options = []): static {
        $options += [
            'codec' => $this->getCodec(),
            'readConcern' => $this->getReadConcern(),
            'readPreference' => $this->getReadPreference(),
            'typeMap' => $this->getTypeMap(),
            'writeConcern' => $this->getWriteConcern(),
        ];

        return new static(
            $this->getManager(),
            $this->getDatabaseName(),
            $this->getCollectionName(),
            $options,
            $this->logger
        );
    }
}
