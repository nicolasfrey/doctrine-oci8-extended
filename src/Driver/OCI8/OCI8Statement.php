<?php

/*
 * This file is part of the doctrine-oci8-extended package.
 *
 * (c) Jason Hofer <jason.hofer@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\DBAL\Driver\OCI8Ext;

use Doctrine\DBAL\Driver\OCI8\OCI8Statement as BaseStatement;

/**
 * Class OCI8Statement
 *
 * @package Doctrine\DBAL\Driver\OCI8Ext
 * @author  Jason Hofer <jason.hofer@gmail.com>
 * 2018-02-21 7:54 PM
 */
class OCI8Statement extends BaseStatement
{
    /**
     * Holds references to bound parameter values.
     *
     * This is a new requirement for PHP7's oci8 extension that prevents bound values from being garbage collected.
     *
     * @see \Doctrine\DBAL\Driver\OCI8\OCI8Statement::$boundValues
     *
     * @var array
     */
    private $references = array();

    /** @var array */
    protected $cursorFields = array();
    /** @var bool */
    protected $checkedForCursorFields = false;
    /** @var bool */
    protected $hasCursorFields = false;

    /** @var bool */
    private $returningResources = false;
    /** @var bool */
    private $returningCursors = false;

    /**
     * @param string     $param
     * @param mixed      $value
     * @param int|string $type
     *
     * @return bool
     *
     * @throws \LogicException
     */
    public function bindValue($param, $value, $type = null)
    {
        list($type, $ociType) = $this->normalizeType($type);

        if (\PDO::PARAM_STMT === $type || OCI_B_CURSOR === $ociType) {
            throw new \LogicException('You must call "bindParam()" to bind a cursor.');
        }

        return parent::bindValue($param, $value, $type);
    }

    /** @noinspection MoreThanThreeArgumentsInspection */
    /**
     * @param string     $column
     * @param mixed      $variable
     * @param int|string $type
     * @param int|null   $length
     *
     * @return bool
     */
    public function bindParam($column, &$variable, $type = \PDO::PARAM_STR, $length = null)
    {
        $origCol = $column;

        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $column = isset($this->_paramMap[$column]) ? $this->_paramMap[$column] : $column;

        list($type, $ociType) = $this->normalizeType($type);

        // Type: Cursor.
        if (\PDO::PARAM_STMT === $type || OCI_B_CURSOR === $ociType) {
            /** @var OCI8Connection $conn Because my IDE complains. */
            $conn     = $this->_conn;
            $variable = $conn->newCursor();

            return $this->bindByName($column, $variable->_sth, -1, OCI_B_CURSOR);
        }

        // Type: Null. (Must come *after* types that can expect $variable to be null, like 'cursor'.)
        if (null === $variable) {
            return $this->bindByName($column, $variable);
        }

        // Type: Array.
        if (is_array($variable)) {
            $length = null === $length ? -1 : $length;

            if (!$ociType) {
                $ociType = \PDO::PARAM_INT === $type ? SQLT_INT : SQLT_CHR;
            }

            return $this->bindArrayByName(
                $column,
                $variable,
                max(count($variable), 1),
                empty($variable) ? 0 : $length,
                $ociType
            );
        }

        // Type: Lob
        if (OCI_B_CLOB === $ociType || OCI_B_BLOB === $ociType) {
            $type = \PDO::PARAM_LOB;
        } elseif ($ociType) {
            return $this->bindByName($column, $variable, null === $length ? -1 : $length, $ociType);
        }

        return parent::bindParam($origCol, $variable, $type, $length);
    }

    /** @noinspection MoreThanThreeArgumentsInspection */
    /**
     * @param string $column
     * @param mixed  $variable
     * @param int    $maxLength
     * @param int    $type
     *
     * @return bool
     */
    protected function bindByName($column, &$variable, $maxLength = -1, $type = SQLT_CHR)
    {
        // For PHP 7's OCI8 extension (prevents garbage collection).
        $this->references[$column] =& $variable;

        return oci_bind_by_name($this->_sth, $column, $variable, $maxLength, $type);
    }

    /** @noinspection MoreThanThreeArgumentsInspection */
    /**
     * @param string $column
     * @param mixed  $variable
     * @param int    $maxTableLength
     * @param int    $maxItemLength
     * @param int    $type
     *
     * @return bool
     */
    protected function bindArrayByName($column, &$variable, $maxTableLength, $maxItemLength = -1, $type = SQLT_AFC)
    {
        // For PHP 7's OCI8 extension (prevents garbage collection).
        $this->references[$column] =& $variable;

        return oci_bind_array_by_name($this->_sth, $column, $variable, $maxTableLength, $maxItemLength, $type);
    }

    /**
     * @param int|string $type
     *
     * @return array
     */
    protected function normalizeType($type)
    {
        $ociType = null;

        // Figure out the type.
        if (is_numeric($type)) {
            $type = (int) $type;
            if (OCI8::isParamConstant($type)) {
                $ociType = OCI8::decodeParamConstant($type);
            }
        } elseif ('cursor' === strtolower($type)) {
            $type    = \PDO::PARAM_STMT;
            $ociType = OCI_B_CURSOR;
        }

        return array($type, $ociType);
    }

