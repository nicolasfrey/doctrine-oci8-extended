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

namespace Doctrine\DBAL\Driver\OCI8Ext;

use Doctrine\DBAL\Driver\OCI8\OCI8Connection as BaseConnection;

/**
 * Class OCI8Connection.
 *
 * @author  Jason Hofer <jason.hofer@gmail.com>
 * 2018-02-21 7:56 PM
 */
class OCI8Connection extends BaseConnection
{
    /**
     * @param resource $sth
     *
     * @return OCI8Cursor<array>
     */
    public function newCursor($sth = null): OCI8Cursor
    {
        return new OCI8Cursor($this->dbh, $this, $sth);
    }

    /**
     * @param string $prepareString
     *
     * @return OCI8Statement<array>
     */
    public function prepare($prepareString): OCI8Statement
    {
        return new OCI8Statement($this->dbh, $prepareString, $this);
    }
}
