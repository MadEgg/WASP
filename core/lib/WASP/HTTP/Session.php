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

namespace WASP\HTTP;

use DateTime;
use DateInterval;

use WASP\Util\Dictionary;
use WASP\Util\Date;
use WASP\Util\Functions as WF;
use WASP\Util\ErrorInterceptor;
use WASP\HTTP\Error as HTTPError;

class Session extends Dictionary
{
    /** Session cache object used to persist sessions in CLI sessions */
    private $session_cache = null;
    
    /** The configuration */
    private $config;

    /** Server variables */
    private $server_vars;

    /** The base URL of the session */
    private $url;

    /** The session cookie to be set */
    private $session_cookie = null;

    /** Session lifetime in seconds */
    private $lifetime = 0;

    /** The ID of the session - mainly for tests */
    private $session_id = null;

    /**
     * Create the session based on a VirtualHost and a configuration
     * @param WASP\HTTP\URL $base_url The base path for the session
     * @param WASP\Util\Dictionary $config The configuration for cookie parameters
     */
    public function __construct(URL $base_url, Dictionary $config, Dictionary $server_vars)
    {
        $this->server_vars = $server_vars;
        if ($config->has('cookie', Dictionary::TYPE_ARRAY))
            $this->config = $config->get('cookie');
        else
            $this->config = $config;

        // Make sure the PHP Session config is in order
        $this->setPHPConfig();

        // Calculate the lifetime and expiry date
        $lifetime = $this->config->dget('lifetime', '30D');
        if (WF::is_int_val($lifetime))
            $lifetime = 'T' . $lifetime . 'S';
        $lifetime = new DateInterval('P' . $lifetime);

        // Determine the correct expiry date
        $now = new DateTime();
        $expire = $now->add($lifetime);

        // Store the amount of seconds
        $this->lifetime = $expire->getTimestamp() - $now->getTimestamp();

        // HTTPOnly should basically always be set, but allow override nonetheless
        $httponly = WF::parse_bool($this->config->dget('httponly', true));

        $this->url = new URL($base_url);
        $session_name = (string)$this->config->dget('prefix', 'wasp_') . str_replace(".", "_", $this->url->host);

        $this->session_cookie = new Cookie($session_name, "");
        $this->session_cookie
            ->setExpires($expire)
            ->setURL($this->url)
            ->setHTTPOnly($httponly);
    }

    /**
     * Set PHP session configuration to safe defaults.
     * Also disable cookies and headers on CLI
     */
    protected function setPHPConfig()
    {
        ini_set('session.use_strict_mode', 0);
        ini_set('session.use_strans_sid', 0);

        if (PHP_SAPI === "cli")
        {
            // On CLI we can't send headers, so don't even try to do so.
            ini_set('session.use_cookies', 0);
            session_cache_limiter("");
        }
    }

    /**
     * Actually start the session
     */
    public function start()
    {
        if (PHP_SAPI === "cli")
        {
            $this->startCLISession();
        }
        else
        {
            // @codeCoverageIgnoreStart
            $this->startHTTPSession();
            // @codeCoverageIgnoreEnd
        }

        if (!$this->has('session_mgmt', 'start_time'))
            $this->set('session_mgmt', 'start_time', time());
    }

    /**
     * Provide a CLI-session by creating a cached array in $_SESSION
     */
    public function startCLISession()
    {
        if (PHP_SAPI === "CLI" && (!defined('WASP_TEST') || WASP_TEST === 0)) return;
        $this->session_cache = new Cache('cli-session');
        $ref = &$this->session_cache->get();

        $GLOBALS['_SESSION'] = &$ref;

        // Make sure the session variables are available through this object
        $this->values = &$ref;
        $this->set('CLI', true);
    }

    /**
     * Change the session ID, useful for tests. Disallowed in normal operation.
     *
     * @codeCoverageIgnore
     */
    public function setSessionID(string $session_id)
    {
        if (!defined('WASP_TEST') || WASP_TEST === 0)
            throw new \RuntimeException("Cannot change the session ID");

        $this->session_id = $session_id;
    }

    /**
     * @return string The current session ID.
     */
    public function getSessionID()
    {
        return $this->session_id;
    }

    /**
     * @return string The name of the session
     */
    public function getSessionName()
    {
        return $this->session_cookie->getName();
    }

    /** 
     * Set up a HTTP session using cookies and the PHP session machinery
     */
    public function startHTTPSession()
    {
        if (session_status() === PHP_SESSION_DISABLED)
            throw new HTTPError(500, "Sesssions are disabled");

        if (session_status() === PHP_SESSION_ACTIVE)
            throw new HTTPError(500, "Repeated session initialization");

        // Now do the PHP session magic to initialize the $_SESSION array
        session_set_cookie_params(
            $this->lifetime, 
            $this->session_cookie->getPath(),
            $this->session_cookie->getDomain(),
            $this->session_cookie->getSecure(),
            $this->session_cookie->getHTTPOnly()
        );
        session_name($this->session_cookie->getName());

        $custom_session_id = defined('WASP_TEST') && WASP_TEST === 1 && $this->session_id !== null;
        if ($custom_session_id)
        {
            ini_set('session.use_strict_mode', 0);
            session_id($this->session_id);
        }
            
        $this->sessionStart();

        if ($custom_session_id)
            ini_set('session.use_strict_mode', 1);

        // Store the session ID
        $this->session_id = session_id();

        // Make sure the session data is accessible through this object
        $this->values = &$_SESSION;

        // Check if session was regenerated
        $this->secureSession();

        // PHPs sessions do not renew the cookie, so it will expire after the
        // period set when the session was first created. We want to postpone
        // the session expiry at every request, so force a cookie to be sent.
        // As the session_id is available after the session started, we need to
        // update the cookie that was generated in the constructor.
        $this->session_cookie->setValue(session_id());
    }

