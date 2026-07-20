<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Exceptions;

use Teapot\HttpException;
use Teapot\StatusCode\WebDAV;

class AmbiguousStation extends HttpException {
    public function __construct(string $name, array $alternatives) {
        parent::__construct(sprintf("Station $name is ambiguous (%s).", implode(', ', $alternatives)), WebDAV::UNPROCESSABLE_ENTITY);
    }
}