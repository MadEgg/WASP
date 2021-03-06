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

namespace WASP\Util;

use PHPUnit\Framework\TestCase;
use WASP\IO\Dir;
use WASP\Platform\System;

/**
 * @covers WASP\Dictionary
 */
final class DictionaryTest extends TestCase
{
    private $pathconfig;
    private $path = null;

    public function setUp()
    {
        $this->pathconfig = System::path();
        Dir::setRequiredPrefix($this->pathconfig->var);
        $this->path = $this->pathconfig->var . '/test';
        if (file_exists($this->path))
            Dir::rmtree($this->path);
        Dir::mkdir($this->path);
    }
    
    public function tearDown()
    {
        if ($this->path)
            Dir::rmtree($this->path); 
    }

    /**
     * @covers WASP\Dictionary::__construct
     * @covers WASP\Dictionary::getAll
     */
    public function testConstruct()
    {
        $dict = new Dictionary();
        $this->assertInstanceOf(Dictionary::class, $dict);
        $this->assertTrue(empty($dict->getAll()));
    }

    /**
     * @covers WASP\Dictionary::__construct
     * @covers WASP\Dictionary::get
     * @covers WASP\Dictionary::dget
     * @covers WASP\Dictionary::offsetGet
     * @covers WASP\Dictionary::has
     */
    public function testConstructArray()
    {
        $data = array('var1' => 'val1', 'var2' => 'val2');
        $dict = Dictionary::wrap($data);

        $this->assertEquals($dict['var1'], 'val1');
        $this->assertEquals($dict['var2'], 'val2');
        $this->assertEquals($dict->get('var1'), 'val1');
        $this->assertEquals($dict->get('var2'), 'val2');
        $this->assertEquals($dict->get('var3'), null);
        $this->assertEquals($dict->dget('var3', 'foo'), 'foo');

        // Set the var3 value to check setting
        $dict->set('var3', 'val3');
        $this->assertEquals($dict->dget('var3', 'foo'), 'val3');

        // Test alternative invocation
        $dict->set(['var3', 'val4'], null);
        $this->assertEquals($dict->dget('var3', 'foo'), 'val4');

        // Test if referenced array is updated
        $this->assertEquals($data['var3'], 'val4');

        $this->assertFalse($dict->has('var1', 'var2'));
    }

    /**
     * @covers WASP\Dictionary::__construct
     * @covers WASP\Dictionary::get
     * @covers WASP\Dictionary::has
     * @covers WASP\Dictionary::offsetGet
     */
    public function testConstructArrayRecursive()
    {
        $data = array('var1' => 'val1', 'var2' => array('a' => 1, 'b' => 2, 'c' => 3));
        $dict = Dictionary::wrap($data);

        $this->assertEquals($dict['var1'], 'val1');
        $this->assertTrue($dict->has('var2', Dictionary::TYPE_ARRAY));
        $this->assertEquals($dict->get('var2', 'a'), 1);
        $this->assertEquals($dict->get('var2', 'b'), 2);
        $this->assertEquals($dict->get('var2', 'c'), 3);
        $this->assertNull($dict->get('var2', 'd'));

        $dict->set('var2', 'd', 4);
        $this->assertTrue($dict->has('var2', 'd', Dictionary::TYPE_INT));

        // Test if referenced array is updated
        $this->assertEquals($data['var2']['d'], 4);
    }

