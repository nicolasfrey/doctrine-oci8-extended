<?php

declare(strict_types=1);

/*
 * This file is part of the doctrine-oci8-extended package.
 *
 * (c) Jason Hofer <jason.hofer@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\DBAL\Test;

use Doctrine\DBAL;
use Doctrine\DBAL\Driver\OCI8Ext\Driver;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use function getenv;

/**
 * Class AbstractTestCase.
 *
 * @author  Jason Hofer <jason.hofer@gmail.com>
 * 2018-02-23 4:26 PM
 *
 * @internal
 */
abstract class AbstractTestCase extends TestCase
{
    /**
     * @var DBAL\Connection
     */
    private $connection;

    /**
     * @var OciWrapper
     */
    private $oci;

    /**
     * @throws DBAL\DBALException
     *
     * @return DBAL\Connection
     */
    protected function getConnection(): DBAL\Connection
    {
        if ($this->connection) {
            return $this->connection;
        }

        $params = [
            'user' => getenv('DB_USER'),
            'password' => getenv('DB_PASSWORD'),
            'host' => getenv('DB_HOST'),
            'port' => getenv('DB_PORT'),
            'dbname' => getenv('DB_SCHEMA'),
            'driverClass' => Driver::class,
        ];

        $config = new DBAL\Configuration();

        return $this->connection = DBAL\DriverManager::getConnection($params, $config);
    }

    protected function getPropertyValue($obj, $prop)
    {
        $rObj = new ReflectionObject($obj);
        $rProp = $rObj->getProperty($prop);
        $rProp->setAccessible(true);

        return $rProp->getValue($obj);
    }

    protected function invokeMethod($obj, $method, array $args = [])
    {
        $rObj = new ReflectionObject($obj);
        $rMethod = $rObj->getMethod($method);
        $rMethod->setAccessible(true);

        return $rMethod->invokeArgs($obj, $args);
    }

    protected function oci(): OciWrapper
    {
        return $this->oci ?: ($this->oci = new OciWrapper());
    }
}
