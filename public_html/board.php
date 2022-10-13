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

use Miklcct\NationalRailJourneyPlanner\Enums\Activity;
use Miklcct\NationalRailJourneyPlanner\Enums\Mode;
use MongoDB\Client;
use Miklcct\NationalRailJourneyPlanner\Repositories\MongodbLocationRepository;
use Miklcct\NationalRailJourneyPlanner\Repositories\MongodbServiceRepository;
use Miklcct\NationalRailJourneyPlanner\Enums\TimeType;
use Miklcct\NationalRailJourneyPlanner\Models\ServiceCallWithDestination;
use function Miklcct\ThinPhpApp\Escaper\html;
use Miklcct\NationalRailJourneyPlanner\Models\Station;
use Miklcct\NationalRailJourneyPlanner\Repositories\MongodbFixedLinkRepository;
use Miklcct\NationalRailJourneyPlanner\Models\FixedLink;

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
    , !empty($_GET['permanent_only'])
);
$fixed_links = new MongodbFixedLinkRepository($database->selectCollection('fixed_links'));

$station = null;
$destination = null;

if (!empty($_GET['station'])) {
    $station = $stations->getLocationByCrs(strtoupper($_GET['station'])) ?? $stations->getLocationByName(strtoupper($_GET['station']));
    if ($station === null) {
        throw new InvalidArgumentException('Station can\'t be found!');
    }

    $time_zone = new DateTimeZone('Europe/London');
    $from = isset($_GET['from']) ? new DateTimeImmutable($_GET['from'], $time_zone) : new DateTimeImmutable('now');
    $from = $from->setTime((int)$from->format('H'), (int)$from->format('i'), 0);
    $to = $from->add(new DateInterval('P1DT4H30M'));
    $board = $timetable->getDepartureBoard($station->crsCode, $from, $to, TimeType::PUBLIC_DEPARTURE);
    $connecting_time = !empty($_GET['connecting_time']) ? new DateTimeImmutable($_GET['connecting_time'], $time_zone) : null;
    if (!empty($_GET['filter'])) {
        $input = strtoupper($_GET['filter']);
        $destination = $stations->getLocationByCrs($input) ?? $stations->getLocationByName($input);
        if ($destination === null) {
            throw new InvalidArgumentException('Destination station can\'t be found!');
        }
    }
    if ($destination !== null) {
        $board = $board->filterByDestination($destination->crsCode);
    }
    /** @var FixedLink[] */
    $station_links = [];
    $fixed_link_departure_time = isset($connecting_time) && $station instanceof Station ? $connecting_time->add(new DateInterval(sprintf('PT%dM', $station->minimumConnectionTime))) : $from;
    foreach ($fixed_links->get($station->crsCode, null) as $fixed_link) {
        $arrival_time = $fixed_link->getArrivalTime($fixed_link_departure_time);
        $existing = $station_links[$fixed_link->destination->crsCode] ?? null;
        if (
            ($destination === null || $destination->crsCode === $fixed_link->destination->crsCode)
            && (
                !$existing 
                || $arrival_time < $existing->getArrivalTime($fixed_link_departure_time) 
                || $arrival_time == $existing->getArrivalTime($fixed_link_departure_time) && $fixed_link->priority > $existing->priority
            )
        ) {
            $station_links[$fixed_link->destination->crsCode] = $fixed_link;
        }
    }
}

$date = null;

?>
<!DOCTYPE html>
<html>
    <head>
        <link rel="stylesheet" href="board.css" />
        <title><?= 
            html(
                $station === null ? 'Departure board' : sprintf(
                    'Departures at %s %s %s'
                    , $station->name 
                    , $destination !== null 
                        ? ' to ' . $destination->name
                        : ''
                    , empty($_GET['from']) ? 'now' : 'from ' . $from->format('Y-m-d H:i')
                )
            )
        ?></title>
    </head>
    <body>
    <form action="<?= html($_SERVER['PHP_SELF']) ?>" method="GET">
            <datalist id="stations">
<?php
foreach ($stations->getAllStationNames() as $name) {
?>
                <option value="<?= html($name) ?>"></option>
<?php
}
?>
            </datalist>
            <p>
                <label>Show departures at: <input autocomplete="off" list="stations" required="required" type="text" name="station" size="32" value="<?= html($station?->name)?>"/></label><br/>
                <label>only trains calling at (optional): <input autocomplete="off" list="stations" type="text" name="filter" size="32" value="<?= html($destination?->name) ?>"/></label><br/>
                <label>from (leave empty for now) <input type="datetime-local" name="from" value="<?= html(empty($_GET['from']) ? '' : substr($from->format('c'), 0, 16)) ?>"/></label>
            </p>
            <p>
                <label>Show valid connections at: <input type="datetime-local" name="connecting_time" value="<?= html($connecting_time !== null ? substr($connecting_time->format('c'), 0, 16) : '') ?>"/>
                from TOC: <input type="text" name="connecting_toc" size="8" value="<?= html($_GET['connecting_toc'] ?? '') ?>"/></label></br>
            </p>
            <p>
                <label>Show permanent timetable instead of actual timetable: <input type="checkbox" name="permanent_only" <?= !empty($_GET['permanent_only']) ? 'checked="checked"' : '' ?>/></label><br/>
            </p>
            <p>
                <input type="submit" />
            </p>
        </form>
