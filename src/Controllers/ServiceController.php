<?php
declare(strict_types = 1);

namespace Miklcct\NationalRailTimetable\Controllers;

use DateTimeZone;
use LogicException;
use Miklcct\NationalRailTimetable\Exceptions\ServiceNotFound;
use Miklcct\NationalRailTimetable\Views\ServiceView;
use Miklcct\NationalRailTimetable\Views\ViewMode;
use Miklcct\RailOpenTimetableData\Models\Date;
use Miklcct\RailOpenTimetableData\Repositories\RepositoryInterface;
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
        , private readonly RepositoryInterface $repository
    ) {}
    
    public function run(ServerRequestInterface $request) : ResponseInterface {
        $query = $request->getQueryParams();
        $path_info = explode('/', trim($request->getServerParams()['PATH_INFO'] ?? '', '/'));
        $uid = null;
        if (\Safe\preg_match('/^[A-Za-z]\d{5}$/', $path_info[0])) {
            $uid = $path_info[0];
        }
        $rsid = null;
        if (\Safe\preg_match('/^[A-Za-z]{2}(\d{4}|\d{6})$/', $path_info[0])) {
            $rsid = $path_info[0];
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
        if ($uid === null && $rsid === null) {
            throw new LogicException('The UID or the RSID but must be specfified.');
        }
        $permanent_only = !empty($query['permanent_only']);
        $wtt = !empty($query['wtt']);
        $service = null;
        $service_repository = $this->repository->getServiceRepository($permanent_only);
        if ($uid !== null) {
            $service = $service_repository->getService($uid, $date);
        }
        if ($rsid !== null) {
            $service = $service_repository->getServiceByRsid($rsid, $date);
        }

        if ($service === null) {
            throw new ServiceNotFound($uid ?? $rsid, $date);
        }

        $response = ($this->viewResponseFactory)(
            new ServiceView(
                $this->streamFactory
                , $service
                , $permanent_only
                , $wtt
                , $this->repository->getGeneratedDate()
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
                    new SafeDateTimeImmutable('tomorrow')->setTimezone(new DateTimeZone('UTC'))->format('r')
                )
            );
    }
}