<?php

declare(strict_types=1);

return [
    'mappings' => dirname(__DIR__, 2) . "/config/doctrine/orm",
    'namespace' => 'Ctb2Md\Entity',
    'params' => [
        'driver' => 'pdo_sqlite',
    ],
];
