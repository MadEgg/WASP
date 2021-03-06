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

namespace WASP;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use WASP\Http\StringResponse;

/**
 * @covers WASP\AssetManager
 */
final class AssetManagerTest extends TestCase
{
    private $devlogger;

    public function setUp()
    {
        $this->devlogger = new Debug\DevLogger(LogLevel::DEBUG);
        $logger = Debug\Logger::getLogger(AssetManager::class);
        $logger->addLogHandler($this->devlogger);
    }

    public function tearDown()
    {
        $logger = Debug\Logger::getLogger(AssetManager::class);
        $logger->removeLogHandlers();
    }

    /**
     * @covers WASP\AssetManager::__construct
     * @covers WASP\AssetManager::addScript
     * @covers WASP\AssetManager::addCSS
     * @covers WASP\AssetManager::injectScript
     * @covers WASP\AssetManager::injectCSS
     */
    public function testAssets()
    {
        $mgr = new AssetManager(new MockAssetRequest);

        $mgr->addScript('test1.min.js');
        $mgr->addScript('test1.js');
        $mgr->addScript('test1');

        $mgr->addScript('test2.js');
        $mgr->addScript('test2.min.js');
        $mgr->addScript('test2');

        $mgr->addScript('test3');
        $mgr->addScript('test3.js');
        $mgr->addScript('test3.min.js');

        $mgr->addCSS('test1.min.css');
        $mgr->addCSS('test1.css');
        $mgr->addCSS('test1');

        $mgr->addCSS('test2.css');
        $mgr->addCSS('test2');
        $mgr->addCSS('test2.min.css');

        $mgr->addCSS('test3');
        $mgr->addCSS('test3.css');
        $mgr->addCSS('test3.min.css');

        $this->assertEquals('#WASP-JAVASCRIPT#', $mgr->injectScript());
        $this->assertEquals('#WASP-CSS#', $mgr->injectCSS());

        $mgr->setMinified(false);
        $this->assertFalse($mgr->getMinified());
        $urls = $mgr->resolveAssets($mgr->getScripts(), 'js');

        $url_list = array();
        foreach ($urls as $u)
            $url_list[] = $u['url'];

        $this->assertEquals(
            array(
                '/assets/js/test1.min.js',
                '/assets/js/test2.js',
                '/assets/js/test3.js'
            ),
            $url_list
        );

        $mgr->setMinified(true);
        $this->assertTrue($mgr->getMinified());
        $urls = $mgr->resolveAssets($mgr->getScripts(), 'js');

        $url_list = array();
        foreach ($urls as $u)
            $url_list[] = $u['url'];

        $this->assertEquals(
            array(
                '/assets/js/test1.min.js',
                '/assets/js/test2.js',
                '/assets/js/test3.min.js'
            ),
            $url_list
        );

        $mgr->setMinified(true);
        $urls = $mgr->resolveAssets($mgr->getCSS(), 'css');

        $url_list = array();
        foreach ($urls as $u)
            $url_list[] = $u['url'];

        $this->assertEquals(
            array(
                '/assets/css/test1.min.css',
                '/assets/css/test2.css',
                '/assets/css/test3.min.css'
            ),
            $url_list
        );

        $mgr->setMinified(false);
        $urls = $mgr->resolveAssets($mgr->getCSS(), 'css');

        $url_list = array();
        foreach ($urls as $u)
            $url_list[] = $u['url'];

        $this->assertEquals(
            array(
                '/assets/css/test1.min.css',
                '/assets/css/test2.css',
                '/assets/css/test3.css'
            ),
            $url_list
        );
    }

    public function testInlineJSArrayValue()
    {
        $mgr = new AssetManager(new MockAssetRequest);

        $val = array('my' => 'json', 'var' => 3);
        $mgr->addVariable('test', $val);
        $rules = $mgr->getVariables();
        $this->assertEquals($val, $rules['test']);
    }

    public function testInlineJSScalarValue()
    {
        $mgr = new AssetManager(new MockAssetRequest);

        $expected = 3.5;
        $mgr->addVariable('test', 3.5);
        $rules = $mgr->getVariables();
        $this->assertEquals($expected, $rules['test']);
    }

    public function testInlineJSDictionary()
    {
        $mgr = new AssetManager(new MockAssetRequest);

        $dict = new Dictionary(array('a' => 3));
        $mgr->addVariable('test', $dict);
        $rules = $mgr->getVariables();
        $expected = $dict->getAll();
        $this->assertEquals($expected, $rules['test']);
    }

    public function testInlineJsArrayLike()
    {
        $mgr = new AssetManager(new MockAssetRequest);

        $dict = new Dictionary(array('a' => 3));
        $mgr->addVariable('test', $dict);
        $rules = $mgr->getVariables();
        $expected = $dict->getAll();
        $this->assertEquals($expected, $rules['test']);
    }

