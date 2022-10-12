<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_error_handler(
    function (int $severity, string $message, string $file, int $line) {
        if (!(error_reporting() & $severity)) {
            // This error code is not included in error_reporting
            return;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
);

use MongoDB\Client;
use Miklcct\NationalRailJourneyPlanner\Repositories\MongodbLocationRepository;
use Miklcct\NationalRailJourneyPlanner\Repositories\MongodbServiceRepository;
use Miklcct\NationalRailJourneyPlanner\Enums\TimeType;
use Miklcct\NationalRailJourneyPlanner\Models\Location;
use Miklcct\NationalRailJourneyPlanner\Models\ServiceCallWithDestination;
use function Miklcct\ThinPhpApp\Escaper\html;
use Miklcct\NationalRailJourneyPlanner\Models\Station;

require_once __DIR__ . '/../vendor/autoload.php';

function show_minutes(int $minutes) : string {
    return $minutes === 1 ? '1 minute' : "$minutes minutes";
}

set_time_limit(300);
ini_set('memory_limit', '4G');

$client = new Client(driverOptions: ['typeMap' => ['array' => 'array']]);
$database = $client->selectDatabase('national_rail');
$stations = new MongodbLocationRepository($database->selectCollection('locations'));
$timetable = new MongodbServiceRepository(
    $database->selectCollection('services')
    , $database->selectCollection('associations')
    , null
    , true
);

$station = $stations->getLocationByCrs($_GET['station']);
if ($station === null) {
    throw new InvalidArgumentException('Station can\'t be found!');
}
$destinations = isset($_GET['filter']) 
    ? array_map(
        $stations->getLocationByCrs(...)
        , (array)$_GET['filter']
    )
    : null;
if (is_array($destinations) && in_array(null, $destinations, true)) {
    throw new InvalidArgumentException('Destination station can\'t be found!');
}
$from = isset($_GET['from']) ? new DateTimeImmutable($_GET['from']) : new DateTimeImmutable();
$to = isset($_GET['to']) ? new DateTimeImmutable($_GET['to']) : $from->add(new DateInterval('P1D'));
$board = $timetable->getDepartureBoard($station->crsCode, $from, $to, TimeType::PUBLIC_DEPARTURE);
if (is_array($destinations)) {
    $board = $board->filter(array_map(fn(Location $station) => $station->crsCode, $destinations), TimeType::PUBLIC_ARRIVAL);
}

$date = null;

?>
<!DOCTYPE html>
<html>
    <head>
        <link rel="stylesheet" href="board.css" />
        <title><?= 
            html(
                sprintf(
                    'Departures from %s %s between %s and %s'
                    , $station->name 
                    , (is_array($destinations) 
                        ? ' to ' . implode(', ', array_map(fn(Location $station) => $station->name, $destinations))
                        : '')
                    , $from->format('Y-m-d H:i')
                    , $to->format('Y-m-d H:i')
                )
            )
        ?></title>
    </head>
    <body>
        <h1>Departures from <?= html($station->name) ?></h1>
        <p>
<?php
if (is_array($destinations)) {
?>
            Calling at <?= implode(' or ', array_map(fn(Location $station) => $station->name, $destinations)) ?>
<?php
}
?>
            between <?= html($from->format('Y-m-d H:i')) ?> and <?= html($to->format('Y-m-d H:i')) ?>
        </p>
<?php
if ($station instanceof Station) {
?>
        <p>Minimum connection time is <span class="time"><?= html(show_minutes($station->minimumConnectionTime)) ?></span><?= $station->tocConnectionTimes === [] ? '.' : ', with the exception of the following:' ?></p>
<?php
    if ($station->tocConnectionTimes !== []) {
?>
        <table>
            <thead>
                <tr><th>From</th><th>To</th><th>Time</th></tr>
            </thead>
            <tbody>
<?php
        foreach ($station->tocConnectionTimes as $entry) {
?>
                <tr>
                    <td><?= html($entry->arrivingToc) ?></td>
                    <td><?= html($entry->departingToc) ?></td>
                    <td class="time"><?= html(show_minutes($entry->connectionTime)) ?></td>
                </tr>
<?php
        }
?>
            </tbody>
        </table>
<?php
    }
}
?>
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Pl.</th>
                    <th>TOC</th>
                    <th>Train number</th>
                    <th>Destination</th>
                    <th>Calling at</th>
                </tr>
            </thead>
            <tbody>
<?php
foreach ($board->calls as $service_call) {
    $current_date = $service_call->timestamp->format('Y-m-d');
    if ($current_date !== $date) {
        $date = $current_date;
?>
                <tr>
                    <th colspan="6"><?= html($date) ?></th>
                </tr>
<?php
    }
    $portions_count = count($service_call->destinations);
    $portion_uids = array_keys($service_call->destinations);
?>
                <tr>
                    <td class="time <?= $service_call->isValidConnection($from, $_GET['connecting_toc'] ?? null) ? 'valid_connection' : 'invalid_connection' ?>" rowspan="<?= html($portions_count) ?>"><?= html($service_call->timestamp->format('H:i')) ?></td>
                    <td rowspan="<?= html($portions_count) ?>"><?= html($service_call->call->platform) ?></td>
                    <td rowspan="<?= html($portions_count) ?>"><?= html($service_call->toc) ?></td>
                    <td rowspan="<?= html($portions_count) ?>"><?= html(substr($service_call->serviceProperty->rsid, 0, 6)) ?></td>
<?php
    foreach ($portion_uids as $i => $uid) {
        if ($i > 0) {
?>
                </tr>
                <tr>
<?php
        }
?>
                    <td class="destination"><?= html($service_call->destinations[$uid]->location->name) ?></td>
                    <td><?=                        
                        implode(
                            ', '
                            , array_map(
                                static function(ServiceCallWithDestination $service_call) use ($destinations): string { 
                                    $station = $service_call->call->location;
                                    return sprintf(
                                        '<a href="%s" class="%s">%s (%s)</a>'
                                        , $_SERVER['PHP_SELF'] . '?' . http_build_query(
                                            [
                                                'station' => $station->crsCode,
                                                'from' => $service_call->timestamp->format('c'),
                                                'connecting_toc' => $service_call->toc,
                                            ]
                                        )
                                        , in_array($station->crsCode, array_map(fn(Location $location) => $location->crsCode, $destinations ?? []), true) ? 'destination' : ''
                                        , html($station->name)
                                        , html($service_call->timestamp->format('H:i'))
                                    );
                                }
                                , array_filter(
                                    $service_call->subsequentCalls
                                    , fn(ServiceCallWithDestination $service_call) : bool =>
                                        in_array($uid, array_keys($service_call->destinations), true)
                                )
                            )
                        )
                    ?></td>
<?php
    }
?>
                </tr>
<?php
}
?>
            </tbody>
        </table>
    </body>
</html>