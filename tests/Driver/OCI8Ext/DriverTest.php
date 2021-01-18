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

/** @noinspection PhpUnhandledExceptionInspection */

namespace Doctrine\DBAL\Test\Driver\OCI8Ext;

use Doctrine\DBAL\Driver\OCI8Ext\OCI8Connection;
use Doctrine\DBAL\Test\AbstractTestCase;
use Doctrine\DBAL\Types\Type;

/**
 * Class DriverTest.
 *
 * @author  Jason Hofer <jason.hofer@gmail.com>
 * 2018-02-23 3:01 PM
 *
 * @internal
 * @coversNothing
 */
final class DriverTest extends AbstractTestCase
{
    public function testDriverManagerReturnsWrappedOci8ExtConnection(): void
    {
        self::assertInstanceOf(
            OCI8Connection::class,
            $this->getConnection()->getWrappedConnection()
        );
    }

    public function testDriverRegistersCursorType(): void
    {
        $this->getConnection();

        self::assertTrue(Type::hasType('cursor'));
    }
}
