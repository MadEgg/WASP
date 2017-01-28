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

use WASP\Request;

class Redirection
{
    public static function redirect($url, $status_code = 302, $timeout = false)
    {
        http_response_code($status_code);
        if ($timeout)
        {
            header("Refresh:$timeout; url=" . $url);
        }
        else
        {
            header("Location: " . $url);
            die();
        }
    }

    public static function checkRedirect()
    {
        $config = \WASP\Config::getConfig();

        $host = Request::$host;
        $https = Request::$secure;
        
        $needs_redirect = false;
        
        // Whether we prefer www. in front of the domain or not
        $use_www = $config->get('site', 'www', true) == true;

        // Whether we prefer HTTPS
        $use_ssl = $config->get('site', 'secure', false) == true;
        
        // Detect the domain in use
        $domain = null;
        $domains = $config->get('site', 'url', $host);
        $domains = explode(";", $domains);
        foreach ($domains as $d)
        {
            if (strpos($host, $d) !== false)
            {
                $domain = $d;
                break;
            }
        }

        // Find out browser preferred language
        $http_lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "";
        $language = substr($http_lang, 0, 2);
        if (empty($language) || ($language !== "nl" && $language !== "en"))
            $language = $config->get('site', 'default_language', 'en');

        if ($domain === null)
        {
            // Unknown domain name - log and possibly redirect
            \Debug\info("Util.Redirection", "Unknown domain name in use: {}", $host);
            $subdomain = null;
            
            if ($config->get('site', 'redirect_unknown'))
            {
                $preferred = reset($domains);
                if ($use_www)
                    $preferred = "www." . $preferred;
                $url = ($use_ssl ? "https://" : "http://") . $preferred . Request::$uri;
                self::redirect($url, 302);
            }
            $domain = $host;
        }
        else
        {
            // Known domain name - see if we need to redirect based on language preference, or www prefix

            // Determine the subdomain in use
            $subdomain = trim(str_replace($domain, "", $host), ".");

            // Detect language based on domain name
            $preferred = reset($domains);
            $site_config = $config->getSection('domainlanguage');
            foreach ($site_config as $key => $domains)
            {
                if (!substr($key, 0, 4) == "url_")
                    continue;

                $lang = substr($key, 4);
                $domains = explode(";", $domains);
                if (in_array($domain, $domains))
                {
                    $language = $lang;
                    $preferred = reset($domains);
                    break;
                }
            }

            if ($domain !== $preferred)
            {
                $url = ($use_ssl ? "https://" : "http://") . ($use_www ? "www." : "") . $preferred . Request::$uri;
                self::redirect($url, 302);
            }

            if ($subdomain === "www" && !$use_www)
            {
                $url = ($use_ssl ? "https://" : "http://") . $preferred . Request::$uri;
                self::redirect($url, 302);
            }

            if ($subdomain === "" && $use_www)
            {
                $url = ($use_ssl ? "https://" : "http://") . "www." . $preferred . Request::$uri;
                self::redirect($url, 302);
            }

            if ($use_ssl && !Request::$secure)
            {
                $url = "https://" . ($use_www ? "www." : "") . $preferred . Request::$uri;
                self::redirect($url, 301);
            }
        }

        // All is well
        if ($domain !== null)
            \Debug\debug("Util.Redirection", "Detected subdomain '{}' and domain '{}'", $subdomain, $domain);

        \Debug\debug("Util.Redirection", "Setting language to '{}'", $language);
        Request::$language = $language;
        Request::$domain = $domain;
        Request::$subdomain = $subdomain;
    }
}

?>
