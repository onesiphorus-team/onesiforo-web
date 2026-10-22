<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

if (
    getenv('CONCURRENCY_TESTS') !== '1'
    || getenv('DB_CONNECTION') !== 'mariadb'
    || getenv('DB_DATABASE') !== 'onesiforo_concurrency_testing'
    || ! in_array(getenv('DB_HOST'), ['127.0.0.1', 'localhost'], true)
    || getenv('DB_URL')
    || getenv('DB_SOCKET')
) {
    throw new RuntimeException('Concurrency tests require explicit opt-in and the dedicated local MariaDB database onesiforo_concurrency_testing.');
}
