<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\DomainModels\Points\IntermediatePoint;
use App\DomainModels\Points\OriginPoint;
use App\DomainModels\Points\PassingPoint;
use App\DomainModels\Points\TimingPoint;
use App\DomainModels\Service;
use App\DomainModels\ServiceProperty;
use App\Enums\Activity;
use App\Enums\Catering;
use App\Enums\TimeType;
use App\ValueObjects\Date;
use Exception;
use Illuminate\Console\Command;
use Safe\DateTimeImmutable;
use function App\Models\get_short_name;

class ShowService extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:show-service {uid} {date} {--exclude-stp} {--detailed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show the details of a service';

    /**
     * Execute the console command.
     */
    public function handle() {
        $uid = $this->argument('uid');
        $date = Date::fromDateTimeInterface(new DateTimeImmutable($this->argument('date')));
        $excludeStp = $this->option('exclude-stp');
        $detailed = $this->option('detailed');

        $service = Service::loadFromDatabase($uid, $date, $excludeStp);
        if ($service === null) {
            throw new Exception("Service $uid cannot be found on $date.");
        }

        $this->line(
            ($service->toc !== null ? "TOC $service->toc, " : "")
            . "UID $uid operating {$service->period->from} to {$service->period->to} {$service->period->getWeekdayString()}, {$service->shortTermPlanning->getDescription()}"
        );

        /** @var ServiceProperty[] $serviceProperties */
        $serviceProperties = [];
        /** @var TimingPoint[] $servicePropertyChangePoints */
        $servicePropertyChangePoints = [];
        /** @var ServiceProperty|null $currentServiceProperty */
        $currentServiceProperty = null;
        foreach ($service->timingPoints as $timingPoint) {
            if ($timingPoint instanceof OriginPoint || $timingPoint instanceof IntermediatePoint) {
                $serviceProperty = $timingPoint->serviceProperty;
                if ($currentServiceProperty !== null && $currentServiceProperty !== $serviceProperty) {
                    $serviceProperties[] = $currentServiceProperty;
                    $servicePropertyChangePoints[] = $timingPoint;
                }
                $currentServiceProperty = $serviceProperty;
            }
        }
        $serviceProperties[] = $currentServiceProperty;
        $servicePropertyChangePoints[] = $service->timingPoints[count($service->timingPoints) - 1];
        if ($serviceProperties !== []) {
            $this->table(
                $detailed
                    ? [
                    'From',
                    'To',
                    'Category',
                    'ID',
                    'Headcode',
                    'Power',
                    'Timing Load',
                    'Speed',
                    'DOO',
                    'Seating class',
                    'Sleeper class',
                    'Reservation',
                    'Catering',
                    'RSID',
                ]
                    : [
                    'From',
                    'To',
                    'Category',
                    'Seating class',
                    'Sleeper class',
                    'Reservation',
                    'Catering',
                    'RSID',
                ],
                array_map(fn(int $index, ServiceProperty $serviceProperty) => $detailed
                    ? [
                        ($index === 0
                            ? $service->timingPoints[0]
                            : $servicePropertyChangePoints[$index
                            - 1])->getTiplocOrStation()->getName(),
                        $servicePropertyChangePoints[$index]->getTiplocOrStation()->getName(),
                        $serviceProperty->trainCategory->getDescription(),
                        $serviceProperty->identity,
                        $serviceProperty->headcode,
                        $serviceProperty->power?->getDescription(),
                        $serviceProperty->timingLoad,
                        $serviceProperty->speedMph,
                        $serviceProperty->doo ? '✓' : '',
                        $serviceProperty->seatingClass?->getDescription(),
                        $serviceProperty->sleeperClass?->getDescription(),
                        $serviceProperty->reservation?->getDescription(),
                        implode(array_map(fn(Catering $x) => $x->getDescription(), $serviceProperty->catering)),
                        $serviceProperty->rsid,
                    ]
                    : [
                        ($index === 0
                            ? $service->timingPoints[0]
                            : $servicePropertyChangePoints[$index
                            - 1])->getStationOrTiploc()->getName(),
                        $servicePropertyChangePoints[$index]->getStationOrTiploc()->getName(),
                        $serviceProperty->trainCategory->getDescription(),
                        $serviceProperty->seatingClass?->getDescription(),
                        $serviceProperty->sleeperClass?->getDescription(),
                        $serviceProperty->reservation?->getDescription(),
                        implode(array_map(fn(Catering $x) => $x->getDescription(), $serviceProperty->catering)),
                        $serviceProperty->rsid,
                    ], array_keys($serviceProperties), $serviceProperties)
            );
            $this->newLine();
        }

        if ($detailed) {
            $this->table(
                ['Location', 'CRS', 'Pl', 'GBTT Arr', 'GBTT Dep', 'WTT Arr', 'WTT Dep', 'Path', 'Line', 'Activity'],
                array_map(
                    function ($timingPoint) {
                        $location = $timingPoint->getTiplocOrStation();
                        return [
                            $location?->getName(),
                            $location?->getCrsCode(),
                            $timingPoint->platform,
                            $timingPoint->getTime(TimeType::PUBLIC_ARRIVAL),
                            $timingPoint->getTime(TimeType::PUBLIC_DEPARTURE),
                            $timingPoint instanceof PassingPoint
                                ? 'pass'
                                : $timingPoint->getTime(
                                TimeType::WORKING_ARRIVAL
                            ),
                            $timingPoint instanceof PassingPoint
                                ? $timingPoint->getPass()
                                : $timingPoint->getTime(
                                TimeType::WORKING_DEPARTURE
                            ),
                            $timingPoint->path ?? null,
                            $timingPoint->line ?? null,
                            implode(array_map(fn(Activity $x) => $x->getDescription(), $timingPoint->activities)),
                        ];
                    },
                    $service->timingPoints
                ),
            );
        } else {
            $this->table(
                ['Station', 'CRS', 'Pl', 'Arr', 'Dep', 'Activity'],
                array_map(
                    function ($timingPoint) {
                        $location = $timingPoint->getStationOrTiploc();
                        return [
                            get_short_name($location?->getName()),
                            $location?->getCrsCode(),
                            $timingPoint->platform,
                            $timingPoint->getTime(TimeType::PUBLIC_ARRIVAL),
                            $timingPoint->getTime(TimeType::PUBLIC_DEPARTURE),
                            implode(array_map(fn(Activity $x) => $x->getDescription(), $timingPoint->activities)),
                        ];
                    },
                    array_filter($service->timingPoints, fn($timingPoint) => $timingPoint->isPublicCall())
                ),
            );
        }
    }
}
