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

namespace WASP\Debug;

use WASP\Http\Request;
use WASP\Http\ResponseHookInterface;
use WASP\Http\Response;
use WASP\Http\DataResponse;
use WASP\Http\StringResponse;

/**
 * Log all output of the current script to memory, and attach a log to the end
 * of the response
 */
class DevLogger implements LogWriterInterface 
{
    /** Minimum logger level */
    private $min_level;

    /** The log storage */
    private $log = array();

    /**
     * Create the logwriter
     * @param string $level The minimum level of messages to store
     */
    public function __construct(string $level)
    {
        $this->min_level = Logger::getLevelNumeric($level);
    }

    /**
     * Log a line to the memory log, if its level is high enough
     * @param string $level The level of the message
     * @param string $message The message
     * @param array $context The variables to fill in the message
     */
    public function write(string $level, $message, array $context);
    {
        $level = Logger::getLevelNumeric($level);
        if ($level < $this->min_level)
            return;

        $message = Logger::fillPlaceholders($message, $context);
        $this->log[] = $message;
    }

    /**
     * Return the collected log lines
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * Attach to the Response to add the log to the end of each HTML and
     * JSON/XML request Do note that this should only be used for development
     * as the log may expose sensitive information to the client.
     * 
     * @param Request $request The request being answerd
     * @param Response $response The response so far
     */
    public function executeHook(Request $request, Response $response)
    {
        $now = new \DateTime();
        $duration = $now->diff($request->getStart());

        if ($response instanceof DataResponse)
            $response->getDictionary()->set('devlog', $this->log);

        if ($response instanceof StringResponse)
        {
            $mime = $response->getMime();
            $buf = fopen('php://memory', 'rw');
            if ($mime === 'text/html')
            {
                fprintf($buf, "<!-- Executed in: %s -->\n", Log::str($duration));
                fprintf($buf, "<!-- App executed: %s -->\n", $request->route);
                foreach ($this->log as $no => $line)
                    fprintf($buf, "<!-- %05d: %s -->\n", $no, htmlentities($line));
            }
            elseif ($mime === 'text/plain')
            {
                fprintf($buf, "<!-- Executed in: %s -->\n", Log::str($duration));
                fprintf($buf, "<!-- App executed: %s -->\n", $request->route);
                foreach ($this->log as $no => $line)
                    fprintf($buf, "// %05d: %s\n", $no, $line);
            }

            fseek($buf, 0);
            $output = fread($buf, 100 * 1024);
            if (!feof($buf))
                $output .= " <!-- TRUNCATED LOG -->\n";
            fclose($buf);
            
            $response->append($output);
        }
    }
}
