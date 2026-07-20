<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Views\Components;

use Miklcct\NationalRailTimetable\ValueObjects\Date;
use Miklcct\ThinPhpApp\View\PhpTemplate;
use Psr\Http\Message\StreamFactoryInterface;

class Footer extends PhpTemplate {
    public function __construct(StreamFactoryInterface $streamFactory) {
        parent::__construct($streamFactory);
    }

    protected function getPathToTemplate() : string {
        return __DIR__ . '/../../../resource/templates/footer.phtml';
    }
}