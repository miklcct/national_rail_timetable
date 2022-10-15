<?php
declare(strict_types=1);

use GuzzleHttp\Psr7\ServerRequest;
use Miklcct\NationalRailTimetable\Controllers\BoardController;
use function Http\Response\send;
use function Miklcct\NationalRailTimetable\get_container;

require_once __DIR__ . '/../initialise.php';

send(get_container()->get(BoardController::class)->run(ServerRequest::fromGlobals()));