    /**
     * Secure the session
     * 
     * This method checks if the session was destroyed, and if so, if a redirect to a new
     * session should be done. The new session ID is stored and will be sent to the client iff:
     *
     * - Less than 1 minute has passed since the previous session was destroyed
     * - The client has the same user agent and IP-address as when the session was destroyed
     *
     * If this is not the case, a new session is started and sent to the client. This method
     * also stores the current User Agent and IP address to the session.
     */
    private function secureSession()
    {
        $expired = false;
        if ($this->has('session_mgmt', 'destroyed'))
        {
            $when = $this->get('session_mgmt', 'destroyed');
            $ua = $this->get('session_mgmt', 'last_ua');
            $ip = $this->get('session_mgmt', 'last_ip');

            $now = time();
            $diff = $now - $when;

            if ($diff > Date::SECONDS_IN_MINUTE)
            {
                // The session exists, but it was expired more than 1 minute ago,
                // so its ready to be deleted.
                $expired = true;
            }
            elseif ($ua === $this->server_vars['HTTP_USER_AGENT'] && $ip === $this->server_vars['REMOTE_ADDR'])
            {
                // If UA and IP match, we can redirect to the new sesssion
                // within 1 minute to avoid session loss on bad connections.
                $new_session = $this->get('session_mgmt', 'new_session_id');
                if (!empty($new_session))
                {
                    session_commit(); 
                    ini_set('session.use_strict_mode', 0);
                    session_id($new_session);
                    $this->sessionStart();
                    ini_set('session.use_strict_mode', 1);
                    $this->session_id = session_id();
                }
                else
                {
                    // The old session does not contain redirect information,
                    // so it was closed, not changed. Destroy it now.
                    $expired = true;
                }
            }
            else
            {
                // This seems like a hi-jack attempt, make sure to destroy the old session
                $expired = true;
            }
        }

        if ($expired)
        {
            // Shut down expired session completely
            $this->clear();
            $this->resetID();
        }

        // Check if it's time to regenerate the session ID
        if ($this->has('session_mgmt', 'start_time'))
        {
            $start = $this->getInt('session_mgmt', 'start_time');

            $now = time();
            $elapsed = $now - $start;
            $interval = Date::SECONDS_IN_DAY * 5;
            if ($elapsed > $interval)
                $this->resetID();
        }
        else
            $this->set('session_mgmt', 'start_time', time());

        // Store the current user agent and IP address to prevent session hijacking
        $this->set('session_mgmt', 'last_ip', $this->server_vars['REMOTE_ADDR']);
        $this->set('session_mgmt', 'last_ua', $this->server_vars['HTTP_USER_AGENT']);
    }

    /** 
     * Should be called when the session ID should be changed, for example
     * after logging in or out.
     * @return WASP\HTTP\Session Provides fluent interface
     */
    public function resetID()
    {
        if (session_status() === PHP_SESSION_ACTIVE)
        {
            $this->set('session_mgmt', 'destroyed', time());
            $auth = $this->get('authentication');
            if ($auth)
                unset($this['authentication']);

            $new_session_id = self::create_new_id();
            $this->set('session_mgmt', 'new_session_id', $new_session_id);
            session_commit();
            
            ini_set('session.use_strict_mode', 0);
            session_id($new_session_id);

            $this->sessionStart();
            ini_set('session.use_strict_mode', 1);
            $this->session_id = session_id();
            $this->values = &$_SESSION;
            $this->session_cookie->setValue($new_session_id);
            
            if ($this->has('session_mgmt', 'destroyed'))
                $this->set('session_mgmt', 'destroyed', null);

            if ($auth)
                $this['authentication'] = $auth;

            // Store the start time of the new session
            $this->set('session_mgmt', 'start_time', time());
        }
    }

    /** 
     * Wrap the session_start so that we can catch ErrorExceptions that occur
     * when the headers have been sent. This happens mostly on CLI during tests,
     * If in the web, it's a real error.
     *
     * @codeCoverageIgnore
     */
    private function sessionStart()
    {
        $interceptor = new ErrorInterceptor('session_start');
        $interceptor->registerError(E_WARNING, 'Cannot send session');
        $interceptor->registerError(E_WARNING, 'Cannot send session');

        $interceptor->execute();

        $errors = $interceptor->getInterceptedErrors();
        foreach ($errors as $error)
            \WASP\Log\notice("WASP.Util.Session", "Error sending session cookie: {exception}", ['exception' => $error]);
    }

    /**
     * Helper function. PHP 7.1 introduces session_create_id function. This
     * is used in PHP 7.1 but a fallback using random_bytes is used on
     * PHP 7.0.
     *
     * @param string $prefix A prefix to prepend to the session ID
     * @return string A hexadecimal session ID
     */
    private static function create_new_id(string $prefix = "WASP")
    {
        if (version_compare(PHP_VERSION, '7.1.0') > 0)
            return session_create_id($prefix);
        return $prefix . bin2hex(random_bytes(16));
    }

    public function close()
    {
        if (session_status() === PHP_SESSION_ACTIVE)
        {
            session_commit();
            $this->values = array();
            $this->session_id = null;
        }
        return $this;
    }

    /** 
     * Should be called when the session should be cleared and destroyed.
     * @return WASP\HTTP\Session Provides fluent interface
     */
    public function destroy()
    {
        $this->clear();
        if (session_status() === PHP_SESSION_ACTIVE)
            session_commit();
        return $this;
    }

    /** 
     * Get the session cookie to be sent to the client
     * @return WASP\HTTP\Cookie The session cookie
     */
    public function getCookie()
    {
        return !empty($this->session_cookie->getValue()) ? $this->session_cookie : null;
    }
}
