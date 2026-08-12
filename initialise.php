<?php
declare(strict_types = 1);

use DI\ContainerBuilder;
use Http\Factory\Guzzle\ResponseFactory;
use Http\Factory\Guzzle\StreamFactory;
use Miklcct\NationalRailTimetable\Config\Config;
use Miklcct\RailOpenTimetableData\Models\Date;
use Miklcct\RailOpenTimetableData\Repositories\FixedLinkRepositoryInterface;
use Miklcct\RailOpenTimetableData\Repositories\LocationRepositoryInterface;
use Miklcct\RailOpenTimetableData\Repositories\MongodbFixedLinkRepository;
use Miklcct\RailOpenTimetableData\Repositories\MongodbLocationRepository;
use Miklcct\RailOpenTimetableData\Repositories\MongodbServiceRepositoryFactory;
use Miklcct\RailOpenTimetableData\Repositories\RepositoryInterface;
use Miklcct\RailOpenTimetableData\Repositories\ServiceRepositoryFactoryInterface;
use Miklcct\ThinPhpApp\Response\ViewResponseFactory;
use Miklcct\ThinPhpApp\Response\ViewResponseFactoryInterface;
use MongoDB\Client;
use MongoDB\Database;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Teapot\HttpException;
use Whoops\Handler\Handler;
use Whoops\Handler\PlainTextHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;
use function DI\autowire;
use function DI\get;

function get_container() : ContainerInterface {
    static $container;
    if ($container === null) {
        $container = new ContainerBuilder()->addDefinitions(
            [
                Client::class => static function(ContainerInterface $container) : Client {
                    $config = $container->get(Config::class);
                    return new Client(uri: $config->mongodbUri ?? 'mongodb://127.0.0.1/', uriOptions: $config->mongodbUriOptions ?? [], driverOptions: ['typeMap' => ['array' => 'array']]);
                },
                Config::class => static fn() : Config => require __DIR__ . '/config.php',
                CacheInterface::class => 
                    static fn() => new Psr16Cache(new PhpFilesAdapter('', 0, __DIR__ . '/var/cache', true)),
                RepositoryInterface::class =>
                    static fn(ContainerInterface $container) => $container->get(Config::class)->getRepository(),
                LocationRepositoryInterface::class => fn (RepositoryInterface $repository) => $repository->getLocationRepository(),
                FixedLinkRepositoryInterface::class => fn (RepositoryInterface $repository) => $repository->getFixedLinkRepository(),
                ViewResponseFactoryInterface::class => autowire(ViewResponseFactory::class),
                ResponseFactoryInterface::class => autowire(ResponseFactory::class),
                StreamFactoryInterface::class => autowire(StreamFactory::class),
            ]
        )
        ->build();
    }
    return $container;
}

function is_development() : bool {
    return $_SERVER['SERVER_NAME'] === 'gbtt.localhost';
}

require_once __DIR__ . '/vendor/autoload.php';

$whoops = new Run;
if (PHP_SAPI === 'cli') {
    $whoops->pushHandler(new PlainTextHandler());
} else {
    $pretty_page_handler = new PrettyPageHandler;
    $pretty_page_handler->setEditor(PrettyPageHandler::EDITOR_PHPSTORM);
    $whoops->pushHandler($pretty_page_handler);
    $whoops->pushHandler(
        new class extends Handler {
            public function handle() : void {
                $exception = $this->getException();
                if ($exception instanceof HttpException) {
                    $this->getRun()->sendHttpCode($exception->getCode());
                }
            }
        }
    );
}
$whoops->register();

set_time_limit(300);
ini_set('memory_limit', '4G');
date_default_timezone_set('Europe/London');
umask(0o002);
