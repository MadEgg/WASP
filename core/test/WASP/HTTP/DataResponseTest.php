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

namespace WASP\Http;

use PHPUnit\Framework\TestCase;
use WASP\Dictionary;

/**
 * @covers WASP\Http\DataResponse
 */
final class DataResponseTest extends TestCase
{
    public function testCreateWithArray()
    {
        $a = new DataResponse(array('a' => 'b'));

        $dict = $a->getDictionary();
        $this->assertEquals("b", $dict['a']);
    }

    public function testCreateWithDictionary()
    {
        $dict = new Dictionary(['a' => 'b']);
        $a = new DataResponse($dict);

        $dict2 = $a->getDictionary();
        $this->assertEquals($dict2, $dict);
    }

    public function testGetMimeTypes()
    {
        $a = new DataResponse(array(1, 2, 3));
        $actual = $a->getMimeTypes();

        $this->assertContains('application/json', $actual);
        $this->assertContains('application/xml', $actual);
        $this->assertContains('text/html', $actual);
        $this->assertContains('text/plain', $actual);
    }

    public function testOutput()
    {
        $a = new DataResponse(array(1, 2, 3));
        
        ob_start();
        $a->output('application/json');
        $contents = ob_get_contents();
        ob_end_clean();

        $actual = json_decode($contents);
        $expected = [1, 2, 3];
        $this->assertEquals($expected, $actual);
    }

    public function testOutputInvalidType()
    {
        $a = new DataResponse(array(1, 2, 3));
        
        ob_start();
        $a->output('application/foobar');
        $actual = ob_get_contents();
        ob_end_clean();

        $expected = "0 = '1'\n1 = '2'\n2 = '3'\n";
        $this->assertEquals($expected, $actual);
    }
}
