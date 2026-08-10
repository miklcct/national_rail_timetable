<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Views;

use Miklcct\RailOpenTimetableData\DomainModels\Service;
use Miklcct\RailOpenTimetableData\Models\Date;
use Miklcct\RailOpenTimetableData\Models\Points\OriginOrIntermediatePoint;
use Miklcct\RailOpenTimetableData\Models\Points\OriginPoint;
use Miklcct\ThinPhpApp\View\PhpTemplate;
use Psr\Http\Message\StreamFactoryInterface;
use function http_build_query;

class ServiceView extends PhpTemplate {
    protected readonly Service $originPortion;
    
    public function __construct(
        StreamFactoryInterface $streamFactory
        , protected readonly Service $service
        , protected readonly bool $permanentOnly
        , protected readonly bool $wtt
        , protected readonly ?Date $generated
        , protected readonly ViewMode $fromViewMode
    ) {
        parent::__construct($streamFactory);

        $this->originPortion = array_first($this->service->getOriginPortions());
    }

    public static function getServiceUrl(
        string $uid
        , Date $date
        , bool $permanent_only
        , ViewMode $view_mode
        , bool $wtt = false
    ) : string {
        return rtrim(
            "/service/$uid/$date?" . http_build_query(
                ($view_mode === ViewMode::BOARD ? ['from' => 'board'] : [])
                + ($permanent_only ? ['permanent_only' => '1'] : [])
                + ($wtt ? ['wtt' => '1'] : [])
            )
            , '?'
        );
    }

    protected function getPathToTemplate() : string {
        return __DIR__ . '/../../resource/templates/service.phtml';
    }

    protected function getTitle() : string {
        $origin_point = array_first($this->originPortion->timingPoints);
        assert($origin_point instanceof OriginPoint);
        return sprintf(
            '%s %s %s %s to %s'
            , $this->originPortion->date
            , substr($origin_point->serviceProperty->rsid, 0, 6)
            , $origin_point->getPublicOrWorkingDeparture()
            , $origin_point->location->getShortName()
            , implode(' and ', array_map(static fn(Service $service) => array_last($service->timingPoints)->location->getShortName(), $this->service->getDestinationPortions()))
        );
    }
}