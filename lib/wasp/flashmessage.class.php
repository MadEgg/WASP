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

class FlashMessage
{
    const SUCCESS = 1;
    const INFO = 2;
    const WARNING = 3;
    const WARN = 3;
    const ERROR = 4;

    private static $KEY = "WASP_FM";

    public static $types = array(
        self::ERROR => "ERROR",
        self::WARN => "WARNING",
        self::INFO => "INFO",
        self::SUCCESS => "SUCCESS"
    );

    private $msg;
    private $type;
    private $date;

    public function __construct($msg, $type = FlashMessage::INFO)
    {
        if (is_array($msg))
        {
            $this->msg = $msg[0];
            $this->type = $msg[1];
            $this->date = new \DateTime("@" . $msg[2]);
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE)
            throw new \RuntimeException("No session available - cannot store Flash Message");

        $this->msg = $msg;
        $this->type = $type;
        $this->date = new \DateTime();

        if (!isset($_SESSION[self::$KEY]))
            $_SESSION[self::$KEY] = array();

        $_SESSION[self::$KEY][] = array($this->msg, $this->type, $this->date->getTimestamp());
    }

    public function getMessage()
    {
        return $this->msg;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getTypeName()
    {
        return self::$types[$this->type];
    }

    public static function hasNext()
    {
        return !empty($_SESSION[self::$KEY]);
    }

    public static function next()
    {
        if (empty($_SESSION[self::$KEY]))
            return null;

        $next = array_shift($_SESSION[self::$KEY]);
        return new FlashMessage($next);
    }

    public static function count()
    {
        if (empty($_SESSION[self::$KEY]))
            return 0;

        return count($_SESSION[self::$KEY]);
    }
}
