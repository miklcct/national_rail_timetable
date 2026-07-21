#!/usr/bin/php
<?php
declare(strict_types=1);

use Miklcct\RailOpenTimetableData\Models\Date;
use Miklcct\RailOpenTimetableData\Repositories\MemoryRepository;
use Safe\DateTimeImmutable;
use function Miklcct\NationalRailTimetable\show_departure_board;
use function Miklcct\RailOpenTimetableData\Parsers\load_timetable_directory;
use function Safe\getopt;

require __DIR__ . '/../initialise.php';
ini_set('memory_limit', '32G');
set_time_limit(0);

$path = $argv[1];
$repository = new MemoryRepository();
load_timetable_directory($repository, $path);

$options = getopt('', ['arrivals'], $rest_index);
$argv = array_slice($argv, $rest_index);

$arrival_mode = isset($options['arrivals']);
echo "Data has been loaded. Enter a CRS code, optionally followed by filter stations, optionally followed by a date to show the departure board.\n";

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting.
        return;
    }

    if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
        // Do not throw an Exception for deprecation warnings as new or unexpected
        // deprecations would break the application.
        return;
    }

    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

while (($line = fgets(STDIN)) !== false) {
    $tokens = preg_split('/\s+/', trim($line));
    if (!isset($tokens[0])) {
        fwrite(STDERR, "CRS code is not specified\n");
        continue;
    }
    $last = array_last($tokens);
    $date = Date::today();
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $last)) {
        $date = Date::fromDateTimeInterface(new DateTimeImmutable($last));
        array_pop($tokens);
    }
    try {
        show_departure_board(
                $repository,
                $tokens[0],
                $date,
                $arrival_mode,
                array_slice($tokens, 1)
        );
    } catch (Throwable $e) {
        fwrite(STDERR, $e->__toString());
        fwrite(STDERR, "\n");
    }
}