<?php
if ($station !== null) {
?>  
        <h1>
            Departures at <?= html($station->name . (isset($station->crsCode) ? " ($station->crsCode)" : '')) ?>
<?php
    if ($destination !== null) {
?>
            calling at <?= html($destination->name . (isset($destination->crsCode) ? " ($destination->crsCode)" : '')) ?>
<?php
    }
    if (!empty($_GET['from'])) {
?>
            from <?= html($from->format('Y-m-d H:i')) ?>
<?php
    } else {
?>
            now
<?php
    }
?>
        </h1>
<?php
    if ($station instanceof Station) {
?>
        <p>Minimum connection time is <span class="time"><?= html(show_minutes($station->minimumConnectionTime)) ?></span><?= $station->tocConnectionTimes === [] ? '.' : ', with the exception of the following:' ?></p>
<?php
        if ($station->tocConnectionTimes !== []) {
?>
        <table>
            <thead>
                <tr><th>From</th><th>To</th><th>Minutes</th></tr>
            </thead>
            <tbody>
<?php
            foreach ($station->tocConnectionTimes as $entry) {
?>
                <tr>
                    <td><?= html($entry->arrivingToc) ?></td>
                    <td><?= html($entry->departingToc) ?></td>
                    <td><?= html($entry->connectionTime) ?></td>
                </tr>
<?php
            }
?>
            </tbody>
        </table>
<?php
        }

        if (!empty($station_links)) {
?>
        <table>
            <thead>
                <tr><th colspan="6">Fixed links as of <?= html($fixed_link_departure_time->format('Y-m-d H:i')) ?></th></tr>
                <tr><th>Transfer to</th><th>Mode</th><th>From</th><th>To</th><th>Minutes</th><th>Arrival</th></tr>
            </thead>
            <tbody>
<?php
            foreach ($station_links as $link) {
                $arrival_time = $link->getArrivalTime($fixed_link_departure_time);
?>
                <tr>
                    <td class="destination">
                        <a href="<?= html(
                            $_SERVER['PHP_SELF'] . '?' . http_build_query(
                                [
                                    'station' => $link->destination->name,
                                    'from' => ($connecting_time ?? $from)->format('c'),
                                    'connecting_time' => $arrival_time->format('c'),
                                    'permanent_only' => $_GET['permanent_only'] ?? ''
                                ]
                            )
                        ) ?>">
                            <?= html($link->destination->name) ?>
                        </a>
                    </td>
                    <td><?= html($link->mode) ?></td>
                    <td><?= html($link->startTime) ?></td>
                    <td><?= html($link->endTime) ?></td>
                    <td><?= html($link->transferTime) ?></td>
                    <td class="time"><?= html($arrival_time->format('H:i')) ?></td>
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
                    <th>Mode</th>
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
                    <th colspan="7"><?= html($date) ?></th>
                </tr>
<?php
        }
        $portions_count = count($service_call->destinations);
        $portion_uids = array_keys($service_call->destinations);
        $overtaken_portions = array_combine(
            $portion_uids
            , array_map(
                fn(string $uid) : bool => $destination !== null ? $board->isOvertaken($service_call, $destination->crsCode, $uid) : false
                , $portion_uids
            )
        );
?>
                <tr class="<?= !in_array(false, $overtaken_portions, true) ? 'overtaken' : '' ?>">
                    <td class="time <?= $connecting_time === null || !$station instanceof Station ? '' : ($service_call->isValidConnection($connecting_time, $_GET['connecting_toc'] ?? null) ? 'valid_connection' : 'invalid_connection') ?>" rowspan="<?= html($portions_count) ?>">
                        <?= html($service_call->timestamp->format('H:i') . (in_array(Activity::REQUEST_STOP, $service_call->call->activities) ? 'x' : '')) ?>
                    </td>
                    <td rowspan="<?= html($portions_count) ?>"><?= match ($service_call->mode) {
                        Mode::BUS => 'BUS',
                        Mode::SHIP => 'SHIP',
                        default => '',
                    } ?></td>
                    <td rowspan="<?= html($portions_count) ?>"><?= html($service_call->call->platform) ?></td>
                    <td rowspan="<?= html($portions_count) ?>"><?= html($service_call->toc) ?></td>
                    <td rowspan="<?= html($portions_count) ?>"><?= html(substr($service_call->serviceProperty->rsid, 0, 6)) ?></td>
<?php
        foreach ($portion_uids as $i => $uid) {
            if ($i > 0) {
?>
                </tr>
                <tr class="<?= !in_array(false, $overtaken_portions, true) ? 'overtaken' : '' ?>">
<?php
            }
?>
                    <td class="destination <?= $overtaken_portions[$uid] ? 'overtaken' : '' ?>"><?= html($service_call->destinations[$uid]->location->name) ?></td>
                    <td class="<?= $overtaken_portions[$uid] ? 'overtaken' : '' ?>"><?=                        
                        implode(
                            ', '
                            , array_map(
                                static function(ServiceCallWithDestination $service_call) use ($destination): string { 
                                    $station = $service_call->call->location;
                                    return $station->crsCode === null
                                        ? sprintf(
                                            '%s (%s%s)'
                                            , html($station->name)
                                            , html($service_call->timestamp->format('H:i'))
                                            , in_array(Activity::REQUEST_STOP, $service_call->call->activities) ? 'x' : ''
                                        )
                                        : sprintf(
                                            '<a href="%s" class="%s">%s (%s%s)</a>'
                                            , $_SERVER['PHP_SELF'] . '?' . http_build_query(
                                                [
                                                    'station' => $station->name,
                                                    'from' => $service_call->timestamp->format('c'),
                                                    'connecting_time' => $service_call->timestamp->format('c'),
                                                    'connecting_toc' => $service_call->toc,
                                                    'permanent_only' => $_GET['permanent_only'] ?? ''
                                                ]
                                            )
                                            , $station->crsCode === $destination?->crsCode ? 'destination' : ''
                                            , html($station->name)
                                            , html($service_call->timestamp->format('H:i'))
                                            , in_array(Activity::REQUEST_STOP, $service_call->call->activities) ? 'x' : ''
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
<?php
}
?>
    </body>
</html>