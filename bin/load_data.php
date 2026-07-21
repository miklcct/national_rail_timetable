#!/usr/bin/php
<?php
declare(strict_types=1);

use Miklcct\NationalRailTimetable\Config\Config;
use Miklcct\NationalRailTimetable\Config\MemoryConfig;
use Miklcct\RailOpenTimetableData\Repositories\MemoryRepository;
use Miklcct\RailOpenTimetableData\Repositories\MongodbRepository;
use Miklcct\RailOpenTimetableData\Repositories\RepositoryInterface;
use Psr\SimpleCache\CacheInterface;
use function Miklcct\RailOpenTimetableData\Parsers\load_timetable_directory;
use function Safe\getopt;

require __DIR__ . '/../initialise.php';
ini_set('memory_limit', '48G');
set_time_limit(0);

$options = getopt('', ['tocs'], $rest_index);
$argv = array_slice($argv, $rest_index);

$path = $argv[0];
$config = get_container()->get(Config::class);
/** @var RepositoryInterface $repository */
$repository = $config->getWriteRepository();
load_timetable_directory($repository, $path, $option['tocs'] ?? null);
if ($repository instanceof MongodbRepository) {
    $repository->addIndexes();
}
if ($config instanceof MemoryConfig && $repository instanceof MemoryRepository) {
    $repository->save($config->directory);
}

/** @var CacheInterface $cache */
$cache = get_container()->get(CacheInterface::class);
if (!$cache->clear()) {
    throw new RuntimeException("Can't clear cache. Please delete var/cache manually!");
}
