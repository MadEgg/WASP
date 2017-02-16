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

use WASP\Debug\LoggerAwareStaticTrait;
use WASP\Autoload\Resolve;
use WASP\Dictionary;
use WASP\Site;
use WASP\VirtualHost;
use WASP\Cache;
use WASP\TerminateRequest;
use WASP\RedirectRequest;

use WASP\Http\Cookie;
use Throwable;

/**
 * Request encapsulates a HTTP request, containing all data transferrred to the
 * script by the client and the webserver.
 *
 * It will dispatch the request to the correct app by resolving the route in
 * the configured paths.
 */
class Request
{
    use LoggerAwareStaticTrait;

    /** The accepted types by CLI scripts */
    public static $CLI_MIME = array(
        'text/plain' => 1.0,
        'text/html' => 0.9,
        'application/json' => 0.8,
        'application/xml' => 0.7,
        '*/*' => 0.5
    );

    /** If the error handler was already set */
    private static $error_handler_set = false;

    /** The current instance of the Request object */
    private static $current_request = null;

    /** The default language used for responses */
    private static $default_language = 'en';

    /** The time at which the request was constructed */
    private $start_time;

    /** The server variables */
    public $server;

    /** The hostname used for the request */
    public $host;

    /** The full request URL */
    public $url;

    /** The selected app path, based on the route */
    public $app;

    /** The route specified on the URL */
    public $route;
    
    /** The query parameters specified in the URL */
    public $query;

    /** The protocol / scheme used to access the script */
    public $protocol;

    /** If https was used */
    public $secure;
    
    /** Whether the request was made using XmlHttpRequest */
    public $ajax;

    /** The GET parameters specified as query in the URL */
    public $get;
    
    /** The arguments POST-ed to the script */
    public $post;

    /** The arguments after the selected route */
    public $url_args;

    /** The storage for cookies sent by the client */
    public $cookie;
    
    /** The request method used for the current request: GET, POST etc */
    public $method;
    
    /** Accepted response types indicated by client */
	public $accept = array();

    /** Session storage */
    public $session;

    /** The IP address of the client */
    public $remote_ip;

    /** The hostname of the client */
    public $remote_host;

    /** The language the response should use */
    public $language;

    /** The configured sites */
    public $sites = array();

    /** The selected VirtualHost for this request */
    public $vhost = null;

    /** The response builder */
    private $response_builder = null;


    /*** 
     * Create the request based on the request data provided by webserver and client
     *
     * @param array $get The GET parameters
     * @param array $post The POST parameters
     * @param array $cookie The COOKIE parameters
     * @param array $server The SERVER parameters
     * @param Dictionary $config The site configuration
     */
    public function __construct(array &$get, array &$post, array &$cookie, array &$server, Dictionary $config, DateTime $start = new DateTime())
    {
        $this->start = $start;
        $this->get = new Dictionary($get);
        $this->post = new Dictionary($post);
        $this->cookie = new Dictionary($cookie);
        $this->server = new Dictionary($server);
        $this->config = $config;

        self::$current_request = $this;
        $this->response_builder = new ResponseBuilder($this);

        $this->method = $this->server->get('REQUEST_METHOD');
        $this->url = new URL($this->server->get('REQUEST_URI'));
        $this->ajax = 
            $this->server->get('HTTP_X_REQUESTED_WITH') === 'xmlhttprequest' ||
            $this->get->has('ajax') || 
            $this->post->has('ajax');

        $this->remote_ip = $this->server->get('REMOTE_ADDR');
        $this->remote_host = !empty($this->remote_ip) ? gethostbyaddr($this->remote_ip) : null;
        $this->accept = self::parseAccept($this->server->dget('HTTP_ACCEPT', ''));

        // Determine the proper VirtualHost
        $cfg = $this->config->getSection('site');
        $this->sites = Site::setupSites($cfg);
        $vhost = self::findVirtualHost($this->url, $this->sites);
        if ($vhost === null)
        {
            $result = $this->handleUnknownHost($this->url, $this->sites, $cfg);
            
            // Handle according to the outcome
            if ($result === null)
            {
                throw new Error(404, "Not found: " . $this->url);
            }
            elseif ($result instanceof URL)
            {
                throw new RedirectRequest($result, 301);
            }
            elseif ($result instanceof VirtualHost)
            {
                $vhost = $result;
                $site = $vhost->getSite();
                if (isset($this->sites[$site->getName()]))
                    $this->sites[$site->getName()] = $site;
            }
            else
                throw \RuntimeException("Unexpected response from handleUnknownWebsite");
        }
        $this->vhost = $vhost;

        // Start the session
        $this->session = new Session($this->vhost, $this->config);

        // Resolve the application to start
        $path = $this->vhost->getPath($this->url);
        $resolved = Resolve::app($path);
        if ($resolved !== null)
        {
            $this->route = $resolved['route'];
            $this->app = $resolved['path'];
            $this->url_args = new Dictionary($resolved['remainder']);
        }
        else
        {
            $this->route = null;
            $this->app = null;
            $this->url_args = new Dictionary();
        }
    }

