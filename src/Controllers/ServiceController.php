<?php
declare(strict_types = 1);

namespace Miklcct\NationalRailTimetable\Controllers;

use DateTimeZone;
use LogicException;
use Miklcct\NationalRailTimetable\DomainModels\Service;
use Miklcct\NationalRailTimetable\Exceptions\ServiceNotFound;
use Miklcct\NationalRailTimetable\ValueObjects\Date;
use Miklcct\NationalRailTimetable\Views\ServiceView;
use Miklcct\NationalRailTimetable\Views\ViewMode;
use Miklcct\ThinPhpApp\Controller\Application;
use Miklcct\ThinPhpApp\Response\ViewResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Safe\DateTimeImmutable as SafeDateTimeImmutable;

class ServiceController extends Application {
    public function __construct(
        private readonly ViewResponseFactoryInterface $viewResponseFactory
        , private readonly StreamFactoryInterface $streamFactory
    ) {}
    
    public function run(ServerRequestInterface $request) : ResponseInterface {
        $query = $request->getQueryParams();
        $path_info = explode('/', trim($request->getServerParams()['PATH_INFO'] ?? '', '/'));
        $id = null;
        if (\Safe\preg_match('/^[A-Za-z]\d{5}$/', $path_info[0])) {
            $id ??= $path_info[0];
        }
        if (\Safe\preg_match('/^[A-Za-z]{2}(\d{4}|\d{6})$/', $path_info[0])) {
            $id ??= $path_info[0];
        }
        if (isset($path_info[1])) {
            $date ??= $path_info[1];
        }
        if (!empty($date)) {
            sscanf($date, '%d-%d-%d', $year, $month, $day);
            $date = new Date($year, $month, $day);
        } else {
            $date = Date::today();
        }
        if ($id === null) {
            throw new LogicException('The UID or the RSID but must be specfified.');
        }
        $permanent_only = !empty($query['permanent_only']);
        $service = Service::loadFromDatabase($id, $date, $permanent_only);

        if ($service === null) {
            throw new ServiceNotFound($id, $date);
        }
        $response = ($this->viewResponseFactory)(
            new ServiceView(
                $this->streamFactory
                , $service
                , $permanent_only
                , $query['from'] ?? null === 'board' ? ViewMode::BOARD : ViewMode::TIMETABLE
            )
        )->withAddedHeader('Cache-Control', ['public', 'max-age=7200']);
        return !empty($query['date'])
            ? $response
            : $response->withAddedHeader(
                'Expires',
                str_replace(
                    '+0000',
                    'GMT',
                    (new SafeDateTimeImmutable('tomorrow'))->setTimezone(new DateTimeZone('UTC'))->format('r')
                )
            );
    }
}