    /**
     * @covers WASP\Dictionary::has
     * @covers WASP\Dictionary::set
     */
    public function testTypeChecking()
    {
        $dict = new Dictionary();

        $dict->set('int', 1);
        $dict->set('float', 1.0);
        $dict->set('object', new \StdClass());
        $dict->set('array', array(1, 2, 3));
        $dict->set('string', 'test');
        $dict->set('stringint', '1');
        $dict->set('stringfloat', '1.0');

        $this->assertTrue($dict->has('int'));
        $this->assertTrue($dict->has('int', Dictionary::TYPE_INT));
        $this->assertTrue($dict->has('int', Dictionary::TYPE_NUMERIC));
        $this->assertFalse($dict->has('int', Dictionary::TYPE_FLOAT));
        $this->assertFalse($dict->has('int', Dictionary::TYPE_STRING));
        $this->assertFalse($dict->has('int', Dictionary::TYPE_ARRAY));
        $this->assertFalse($dict->has('int', Dictionary::TYPE_OBJECT));

        $this->assertTrue($dict->has('float'));
        $this->assertTrue($dict->has('float', Dictionary::TYPE_FLOAT));
        $this->assertTrue($dict->has('float', Dictionary::TYPE_NUMERIC));
        $this->assertFalse($dict->has('float', Dictionary::TYPE_INT));
        $this->assertFalse($dict->has('float', Dictionary::TYPE_STRING));
        $this->assertFalse($dict->has('float', Dictionary::TYPE_ARRAY));
        $this->assertFalse($dict->has('float', Dictionary::TYPE_OBJECT));

        $this->assertTrue($dict->has('object'));
        $this->assertTrue($dict->has('object', Dictionary::TYPE_OBJECT));
        $this->assertFalse($dict->has('object', Dictionary::TYPE_INT));
        $this->assertFalse($dict->has('object', Dictionary::TYPE_FLOAT));
        $this->assertFalse($dict->has('object', Dictionary::TYPE_NUMERIC));
        $this->assertFalse($dict->has('object', Dictionary::TYPE_STRING));
        $this->assertFalse($dict->has('object', Dictionary::TYPE_ARRAY));

        $this->assertTrue($dict->has('array'));
        $this->assertTrue($dict->has('array', Dictionary::TYPE_ARRAY));
        $this->assertFalse($dict->has('array', Dictionary::TYPE_NUMERIC));
        $this->assertFalse($dict->has('array', Dictionary::TYPE_INT));
        $this->assertFalse($dict->has('array', Dictionary::TYPE_FLOAT));
        $this->assertFalse($dict->has('array', Dictionary::TYPE_STRING));
        $this->assertFalse($dict->has('array', Dictionary::TYPE_OBJECT));

        $this->assertTrue($dict->has('string'));
        $this->assertTrue($dict->has('string', Dictionary::TYPE_STRING));
        $this->assertFalse($dict->has('string', Dictionary::TYPE_NUMERIC));
        $this->assertFalse($dict->has('string', Dictionary::TYPE_INT));
        $this->assertFalse($dict->has('string', Dictionary::TYPE_FLOAT));
        $this->assertFalse($dict->has('string', Dictionary::TYPE_NUMERIC));
        $this->assertFalse($dict->has('string', Dictionary::TYPE_ARRAY));
        $this->assertFalse($dict->has('string', Dictionary::TYPE_OBJECT));

        $this->assertTrue($dict->has('stringint'));
        $this->assertTrue($dict->has('stringint', Dictionary::TYPE_NUMERIC));
        $this->assertTrue($dict->has('stringint', Dictionary::TYPE_INT));
        $this->assertTrue($dict->has('stringint', Dictionary::TYPE_STRING));
        $this->assertFalse($dict->has('stringint', Dictionary::TYPE_ARRAY));
        $this->assertFalse($dict->has('stringint', Dictionary::TYPE_FLOAT));
        $this->assertFalse($dict->has('stringint', Dictionary::TYPE_FLOAT));
        $this->assertFalse($dict->has('stringint', Dictionary::TYPE_OBJECT));

        $this->assertTrue($dict->has('stringfloat'));
        $this->assertTrue($dict->has('stringfloat', Dictionary::TYPE_NUMERIC));
        $this->assertTrue($dict->has('stringfloat', Dictionary::TYPE_STRING));
        $this->assertFalse($dict->has('stringfloat', Dictionary::TYPE_FLOAT));
        $this->assertFalse($dict->has('stringfloat', Dictionary::TYPE_ARRAY));
        $this->assertFalse($dict->has('stringfloat', Dictionary::TYPE_INT));
        $this->assertFalse($dict->has('stringfloat', Dictionary::TYPE_FLOAT));
        $this->assertFalse($dict->has('stringfloat', Dictionary::TYPE_OBJECT));
    }

