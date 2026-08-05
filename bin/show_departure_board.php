#!/usr/bin/php
<?php
declare(strict_types=1);

use Miklcct\NationalRailTimetable\Config\Config;
use Miklcct\RailOpenTimetableData\Models\Date;
use Miklcct\RailOpenTimetableData\Repositories\RepositoryInterface;
use Psr\SimpleCache\CacheInterface;
use Safe\DateTimeImmutable;
use function Miklcct\NationalRailTimetable\show_departure_board;
use function Safe\getopt;

require __DIR__ . '/../initialise.php';
ini_set('memory_limit', '32G');
set_time_limit(0);

$options = getopt('', ['arrivals'], $rest_index);
$argv = array_slice($argv, $rest_index);
if (count($argv) !== 2) {
    throw new RuntimeException('A date and CRS code must be specified to generate the board.');
}

$arrival_mode = isset($options['arrivals']);
$date = Date::fromDateTimeInterface(
    new DateTimeImmutable($argv[0])->sub(new DateInterval($arrival_mode ? 'PT4H30S' : 'P0D'))
);

/** @var RepositoryInterface $repository */
$repository = get_container()->get(Config::class)->getRepository();

show_departure_board($repository, $argv[1], $date, $arrival_mode, array_slice($argv, 2), [], get_container()->get(CacheInterface::class));