    /**
     * @return DateTime The start of the script
     */
    public function getStartTime()
    {
        return $this->start;
    }

    /**
     * @return WASP\Http\ResponseBuilder The response builder that will produce
     *                                   the final response to the client
     */
    public function getResponseBuilder()
    {
        return $this->response_builder;
    }

    /**
     * Run the selected application
     * @throws WASP\Http\Response Contains the output for the request
     */
    public function dispatch()
    {
        if ($this->route === null)
            throw new HttpError(404, 'Could not resolve ' . $this->url);

        $app = new AppRunner($this, $this->app);
        $app->execute();
    }

    /**
     * Check if the mime-type is accepted by the configured list
     * @param string $mime The mime type to match against the list of accepted
     * mime types
     * @return boolean True if the type is accepted, false if it is not
     */
    public function isAccepted($mime)
    {
        if (empty($this->accept))
            return true;

        foreach ($this->accept as $type => $priority)
        {
            if (strpos($type, "*") !== false)
            {
                $regexp = "/" . str_replace("WC", ".*", preg_quote(str_replace("*", "WC", $type), "/")) . "/i";
                if (preg_match($regexp, $mime))
                    return $priority;
            }
            elseif (strtolower($mime) === strtolower($type))
                return $priority;
        }
        return false;
    }

    /**
     * Select the best response type to return from a list of available types.
     * @param array $types The types that the script is willing to return
     * @return string The mime type preferred by the client
     * 
     * @return string The mime-type of the preferred response type. Null if
     *                none of them are accepted
     */
    public function getBestResponseType(array $types)
    {
        $best_priority = null;
        $best_type = null;

        // Auto-fill mime-types when running from CLI.
        if (self::cli() && empty($this->accept))
            $this->accept = self::$CLI_MIME;

        foreach ($types as $type)
        {
            $priority = $this->isAccepted($type);
            if ($priority === false)
                continue;

            if ($best_priority === null || $priority > $best_priority)
            {
                $best_priority = $priority;
                $best_type = $type;
            }
        }

        return $best_type;
    }

    /**
     * From the list of availble responses, output the one that's preferred by
     * the client.  While it is of course possible to prepare all outputs
     * directly, a more efficient method is to provide a list of objects with
     * a __tostring() method that generates the response on demand.
     *
     * @param array $available A list of mime => output types.
     */
    public function outputBestResponseType(array $available)
    {
        $types = array_keys($available);
        $type = $this->getBestResponseType($types);
        
        if (!headers_sent())
            // @codeCoverageIgnoreStart
            header("Content-type: " . $type);
            // @codeCoverageIgnoreEnd
        echo $available[$type];
    }

    /**
     * Set up a real HTTP Session or a persisted CLI session
     */
    public function setupSession()
    {
        $domain = $this->vhost->getHost()->host;

        if (!self::CLI())
        {
            // @codeCoverageIgnoreStart
            if (session_status() === PHP_SESSION_DISABLED)
                throw new Error(500, "Sesssions are disabled");

            if (session_status() === PHP_SESSION_ACTIVE)
                throw new Error(500, "Repeated session initialization");

            $lifetime = $this->config->dget('cookie', 'lifetime', '30D');
            if (WASP\is_int_val($lifetime))
                $lifetime = $lifetime . 'S';

            $path = '/';
            $domain = (!empty($sub) && $sub !== "www") ? $sub . "." . $domain : $domain;
            $secure = $this->secure;
            $httponly = $this->config->dget('cookie', 'httponly', true) == true;
            $session_name = (string)$this->config->dget('cookie', 'prefix', 'cms_') . str_replace(".", "_", $domain);

            session_set_cookie_params($lifetime, $path, $domain, $secure, $httponly);
            session_name($session_name);
            session_start();

            // Make sure the cookie expiry is updated every time.
            $cookie = new Cookie(session_name(), session_id());
            $cookie->setExpiresIn('P' . $lifetime);
            setcookie(session_name(), session_id(), time() + $lifetime);
            // @codeCoverageIgnoreEnd
        }
        else
        {
            // Simulate a CLI-session by creating a cached array in $_SESSION
            $this->session_cache = new Cache('cli-session');
            $GLOBALS['_SESSION'] = $this->session_cache->get();
        }

        $this->session = new Dictionary($GLOBALS['_SESSION']);
        if ($this->session->has('language'))
            $this->language = $this->session->get('language');
    }