    /** 
     * @covers WASP\Dictionary::getType
     * @covers WASP\Dictionary::getBool
     * @covers WASP\Dictionary::getInt
     * @covers WASP\Dictionary::getFloat
     * @covers WASP\Dictionary::getString
     * @covers WASP\Dictionary::getArray
     * @covers WASP\Dictionary::getObject
     */ 
    public function testGetType()
    {
        $data = array(1, 2, 3, 'test' => 'data', 'test2' => array('test3' => array('test4', 'test5', 'test6'), 'test7' => 'test8'));
        $dict = new Dictionary($data);

        $this->assertTrue(is_int($dict->getType(0, Dictionary::TYPE_INT)));
        $this->assertTrue($dict->getType(0, Dictionary::TYPE_INT) === 1);
        $this->assertFalse($dict->getType(0, Dictionary::TYPE_INT) === 1.0);

        $this->assertTrue(is_float($dict->getType(0, Dictionary::TYPE_FLOAT)));
        $this->assertEquals($dict->getType(0, Dictionary::TYPE_FLOAT), 1.0);
        $this->assertFalse($dict->getType(0, Dictionary::TYPE_FLOAT) === 1);

        $this->assertTrue(is_string($dict->getType(0, Dictionary::TYPE_STRING)));
        $this->assertTrue($dict->getType(0, Dictionary::TYPE_STRING) === "1");
        $this->assertFalse($dict->getType(0, Dictionary::TYPE_STRING) === 1);

        $dict['int'] = '3';
        $dict['fl'] = '3.5';
        $dict['str'] = 3;
        $dict['bool'] = true;
        $dict['bool2'] = false;
        $this->assertTrue($dict->getInt('int') === 3);
        $this->assertTrue($dict->getFloat('fl') === 3.5);
        $this->assertTrue($dict->getString('str') === '3');
        $this->assertTrue($dict->getBool('bool') === true);
        $this->assertTrue($dict->getBool('bool2') === false);

        $dict['bool'] = 'on';
        $dict['bool2'] = 'off';
        $this->assertTrue($dict->getBool('bool') === true);
        $this->assertTrue($dict->getBool('bool2') === false);


        $obj = new \StdClass();
        $dict['obj'] = $obj;
        $arr = array(1, 2, 3, 4);
        $dict['arr'] = $arr;

        $arr2 = array(5, 6, 7, 8);
        $dict['arr2'] = new Dictionary($arr2);

        $this->assertTrue($dict->has('obj', Dictionary::TYPE_OBJECT));
        $this->assertFalse($dict->has('obj', Dictionary::TYPE_ARRAY));
        $this->assertFalse($dict->has('obj', Dictionary::TYPE_INT));
        $this->assertFalse($dict->has('obj', Dictionary::TYPE_FLOAT));
        $this->assertFalse($dict->has('obj', Dictionary::TYPE_NUMERIC));
        $this->assertFalse($dict->has('obj', Dictionary::TYPE_STRING));
        $this->assertEquals($dict->getObject('obj'), $obj);

        $this->assertTrue($dict->has('arr', Dictionary::TYPE_ARRAY));
        $this->assertFalse($dict->has('arr', Dictionary::TYPE_OBJECT));
        $this->assertFalse($dict->has('arr', Dictionary::TYPE_INT));
        $this->assertFalse($dict->has('arr', Dictionary::TYPE_FLOAT));
        $this->assertFalse($dict->has('arr', Dictionary::TYPE_NUMERIC));
        $this->assertFalse($dict->has('arr', Dictionary::TYPE_STRING));
        $this->assertEquals($dict->getArray('arr'), $arr);

        $this->assertEquals($dict->getArray('arr2'), $arr2);

        $this->assertInstanceOf(Dictionary::class, $dict->getType('arr2', Dictionary::EXISTS));

        $this->expectException(\DomainException::class);
        $this->expectException(is_array($dict->getType(0, Dictionary::TYPE_ARRAY)));
    }