    public function testInlineJsJsonSerializable()
    {
        $mgr = new AssetManager(new MockAssetRequest);

        $obj = new MockAssetMgrJsonSerializable();

        $mgr->addVariable('test', $obj);
        $rules = $mgr->getVariables();
        $expected = array('foo' => 'bar', 'bar' => 'baz');
        $this->assertEquals($expected, $rules['test']);
    }

    public function testInlineJsInvalid()
    {
        $mgr = new AssetManager(new MockAssetRequest);

        $obj = new \StdClass();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid value provided for JS variable test");
        $mgr->addVariable('test', $obj);
    }

    public function testInlineCSS()
    {
        $mgr = new AssetManager(new MockAssetRequest);

        $val = 'body { bg-color: black;}';
        $val2 = 'body { bg-color: black;}';
        $mgr->addStyle($val2);
        $mgr->addStyle($val);

        $styles = $mgr->getStyles();
        $this->assertEquals(array($val2, $val), $styles);
    }

    public function testInvalidJS()
    {
        $mgr = new AssetManager(new MockAssetRequest);
        $mgr->addScript('test4');

        $scripts = $mgr->getScripts();
        $urls = $mgr->resolveAssets($scripts, 'js');

        $url_list = array();
        foreach ($urls as $u)
            $url_list[] = $u['url'];

        $this->assertEquals(array(), $url_list);

        $log = $this->devlogger->getLog();
        $error_found = false;
        foreach ($log as $line)
            $error_found = $error_found || strpos($line, "Requested asset test4 could not be resolved");
        $this->assertTrue($error_found);
    }

    public function testExecuteHook()
    {
        $mgr = new AssetManager(new MockAssetRequest);
        $mgr->addScript('test1');
        $mgr->addScript('test2');
        $mgr->addCSS('test3');
        $mgr->setMinified(true);
        $mgr->setTidy(false);
        $this->assertFalse($mgr->getTidy());

        $resp = new StringResponse("<html><head>" . $mgr->injectCSS() . "</head><body>" . $mgr->injectScript() . "</body></html>", 'text/html');

        $mgr->executeHook($mgr->getRequest(), $resp, 'text/html');

        $op = $resp->getOutput('text/html');

        $urls = $mgr->resolveAssets($mgr->getScripts(), 'js');
        $url_list = array();
        foreach ($urls as $u)
        {
            $idx = strpos($op, $u['url']);
            $this->assertTrue($idx !== false);
        }

        $urls = $mgr->resolveAssets($mgr->getCSS(), 'css');
        $url_list = array();
        foreach ($urls as $u)
        {
            $idx = strpos($op, $u['url']);
            $this->assertTrue($idx !== false);
        }
    }

    public function testExecuteHookTidy()
    {
        // This functionality depends on presence of Tidy extension
        if (!class_exists('Tidy', false))
            return;

        $mgr = new AssetManager(new MockAssetRequest);
        $mgr->setTidy(true);
        $this->assertTrue($mgr->getTidy());

        $resp = new StringResponse("<html></head><body><h1>Foo</html>", 'text/html');

        $mgr->executeHook($mgr->getRequest(), $resp, 'text/html');

        $expected = <<<EOT
<!DOCTYPE html>
<html>
  </head>
    <title></title>
  </head>
  <body>
    <h1>
      Foo
    </h1>
  </body>
</html>
EOT;

        $op = $resp->getOutput('text/html');

        $this->assertEquals($expected, $op);
    }
}

class MockAssetRequest extends Http\Request
{
    public function __construct()
    {
        $this->resolver = new MockAssetResolver();
        $this->vhost = new MockAssetVhost();
        $this->response_builder = new Http\ResponseBuilder($this);
    }
}

class MockAssetResolver extends Resolve\Resolver
{
    public function __construct()
    {}

    public function asset(string $path)
    {
        $min = strpos($path, '.min.') !== false;
        // test1 is only available minified
        if (strpos($path, 'test1') !== false)
            return $min ? 'resolved/' . $path : null;

        // test2 is only available unminified
        if (strpos($path, 'test2') !== false)
            return $min ? null : 'resolved/' . $path;

        // test3 is available minified and unminified
        if (strpos($path, 'test3') !== false)
            return 'resolved/' . $path;

        return null;
    }

    public function template(string $path)
    {
        $res = System::resolver();
        return $res->template($path);
    }
}

class MockAssetVhost extends VirtualHost
{
    public function __construct()
    {
    }

    public function URL($path = '', $current_url = null)
    {
        return $path;
    }
}

class MockAssetMgrJsonSerializable implements \JSONSerializable
{
    public function jsonSerialize()
    {
        return array('foo' => 'bar', 'bar' => 'baz');
    }

}
