<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Test;

use const OCI_DEFAULT;

/**
 * Class OciWrapper.
 *
 * Utility class for performing database setup and tear-down.
 */
class OciWrapper
{
    private $dbh;

    public function __construct()
    {
        // Prevent OCI_SUCCESS_WITH_INFO: ORA-28002: the password will expire within 7 days
        $this->execute('ALTER PROFILE DEFAULT LIMIT PASSWORD_LIFE_TIME UNLIMITED');

        // We must change the password in order to take this in account.
        oci_password_change(
            $this->connect(),
            getenv('DB_USER'),
            getenv('DB_PASSWORD'),
            getenv('DB_PASSWORD')
        );
    }

    public function close(): bool
    {
        $result = oci_close($this->dbh);
        $this->dbh = null;

        return $result;
    }

    public function connect()
    {
        if (!$this->dbh) {
            $this->dbh = oci_connect(
                getenv('DB_USER'),
                getenv('DB_PASSWORD'),
                '//' . getenv('DB_HOST') . ':' . getenv('DB_PORT') . '/' . getenv('DB_SCHEMA'),
                getenv('DB_CHARSET'),
                OCI_DEFAULT
            );

            if (!$this->dbh) {
                /** @var array $m */
                $m = oci_error();

                throw new RuntimeException($m['message']);
            }
        }

        return $this->dbh;
    }

    public function createTable($name, array $columns)
    {
        $this->drop('table', $name);

        return $this->execute(sprintf('CREATE TABLE %s (%s)', $name, implode(', ', $columns)));
    }

    /**
     * https://stackoverflow.com/questions/1799128/oracle-if-table-exists.
     *
     * @param string $type
     * @param string $name
     *
     * @return bool
     */
    public function drop($type, $name): bool
    {
        static $codes = [
            'COLUMN' => '-904',
            'TABLE' => '-942',
            'CONSTRAINT' => '-2443',
            'FUNCTION' => '-4043',
            'PACKAGE' => '-4043',
            'PROCEDURE' => '-4043',
        ];
        $type = mb_strtoupper($type);
        $code = $codes[$type];

        if (false !== mb_strpos('COLUMN CONSTRAINT', $type)) {
            $pos = mb_strrpos($name, '.');
            $table = mb_substr($name, 0, $pos);  // "PACKAGE_NAME.TABLE_NAME" or just "TABLE_NAME"
            $column = mb_substr($name, $pos + 1); // "COLUMN_NAME"
            $query = "ALTER TABLE {$table} DROP {$type} {$column}";
        } else {
            $query = "DROP {$type} {$name}";
        }
        $sql = "
BEGIN
EXECUTE IMMEDIATE '{$query}';
EXCEPTION
WHEN OTHERS THEN
IF SQLCODE != {$code} THEN
RAISE;
END IF;
END;
";

        return (bool) $this->execute($sql);
    }

    public function execute($sql)
    {
        $stmt = $this->parse($sql);

        return oci_execute($stmt) ? $stmt : false;
    }

    public function parse($sql)
    {
        return oci_parse($this->connect(), $sql);
    }
}