    public function testOverwriteType()
    {
        $a = new Dictionary();
        $a['test'] = "string";

        $a->set('test', 'test2', 'test3');
        $this->assertEquals($a->get('test', 'test2'), 'test3');
    }
    /** 
     * @covers WASP\Dictionary::getType
     */ 
    public function testGetTypeNotExistsException()
    {
        $a = new Dictionary();
        $this->expectException(\OutOfRangeException::class);
        $a->getType('a', Dictionary::TYPE_INT);
    }

    public function testGetTypeIntException()
    {
        $a = new Dictionary();
        $a['int'] = "str";
        $this->expectException(\DomainException::class);
        $a->getType('int', Dictionary::TYPE_INT);
    }

    public function testGetTypeFloatException()
    {
        $a = new Dictionary();
        $a['float'] = array();
        $this->expectException(\DomainException::class);
        $a->getType('float', Dictionary::TYPE_FLOAT);
    }

    public function testGetTypeStringException()
    {
        $a = new Dictionary();
        $a['string'] = array();
        $this->expectException(\DomainException::class);
        $a->getType('string', Dictionary::TYPE_STRING);
    }

    public function testGetTypeArrayException()
    {
        $a = new Dictionary();
        $a['array'] = 1;
        $this->expectException(\DomainException::class);
        $a->getType('array', Dictionary::TYPE_ARRAY);
    }

    public function testGetTypeObjectException()
    {
        $a = new Dictionary();
        $a['object'] = 1;
        $this->expectException(\DomainException::class);
        $a->getType('object', Dictionary::TYPE_OBJECT);
    }

    /**
     * @covers WASP\Dictionary::offsetGet
     * @covers WASP\Dictionary::offsetSet
     * @covers WASP\Dictionary::offsetExists
     * @covers WASP\Dictionary::offsetUnset
     * @covers WASP\Dictionary::getAll
     */
    public function testArrayAccess()
    {
        $data = array(1, 2, 3, 'test' => 'data', 'test2' => array('test3' => array('test4', 'test5', 'test6'), 'test7' => 'test8'));
        $dict = Dictionary::wrap($data);

        $keys = array_keys($data);
        foreach ($keys as $key)
        {
            $val = $dict[$key];
            if ($val instanceof Dictionary)
                $val = $val->getAll();
                
            $this->assertEquals($data[$key], $val);
        }

        $dict['test9'] = 'test10';
        $this->assertEquals($data['test9'], $dict->get('test9'));

        $this->assertTrue(isset($dict['test9']));
        unset($dict['test9']);
        $this->assertFalse(isset($dict['test9']));
    }

    /**
     * @covers WASP\Dictionary::rewind
     * @covers WASP\Dictionary::current
     * @covers WASP\Dictionary::next
     * @covers WASP\Dictionary::key
     * @covers WASP\Dictionary::valid
     */
    public function testIterator()
    {
        $data = array(1, 2, 3, 'test' => 'data', 'test2' => array('test3' => array('test4', 'test5', 'test6'), 'test7' => 'test8'));
        $dict = Dictionary::wrap($data);

        $iterations = 0;
        $exp = count($data);
        foreach ($dict as $key => $val)
        {
            ++$iterations;
            if ($val instanceof Dictionary)
                $val = $val->getAll();
                
            $this->assertEquals($data[$key], $val);
        }

        $this->assertEquals($iterations, $exp);
    }

    /**
     * @covers WASP\Dictionary::count
     */
    public function testCountable()
    {
        $data = array(1, 2, 3, 'test' => 'data', 'test2' => array('test3' => array('test4', 'test5', 'test6'), 'test7' => 'test8'));
        $dict = new Dictionary($data);

        $this->assertEquals(count($dict), count($data));
    }

    /**
     * @covers WASP\Dictionary::offsetSet
     * @covers WASP\Dictionary::offsetGet
     */
    public function testNumericIndex()
    {
        $dict = new Dictionary();
        $dict[] = 'val1';
        $dict[] = 'val2';
        $dict[] = 'val3';

        $this->assertEquals(count($dict), 3);
        $this->assertEquals($dict[0], 'val1');
        $this->assertEquals($dict[2], 'val3');
        $this->assertEquals($dict[1], 'val2');
    }

