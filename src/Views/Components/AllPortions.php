<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Views\Components;

use Miklcct\NationalRailTimetable\DomainModels\Service;
use Miklcct\NationalRailTimetable\ValueObjects\Date;
use Miklcct\NationalRailTimetable\Views\ViewMode;
use Miklcct\ThinPhpApp\View\PhpTemplate;
use Psr\Http\Message\StreamFactoryInterface;

class AllPortions extends PhpTemplate {
    public function __construct(
        StreamFactoryInterface $streamFactory
        , protected readonly Date $dateFromOrigin
        , protected readonly Service $service
        , protected readonly bool $permanentOnly
        , protected readonly ViewMode $fromViewMode
    ) {
        parent::__construct($streamFactory);
    }

    protected function getPathToTemplate() : string {
        return __DIR__ . '/../../../resource/templates/all_portions.phtml';
    }
}