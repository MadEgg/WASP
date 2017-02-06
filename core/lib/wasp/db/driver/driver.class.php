<?php
/*
This is part of WASP, the Web Application Software Platform.
It is published under the MIT Open Source License.

Copyright 2017, Egbert van der Wal

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

namespace WASP\DB\Driver;

use WASP\DB\DB;
use WASP\DB\DBException;

use WASP\DB\Table\Table;
use WASP\DB\Table\Index;
use WASP\DB\Table\ForeignKey;
use WASP\DB\Table\Column\Column;

use WASP\Config;
use WASP\Debug\Log;

use PDO;
use PDOException;

abstract class Driver
{
    protected $logger;
    protected $db;
    protected $table_prefix = "";

    protected $dbname;
    protected $schemaname;

    protected $mapping = array();
    protected $iquotechar = null;

    public function __construct($db)
    {
        if (!($db instanceof PDO || $db instanceof DB))
            throw new DBException("The driver needs a DB or PDO object to work with");

        $this->db = $db;
        $this->logger = new Log($this);
    }

    /**
     * Set a prefix that will be prepended to all table names
     * and all key names (indexes, foreign keys etc).
     * @param $prefix string The prefix string
     * @return $Driver Provides fluent interface
     */
    public function setTablePrefix($prefix)
    {
        $this->table_prefix = $prefix;
        return $this;
    }

    public function setDatabaseName($dbname, $schema = null)
    {
        $this->dbname = $dbname;
        if ($schema !== null)
            $this->schema = $schema;

        return $this;
    }

    /**
     * Quote the name of an identity
     * @param $name string The name to quote
     * @return string The quoted name
     */
    public function identQuote($name)
    {
        return $this->iquotechar . str_replace($this->iquotechar, $this->iquotechar . $this->iquotechar, $name) . $this->iquotechar;
    }

    /**
     * Return the identity name quoted and prefixed with the configured prefix.
     *
     * @param $entity mixed A string with the name or a Table, Index or ForeignKey
     *                     object with a getName method.
     * @return string The quoted, prefixed name
     */
    public function getName($entity)
    {
        if (is_object($entity))
            $entity = $entity->getName();
        if (!is_string($entity))
            throw new DBException("Provide a string or a object with a getName method");
        return $this->identQuote($this->table_prefix . $entity);
    }


    // CRUD
    abstract public function select($table, $where, $order, array $params);
    abstract public function update($table, $idfield, array $record);
    abstract public function insert($table, $idfield, array &$record);
    abstract public function delete($table, $where);

    /**
     * Remove all rows from the table
     *
     * @param $table mixed The table from which to remove
     * @return Driver Provides fluent interface
     */
    public function truncateTable($table)
    {
        $query = "TRUNCATE " . $this->getName($table->getName());
        $this->db->exec($query);
        return $this;
    }

    // Non-standard SQL, but commonly available
    abstract public function upsert($table, $idfield, $conflict, array &$record);

    // Table administration
    abstract public function createTable(Table $table);
    abstract public function createIndex(Table $table, Index $idx);
    abstract public function dropIndex(Table $table, Index $idx);
    abstract public function createForeignKey(Table $table, ForeignKey $fk);
    abstract public function dropForeignKey(Table $table, ForeignKey $fk);
    abstract public function createSerial(Table $table, Column $column);
    abstract public function dropSerial(Table $table, Column $column);
    abstract public function addColumn(Table $table, Column $column);
    abstract public function removeColumn(Table $table, Column $column);
    abstract public function getColumnDefinition(Column $col);
    abstract public function dropTable($table, $safe = false);

    // Importing / generating table definitions
    abstract public function loadTable($table_name);
    abstract public function getColumns($table_name);
    abstract public function getConstraints($table_name);

    // Standard SQL parts - can be overriden if required, should usually not be necessary
    public function getWhere($where, &$col_idx, array &$params)
    {
        if (is_string($where))
            return " WHERE " . $where;

        if (is_array($where) && count($where))
        {
            $parts = array();
            foreach ($where as $k => $v)
            {
                if (is_array($v))
                {
                    $op = $v[0];
                    $val = $v[1];
                }
                else
                {
                    $op = "=";
                    $val = $v;
                }

                if ($val === null)
                {
                    if ($op === "=")
                        $parts[] = self::identQuote($k) . " IS NULL";
                    else if ($op == "!=")
                        $parts[] = self::identQuote($k) . " IS NOT NULL";
                }
                else
                {
                    $col_name = "col" . (++$col_idx);
                    $parts[] = self::identQuote($k) . " {$op} :{$col_name}";
                    $params[$col_name] = $v;
                }
            }

            return " WHERE " . implode(" AND ", $parts);
        }

        return "";
    }

    public function getOrder($order)
    {
        if (is_string($order))
            return "ORDER BY " . $order;

        if (is_array($order) && count($order))
        {
            $parts = array();
            foreach ($order as $k => $v)
            {
                if (is_numeric($k))
                {
                    $k = $v;
                    $v = "ASC";
                }
                else
                {
                    $v = strtoupper($v);
                    if ($v !== "ASC" && $v !== "DESC")
                        throw new DBException("Invalid order type {$v}");
                }
                $parts[] = self::identQuote($k) . " " . $v;
            }

            return " ORDER BY " . implode(", ", $parts);
        }

        return "";
    }

}