    /** 
     * @covers WASP\Dictionary::getSection
     * @covers WASP\Dictionary::getArray
     * @covers WASP\Dictionary::toArray
     */
    public function testSections()
    {
        $data = array('test' => 4, 'test2' => array('test1', 'test2', 'test'));
        $dict = Dictionary::wrap($data);

        $this->assertEquals($data['test2'], $dict['test2']->toArray());
        $this->assertEquals($data['test2'], $dict->getArray('test2'));
        $this->assertEquals($data['test2'], $dict->getSection('test2')->toArray());

        $extracted = $dict->getSection('test');
        $extracted = $extracted->toArray();
        $this->assertEquals(array($data['test']), $extracted);
    }

    /** 
     * @covers WASP\Dictionary::addAll
     * @covers WASP\Dictionary::getArray
     * @covers WASP\Dictionary::toArray
     */
    public function testAddAll()
    {
        $data = array('a' => 1, 'b' => 2, 'c' => 3);
        $data2 = array('b' => 5, 'g' => 10, 'h' => 20, 'i' => 30);

        $dict = new Dictionary($data);
        $dict->addAll($data2);

        $this->assertEquals($dict->get('a'), 1);
        $this->assertEquals($dict->get('b'), 5);
        $this->assertEquals($dict->get('c'), 3);
        $this->assertEquals($dict->get('g'), 10);
        $this->assertEquals($dict->get('h'), 20);
        $this->assertEquals($dict->get('i'), 30);

        $obj = new \StdClass;
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Invalid value to merge");
        $dict->addAll($obj);
    }

    /**
     * @covers WASP\Dictionary::toArray
     */
    public function testToArray()
    {
        $data = array('a' => 1, 'b' => 2);
        $dict = new Dictionary($data);

        $data2 = $dict->toArray();
        $data2['c'] = 3;
        $this->assertEmpty($dict->get('c'));
    }

    /**
     * @covers WASP\Dictionary::pop
     * @covers WASP\Dictionary::push
     * @covers WASP\Dictionary::append
     */
    public function testStack()
    {
        $dict = new Dictionary();

        $dict->push('z');
        $dict->append('g');
        $dict->push(0.5);

        $this->assertEquals(0.5, $dict->pop());
        $this->assertEquals('g', $dict->pop());
        $this->assertEquals('z', $dict->pop());
        $this->assertEquals(null, $dict->pop());
    }

    /**
     * @covers WASP\Dictionary::push
     * @covers WASP\Dictionary::shift
     * @covers WASP\Dictionary::unshift
     * @covers WASP\Dictionary::prepend
     */
    public function testQueue()
    {
        $dict = new Dictionary();

        $dict->push('z');
        $dict->push('g');
        $dict->push(0.5);

        $this->assertEquals('z', $dict->shift());
        $this->assertEquals('g', $dict->shift());
        $this->assertEquals(0.5, $dict->shift());
        $this->assertEquals(null, $dict->shift());

        $dict->unshift('z');
        $dict->prepend('g');
        $dict->unshift(0.5);

        $this->assertEquals(0.5, $dict->shift());
        $this->assertEquals('g', $dict->shift());
        $this->assertEquals('z', $dict->shift());
        $this->assertEquals(null, $dict->shift());
    }

    /**
     * @covers WASP\Dictionary::append
     * @covers WASP\Dictionary::prepend
     * @covers WASP\Dictionary::clear
     */
    public function testClear()
    {
        $dict = new Dictionary();

        $dict->append('z');
        $dict->prepend('g');
        $dict->append(0.5);

        $c = $dict->getAll();
        $this->assertEquals(['g', 'z', 0.5], $c);

        $dict->clear();
        $c = $dict->getAll();
        $this->assertEmpty($c);
    }

    /**
     * @covers WASP\Dictionary::dget
     * @covers WASP\Dictionary::get
     */
    public function testDefVal()
    {
        $dict = new Dictionary();

        $val = $dict->getBool('a', 'b', 'c', new DefVal(true));
        $this->assertEquals($val, true);

        $val = $dict->getBool('a', 'b', 'c', new DefVal(false));
        $this->assertEquals($val, false);

        $this->expectException(\OutOfRangeException::class);
        $this->expectExceptionMessage('Key a.b.c does not exist');
        $val = $dict->getBool('a', 'b', 'c');
    }

