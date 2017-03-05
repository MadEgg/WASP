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

namespace WASP\DB\Query;

use PHPUnit\Framework\TestCase;
use WASP\DB\Driver\Driver;

class FieldExpressionTest extends TestCase
{
    public function testFieldNoTable()
    {
        $db_mock = $this->prophesize(Driver::class);
        $db_mock->identQuote('foo')->willReturn('"foo"');
        $db = $db_mock->reveal();

        $param_mock = $this->prophesize(Parameters::class);
        $param_mock->getDB()->willReturn($db);
        $param_mock->getDefaultTable()->willReturn(null);
        $p = $param_mock->reveal();

        $a = new FieldExpression('foo');
        $sql = $a->toSQL($p);
        $this->assertEquals('"foo"', $sql);
        $this->assertFalse($a->isNull());
    }

    public function testFieldTable()
    {
        $db_mock = $this->prophesize(Driver::class);
        $db_mock->identQuote('foo')->willReturn('"foo"');
        $db = $db_mock->reveal();

        $param_mock = $this->prophesize(Parameters::class);
        $param_mock->getDB()->willReturn($db);
        $p = $param_mock->reveal();

        $table_mock = $this->prophesize(TableClause::class);
        $table_mock->toSQL($p)->willReturn('"PLACEHOLDER"');

        $param_mock->getDefaultTable()->willReturn($table_mock->reveal());

        $a = new FieldExpression('foo');
        $sql = $a->toSQL($p);
        $this->assertEquals('"PLACEHOLDER"."foo"', $sql);
        $this->assertFalse($a->isNull());
    }
}