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

namespace Doctrine\DBAL\Test\Driver\OCI8Ext;

use Doctrine\DBAL\Driver\OCI8Ext\OCI8;
use Doctrine\DBAL\Test\AbstractTestCase;
use PDO;
use const OCI_B_CURSOR;
use const PHP_INT_MAX;

/**
 * Class OCI8Test.
 *
 * @author  Jason Hofer <jason.hofer@gmail.com>
 * 2018-02-24 1:02 AM
 *
 * @internal
 * @coversNothing
 */
final class OCI8Test extends AbstractTestCase
{
    public function testDecodeParamConstant(): void
    {
        self::assertSame(OCI_B_CURSOR, OCI8::decodeParamConstant(OCI8::PARAM_CURSOR));
    }

    public function testDecodeParamConstantReturnsGivenValueIfNotParamConstant(): void
    {
        self::assertSame(0, OCI8::decodeParamConstant(0));
        self::assertSame(1, OCI8::decodeParamConstant(1));
        self::assertSame(PDO::PARAM_STMT, OCI8::decodeParamConstant(PDO::PARAM_STMT));
        self::assertSame(PHP_INT_MAX, OCI8::decodeParamConstant(PHP_INT_MAX));
    }

    public function testIsParamConstant(): void
    {
        self::assertTrue(OCI8::isParamConstant(OCI8::PARAM_CURSOR));

        self::assertFalse(OCI8::isParamConstant(0));
        self::assertFalse(OCI8::isParamConstant(1));
        self::assertFalse(OCI8::isParamConstant(PDO::PARAM_STMT));
        self::assertFalse(OCI8::isParamConstant(PHP_INT_MAX));
    }
}