    public function testSort()
    {
        $dict = new Dictionary();

        $dict['z'] = 'a';
        $dict['y'] = 'b';
        $dict['x'] = 'c';

        $dict->asort();
        $this->assertEquals(
            array(
                'z' => 'a',
                'y' => 'b',
                'x' => 'c'
            ),
            $dict->getAll()
        );

        $dict->ksort();
        $this->assertEquals(
            array(
                'x' => 'c',
                'y' => 'b',
                'z' => 'a'
            ),
            $dict->getAll()
        );
        
        $dict->clear();
        $dict[] = 'img12.png';
        $dict[] = 'img10.png';
        $dict[] = 'IMG11.png';
        $dict[] = 'img2.png';
        $dict[] = 'img1.png';

        $dict->asort();
        $this->assertEquals('IMG11.png', $dict->shift());
        $this->assertEquals('img1.png', $dict->shift());
        $this->assertEquals('img10.png', $dict->shift());
        $this->assertEquals('img12.png', $dict->shift());
        $this->assertEquals('img2.png', $dict->shift());

        $dict->clear();
        $dict[] = 'img12.png';
        $dict[] = 'img10.png';
        $dict[] = 'IMG11.png';
        $dict[] = 'img2.png';
        $dict[] = 'img1.png';
        $dict->natsort();
        $this->assertEquals('IMG11.png', $dict->shift());
        $this->assertEquals('img1.png', $dict->shift());
        $this->assertEquals('img2.png', $dict->shift());
        $this->assertEquals('img10.png', $dict->shift());
        $this->assertEquals('img12.png', $dict->shift());

        $dict->clear();
        $dict[] = 'img12.png';
        $dict[] = 'img10.png';
        $dict[] = 'IMG11.png';
        $dict[] = 'img2.png';
        $dict[] = 'img1.png';
        $dict->natcasesort();
        $this->assertEquals('img1.png', $dict->shift());
        $this->assertEquals('img2.png', $dict->shift());
        $this->assertEquals('img10.png', $dict->shift());
        $this->assertEquals('IMG11.png', $dict->shift());
        $this->assertEquals('img12.png', $dict->shift());

        $dict->clear();
        $dict['a'] = '100';
        $dict['b'] = '20';
        $dict['c'] = '30';

        $dict->uksort(function ($l, $r) { return -strcmp($l, $r); });
        $dict->rewind();
        $this->assertEquals('c', $dict->key());
        $this->assertEquals('30', $dict->current());
        $dict->next();
        $this->assertEquals('b', $dict->key());
        $this->assertEquals('20', $dict->current());
        $dict->next();
        $this->assertEquals('a', $dict->key());
        $this->assertEquals('100', $dict->current());

        $dict->uasort(function ($l, $r) { return -strnatcmp($l, $r); });
        $dict->rewind();
        $this->assertEquals('a', $dict->key());
        $this->assertEquals('100', $dict->current());
        $dict->next();
        $this->assertEquals('c', $dict->key());
        $this->assertEquals('30', $dict->current());
        $dict->next();
        $this->assertEquals('b', $dict->key());
        $this->assertEquals('20', $dict->current());
    }


    /**
     * @covers WASP\Dictionary::jsonSerialize
     */
    public function testJsonSerialize()
    {
        $data = array('a' => 1, 'b' => 2);
        $dict = new Dictionary($data);

        $json = json_encode($dict);
        $json_orig = json_encode($data);
        $this->assertEquals($json, $json_orig);
    }

    /**
     * @covers WASP\Dictionary::serialize
     * @covers WASP\Dictionary::unserialize
     */
    public function testPHPSerialize()
    {
        $data = array('a' => 1, 'b' => 2);
        $dict = new Dictionary($data);

        $ser = serialize($dict);
        $dict2 = unserialize($ser);

        $this->assertEquals($dict->getAll(), $dict2->getAll());
    }
}
