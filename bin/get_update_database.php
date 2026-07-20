#!/usr/bin/php
<?php
declare(strict_types=1);

require __DIR__ . '/../initialise.php';

/**
 * @var \Illuminate\Database\Connection $connection
 * @var \DateTimeImmutable $date
 */
$dates = get_generated_dates(get_capsule());
$config = get_database_config()[array_key_last($dates)];
echo json_encode(
    [
        'DATABASE_HOST' => $config['host'] ?? null,
        'DATABASE_PORT' => $config['port'] ?? null,
        'DATABASE_NAME' => $config['database'] ?? null,
        'DATABASE_USERNAME' => $config['username'] ?? null,
        'DATABASE_PASSWORD' => $config['password'] ?? null,
        'generated' => array_last($dates)?->format('Y-m-d'),
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
);
