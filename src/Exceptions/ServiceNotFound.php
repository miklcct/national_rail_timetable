<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Exceptions;

use Miklcct\NationalRailTimetable\ValueObjects\Date;
use Teapot\HttpException;
use Teapot\StatusCode\Http;

class ServiceNotFound extends HttpException {
    public function __construct(string $id, Date $date) {
        parent::__construct("Service $id cannot be found on $date.", Http::NOT_FOUND);
    }
}