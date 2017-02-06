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

namespace WASP\DB\Table;

use WASP\DB\DBException;

class ForeignKey
{
    const DO_CASCADE = 1;
    const DO_RESTRICT = 2;
    const DO_NULL = 3;

    protected $name = null;
    protected $table = null;
    protected $columns = array();
    protected $referred_table = null;
    protected $referred_columns = array();

    protected $on_update = null;
    protected $on_delete = null;

    public function __construct(
        $column = null, 
        $referred_table = null, 
        $referred_column = null, 
        $on_update = ForeignKey::DO_RESTRICT, 
        $on_delete = ForeignKey::DO_RESTRICT
    )
    {
        if (is_array($column))
        {
            if (isset($column['column']) && isset($column['referred_table']) && isset($column['referred_column']))
            {
                $this->columns = (array)$column['column'];
                $this->referred_table = $column['referred_table'];
                $this->referred_columns = (array)$column['referred_column'];
                if (isset($column['name']))
                    $this->name = (int)$column['name'];
                if (isset($column['on_update']))
                    $this->on_update = (int)$column['on_update'];
                if (isset($column['on_delete']))
                    $this->on_delete = (int)$column['on_delete'];
            }
            elseif (is_array($referred_column) && count($referred_column) === count($column))
            {
                $this->columns = array_values($column);
                $this->referred_columns = array_values($referred_column);
                $this->referred_table = $referred_table;
            }
            else
                throw new DBException("Invalid arguments specified: first argument is not a suitable array");
        }
        else
        {
            $this->columns = (array)$column;
            $this->referred_table = $referred_table;
            if (isset($referred_column))
                $this->referred_columns = (array)$referred_column;
            $this->on_update = $on_update;
            $this->on_delete = $on_delete;
        }
    }

    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function getName()
    {
        if ($this->table === null)
            throw new DBException("No table set for foreign key");

        if ($this->name === null)
        {
            $this->name = $this->table . "_";
            $this->name .= implode("_", $this->columns) . "_fkey";
        }
        return $this->name;
    }

    public function setTable($table)
    {
        if ($table instanceof Table)
            $table = $table->getName();

        $this->table = $table;
        return $this;
    }

    public function addReferringColumn(Column $column)
    {
        $args = func_get_args();
        foreach ($args as $arg)
        {
            if (!($arg instanceof Column))
                throw new DBException("Invalid column");
            $this->columns[] = $arg;

            $t = $arg->getTable();

            if ($t === null)
                throw new DBException("Column does not belong to a table");

            if ($this->table !== null && $this->table !== $t)
                throw new DBException("All referring columns must be in the same table");

            $this->table = $t;
        }
        return $this;
    }

    public function setReferredTable($table)
    {
        if ($table instanceof Table)
            $this->table = $table->getName();
        else
            $this->table = $table;
        return $this;
    }

    public function addReferredColumn($column)
    {
        $args = func_get_args();
        foreach ($args as $arg)
        {
            if (is_string($arg))
                $this->referred_columns[] = $column; 
            elseif ($arg instanceof Column)
                $this->referred_columns[] = $column->getName();
            else
                throw new DBException("Invalid column type");
        }
        return $this;
    }

    public function setOnUpdate($action)
    {
        if ($action !== ForeignKey::DO_UPDATE &&
            $action !== ForeignKey::DO_RESTRICT &&
            $action !== ForeignKey::DO_DELETE)
            throw new DBException("Invalid on update policy: $action");
        $this->on_update = $action;
        return $this;
    }

    public function setOnDelete($action)
    {
        if ($action !== ForeignKey::DO_UPDATE &&
            $action !== ForeignKey::DO_RESTRICT &&
            $action !== ForeignKey::DO_DELETE)
            throw new DBException("Invalid on update policy: $action");
        $this->on_delete = $action;
        return $this;
    }

    public function getTable()
    {
        return $this->columns;
    }

    public function getReferredTable()
    {
        return $this->referred_table;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function getReferredColumns()
    {
        return $this->referred_columns;
    }

    public function getOnUpdate()
    {
        return $this->on_update;
    }

    public function getOnDelete()
    {
        return $this->on_delete;
    }
}