    /**
     * Return the current Request object
     */
    public static function current()
    {
        return self::$current_request;
    }

    /**
     * Parse the HTTP Accept headers into an array of Type => Priority pairs.
     *
     * @param string $accept The accept header to parse
     * @return The parsed accept list
     */
    public static function parseAccept(string $accept)
    {
        if (empty($accept))
            $accept = "text/html";

		$accept = explode(",", $accept);
        $accepted = array();
		foreach ($accept as $type)
		{
			if (preg_match("/^([^;]+);q=([\d.]+)$/", $type, $matches))
			{
				$type = $matches[1];
				$prio = (float)$matches[2];
			}
			else
				$prio = 1.0;

			$accepted[$type] = $prio;
		}

        return $accepted;
    }

    /**
     * Find the VirtualHost matching the provided URL.
     * @param URL $url The URL to match
     * @param array $sites A list of Site objects from which the correct
     *                     VirtualHost should be extracted.
     * @return VirtualHost The correct VirtualHost. Null if not found.
     */
    public static function findVirtualHost(URL $url, array $sites)
    {
        foreach ($sites as $site)
        {
            $vhost = $site->match($url);
            if ($vhost !== null)
                return $vhost;
        }
        return null;
    }

    /**
     * Determine what to do when a request was made to an unknown host.
     * 
     * The default configuration is IGNORE, which means that a new vhost will be
     * generated on the fly and attached to the site of the closest matching VirtualHost.
     * If no site is configured either, a new Site named 'defaul't is created and the new
     * VirtualHost is attached to that site. This makes configuration non-required for 
     * simple sites with one site and one hostname.
     * 
     * @param URL $url The URL that was requested
     * @param array $sites The configured sites
     * @param Dictionary $cfg The configuration to get the policy from
     * @return mixed One of:
     *               * null: if the policy is to error out on unknown hosts
     *               * URI: if the policy is to redirect to the closest matching host
     *               * VirtualHost: if the policy is to ignore / accept unknown hosts
     */
    public static function handleUnknownHost(URL $url, array $sites, Dictionary $cfg)
    {
        // Determine behaviour on unknown host
        $on_unknown = strtoupper($cfg->dget('unknown_host_policy', "IGNORE"));
        $best_matching = self::findBestMatching($url, $sites);

        if ($on_unknown === "ERROR" || ($best_matching === null && $on_unknown === "REDIRECT"))
            return null;

        if ($on_unknown === "REDIRECT")
        {
            $redir = $best_matching->URL($url->getPath);
            return $redir;
        }
        // Generate a proper VirtualHost on the fly
        $url = new URL($url);
        $url->setPath('/')->set('query', null)->set('fragment', null);
        $vhost = new VirtualHost($url, self::$default_language);

        // Add the new virtualhost to a site.
        if ($best_matching === null)
        {
            // If no site has been defined, create a new one
            $site = new Site();
            $site->addVirtualHost($vhost);
        }
        else
            $best_matching->getSite()->addVirtualHost($vhost);

        return $vhost;
    }

    /**
     * Find the best matching VirtualHost. In case the URL used does not
     * match any defined VirtualHost, this function will find the VirtualHost
     * that matches the URL as close as possible, in an attempt to guess at
     * which information the visitor is interested.
     *
     * @param URL $url The URL that was requested
     * @param array $sites The configured sites
     * @return VirtualHost The best matching VirtualHost in text similarity.
     */ 
    public static function findBestMatching(URL $url, array $sites)
    {
        $vhosts = array();
        foreach ($sites as $site)
            foreach ($site->getVirtualHosts() as $vhost)
                $vhosts[] = $vhost;

        // Remove query and fragments from the URL in use
        $my_url = new URL($url);
        $my_url->set('query', null)->set('fragment', null)->toString();

        // Match the visited URL with all vhosts and calcualte their textual similarity
        $best_percentage = 0;
        $best_idx = null;
        foreach ($vhosts as $idx => $vhost)
        {
            $host = $vhost->getHost()->toString();
            similar_text($my_url, $host, $percentage);
            if ($best_idx === null || $percentage > $best_percentage)
            {
                $best_idx = $idx;
                $best_percentage = $percentage;
            }
        }
        
        // Return the best match, or null if none was found.
        if ($best_idx === null)
            return null;
        return $vhosts[$best_idx];
    }

    /**
     * @return boolean True when the script is run from CLI, false if run from webserver
     */
    public static function cli()
    {
        return PHP_SAPI === "cli";
    }
}

// @codeCoverageIgnoreStart
Request::setLogger();
// @codeCoverageIgnoreEnd
