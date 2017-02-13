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

class URLException extends \RuntimeException
{}

/**
 * URL is a class that parses and modifies URLs. This
 * class is limited to a limited set of schemes - it
 * supports http, https and ftp.
 */
class URL implements \ArrayAccess
{
    private $scheme = null;
    private $port = null;
    private $username = null;
    private $password = null;
    private $host = null;
    private $path = null;
    private $query = null;
    private $fragment = null;

    public function __construct($url = "", $default_scheme = '')
    {
        if (empty($url))
            return;

        if ($url instanceof URL)
            $parts = $url;
        else
            $parts = self::parse($url, $default_scheme);

        foreach ($parts as $field => $value)
            $this->set($field, $value);
    }

    public static function parse(string $url, string $default_scheme = '')
    {
        if (!preg_match('/^(((([a-z]+):)?\/\/)?((([^:]+):([^@]+)@)?([\w\d.-]+))(:([1-9][0-9]*))?)?(\/.*)?$/u', $url, $matches))
            throw new URLException("Invalid URL: " . $url);

        $scheme = !empty($matches[4]) ? $matches[4] : null;
        $user   = !empty($matches[7]) ? $matches[7] : null;
        $pass   = !empty($matches[8]) ? $matches[8] : null;
        $host   = !empty($matches[9]) ? $matches[9] : null;
        $port   = !empty($matches[11]) ? (int)$matches[11] : null;
        $path   = !empty($matches[12]) ? $matches[12] : '/';

        if (empty($scheme))
            $scheme = $default_scheme;

        if (!in_array($scheme, array('http', 'https', 'ftp')))
            throw new URLException("Unsupported scheme: '" . $scheme . "'");

        return array(
            'scheme' => $scheme,
            'username' => $user,
            'password' => $pass,
            'host' => $host,
            'port' => $port,
            'path' => $path
        );
    }

    public function setPath($path)
    {
        $query = null;
        $fragment = null;
        if (preg_match('/^(.*?)(\\?([^#]*))?(#(.*))?$/u', $path, $matches))
        {
            $path = $matches[1];
            $this->query = !empty($matches[3]) ? $matches[3] : null;
            $this->fragment = !empty($matches[5]) ? $matches[5] : null;
        }

        $this->path = $path;
        return $this;
    }

    public function __toString()
    {
        return $this->toString();
    }

    public function toString($idn = false)
    {
        $o = "";
        if (!empty($this->scheme))
            $o .= $this->scheme . "://";
        
        if (!empty($this->username) && !empty($this->password))
            $o .= $this->username . ':' . $this->password . '@';

        // Check for IDN
        $host = $this->host;
        if ($idn && preg_match('/[^\x20-\x7f]/', $host))
            $host = \idn_to_ascii($host);
        $o .= $host;

        if (!empty($this->port))
        {
            if (!(
                ($this->scheme === "http"  && $this->port === 80) ||
                ($this->scheme === "https" && $this->port === 443) || 
                ($this->scheme === "ftp"   && $this->port === 21)
            ))
                $o .= ":" . $this->port;
        }

        $o .= $this->path;
        if (!empty($this->query))
            $o .= '?' . $this->query;
        if (!empty($this->fragment))
            $o .= '#' . $this->fragment;
        return $o;
    }

    public function setHost($hostname)
    {
        if (($ppos = strrpos($hostname, ":")) !== false)
        {
            $port = substr($hostname, $ppos + 1);
            $hostname = substr($hostname, 0, $ppos);
        }

        if (substr($hostname, 0, 4) === "xn--")
            $hostname = \idn_to_utf8($hostname);

        $this->host = strtolower($hostname);
        return $this;
    }

    // ArrayAccess implementation
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value)
    {
        return $this->set($offset, $value);
    }

    public function offsetExists($offset)
    {
        return property_exists($this, $offset);
    }

    public function offsetUnset($offset)
    {
        return $this->set($offset, null);
    }

    public function __get($field)
    {
        return $this->get($field);
    }

    public function __set($field, $value)
    {
        return $this->set($field, $value);
    }

    public function set($field, $value)
    {
        switch ($field)
        {
            case "scheme":
                $this->scheme = strtolower($value);
                return;
            case "port":
                $value = empty($value) ? null : (int)$value;
            case "username":
            case "password":
            case "query":
            case "fragment":
                $this->$field = $value;
                return;
            case "path":
                $this->setPath($value);
                return;
            case "host":
                return $this->setHost($value);
            case "secure":
                return $this->scheme === "https";
        }
        throw new \OutOfRangeException($field);
    }

    public function get($field)
    {
        if (property_exists($this, $field))
            return $this->$field;
        throw new \OutOfRangeException($field);
    }
}