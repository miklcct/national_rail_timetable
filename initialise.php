<?php
declare(strict_types = 1);

use DI\ContainerBuilder;
use Http\Factory\Guzzle\ResponseFactory;
use Http\Factory\Guzzle\StreamFactory;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Miklcct\NationalRailTimetable\Config\Config;
use Miklcct\NationalRailTimetable\ValueObjects\Date;
use Miklcct\ThinPhpApp\Response\ViewResponseFactory;
use Miklcct\ThinPhpApp\Response\ViewResponseFactoryInterface;
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

function initialise_database() : ?Date {
    $capsule = get_capsule();
    $connections = get_generated_dates($capsule);
    $capsule->getDatabaseManager()->setDefaultConnection(array_key_first($connections));
    $capsule->bootEloquent();
    return array_first($connections);
}

function get_generated_dates(Manager $capsule) : array {
    $result = array_combine(
        array_keys(get_database_config())
        , array_map(fn(string $connection_name) => get_generated_date($capsule->getConnection($connection_name)), array_keys(get_database_config()))
    );
    uasort($result, static fn($a, $b) => $b <=> $a);
    return $result;
}

function get_database_config() : array {
    $container = get_container();
    /** @var Config $config */
    $config = $container->get(Config::class);
    
    $illuminate_config = [
        'driver' => 'mysql',
        'host' => $config->mysqlHost,
        'database' => $config->databaseName,
        'username' => $config->mysqlUsername,
        'password' => $config->mysqlPassword,
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
    ];
    $result['mysql'] = $illuminate_config;
    $illuminate_config['database'] = $config->alternativeDatabaseName;
    $result['mysql_alternative'] = $illuminate_config;
    return $result;
}

function get_capsule() : Manager {
    $capsule = new Manager();
    foreach (get_database_config() as $name => $illuminate_config) {
        $capsule->addConnection($illuminate_config, $name);
        $capsule->getConnection($name)->enableQueryLog();
    }
    return $capsule;
}

function get_generated_date(Connection $connection) : ?Date {
    try {
        $result = $connection
            ->table('import')
            ->orderByDesc('id')
            ->first()?->generated_date;
    } catch (QueryException) {
        $result = null;
    }
    if ($result !== null) {
        return Date::fromDateTimeInterface(new \Safe\DateTimeImmutable($result));
    }
    return null;
}

function get_container() : ContainerInterface {
    static $container;
    if ($container === null) {
        $container = (new ContainerBuilder())->addDefinitions(
            [
                Config::class => static fn() : Config => require __DIR__ . '/config.php',
                CacheInterface::class =>
                    static fn() => new Psr16Cache(new PhpFilesAdapter('', 0, __DIR__ . '/var/cache', true)),
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

function last_updated(Date $date = null) : ?Date {
    static $last_updated = null;
    $result = $last_updated;
    if ($date !== null) {
        $last_updated = $date;
    }
    return $result;
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
last_updated(initialise_database());