    /**
     * @param int $fetchMode
     *
     * @return array|bool|mixed
     *
     * @throws \Doctrine\DBAL\Driver\OCI8\OCI8Exception
     */
    public function fetch($fetchMode = null)
    {
        list($fetchMode, $returnResources, $returnCursors) = $this->processFetchMode($fetchMode, true);

        $row = parent::fetch($fetchMode);

        if (!$returnResources) {
            $this->fetchCursorFields($row, $fetchMode, $returnCursors);
        }

        return $row;
    }

    /**
     * @param int $fetchMode
     *
     * @return array|mixed
     *
     * @throws \Doctrine\DBAL\Driver\OCI8\OCI8Exception
     */
    public function fetchAll($fetchMode = null)
    {
        list(
            $fetchMode,
            $this->returningResources,
            $this->returningCursors
        ) = $this->processFetchMode($fetchMode);

        $results = parent::fetchAll($fetchMode);

        if (
            !$this->returningResources &&
            is_array($results) &&
            $fetchMode !== \PDO::FETCH_COLUMN &&
            self::$fetchModeMap[$fetchMode] !== OCI_BOTH // handled in parent::fetchAll()
        ) {
            foreach ($results as &$row) {
                $this->fetchCursorFields($row, $fetchMode, $this->returningCursors);
            }
            unset($row);
        }

        $this->returningResources =
        $this->returningCursors   = false;
        $this->resetCursorFields();

        return $results;
    }

    /**
     * @param array|mixed $row
     * @param int         $fetchMode
     * @param bool        $returnCursors
     *
     * @throws \Doctrine\DBAL\Driver\OCI8\OCI8Exception
     */
    protected function fetchCursorFields(&$row, $fetchMode, $returnCursors)
    {
        if (!is_array($row)) {
            $this->resetCursorFields();
        } elseif (!$this->checkedForCursorFields) {
            // This will also call fetchCursorField() on each cursor field of the first row.
            $this->findCursorFields($row, $fetchMode, $returnCursors);
        } elseif ($this->hasCursorFields) {
            $shared = array();
            foreach ($this->cursorFields as $field) {
                $key = (string) $row[$field];
                if (isset($shared[$key])) {
                    $row[$field] = $shared[$key];
                    continue;
                }
                $row[$field]  =  $this->fetchCursorValue($row[$field], $fetchMode, $returnCursors);
                $shared[$key] =& $row[$field];
            }
        }
    }

    protected function resetCursorFields()
    {
        $this->cursorFields           = array();
        $this->checkedForCursorFields =
        $this->hasCursorFields        = false;
    }

    /**
     * @param array $row
     * @param int   $fetchMode
     * @param bool  $returnCursors
     *
     * @throws \Doctrine\DBAL\Driver\OCI8\OCI8Exception
     */
    protected function findCursorFields(array &$row, $fetchMode, $returnCursors)
    {
        $shared = array();
        foreach ($row as $field => $value) {
            if (is_resource($value)) {
                $this->hasCursorFields = true;
                $this->cursorFields[]  = $field;
                $key = (string) $value;
                if (isset($shared[$key])) {
                    $row[$field] = $shared[$key];
                    continue;
                }
                // We are already here, so might as well process it.
                $row[$field]  =  $this->fetchCursorValue($row[$field], $fetchMode, $returnCursors);
                $shared[$key] =& $row[$field];
            }
        }
        $this->checkedForCursorFields = true;
    }

    /**
     * @param resource $resource
     * @param int      $fetchMode
     * @param bool     $returnCursor
     *
     * @return array|mixed|OCI8Cursor
     *
     * @throws \Doctrine\DBAL\Driver\OCI8\OCI8Exception
     */
    protected function fetchCursorValue($resource, $fetchMode, $returnCursor)
    {
        /** @var OCI8Connection $conn Because my IDE complains. */
        $conn   = $this->_conn;
        $cursor = $conn->newCursor($resource);

        if ($returnCursor) {
            return $cursor;
        }

        $cursor->execute();
        $results = $cursor->fetchAll($fetchMode);
        $cursor->closeCursor();

        return $results;
    }

    /**
     * @param int  $fetchMode
     * @param bool $checkGlobal
     *
     * @return array
     */
    protected function processFetchMode($fetchMode, $checkGlobal = false)
    {
        $returnResources  = ($checkGlobal && $this->returningResources) || ($fetchMode & OCI8::RETURN_RESOURCES);
        $returnCursors    = ($checkGlobal && $this->returningCursors)   || ($fetchMode & OCI8::RETURN_CURSORS);
        $fetchMode       &= ~(OCI8::RETURN_RESOURCES+OCI8::RETURN_CURSORS); // Must unset the flags or there will be an error.
        $fetchMode        = $fetchMode ?: $this->_defaultFetchMode;

        return array($fetchMode, $returnResources, $returnCursors);
    }
}