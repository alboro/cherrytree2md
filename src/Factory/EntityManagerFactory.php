<?php

namespace Ctb2Md\Factory;

use Ctb2Md\Dbal\RegisterTypes;
use Ctb2Md\RegisterTypesInterface;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use Doctrine\ORM\ORMSetup;

class EntityManagerFactory
{
    public function __construct(private string $rootDir) {}
    public function entityManager($file): EntityManagerInterface
    {
        $config = require $this->rootDir . '/config/doctrine/config.php';

        return $this->createEntityManager(
            $config['mappings'],
            $config['namespace'],
            array_merge($config['params'], [
                'path' => $file,
            ]),
            true,
            new RegisterTypes(),
        );
    }
    /**
     * Создание и получение EntityManager
     *
     * @param string $xmlMappings mapping dir
     * @param string $namespace
     * @param array $dbParams Параметры подключения к базе данных
     * @param bool $isDevMode Включить режим разработки
     * @return EntityManagerInterface
     */
    private function createEntityManager(
        string $xmlMappings,
        string $namespace,
        array $dbParams,
        bool $isDevMode = true,
        ?RegisterTypesInterface $registerTypes = null,
    ): EntityManagerInterface {
        $config = ORMSetup::createConfiguration($isDevMode);
        $config->setMetadataDriverImpl(
             new SimplifiedXmlDriver([$xmlMappings => $namespace])
        );
        null !== $registerTypes && $registerTypes->register();
        $connection = DriverManager::getConnection($dbParams, $config);

        return new EntityManager($connection, $config);
    }
}
