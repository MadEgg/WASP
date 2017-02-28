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

namespace WASP
{
    use Throwable;
    use WASP\Resolve\Resolver;
    use WASP\Debug\LoggerAwareStaticTrait;
    use WASP\Http\Request;
    use WASP\Http\Error as HttpError;
    use WASP\Http\Response;
    use WASP\Http\StringResponse;

    /**
     * The Templating class implements the WASP template system. Templates are
     * regular PHP files, of course without any controller or model activities.
     *
     * Template executes these scripts in a class scope. The controller can provide
     * variables to the template by calling Template::assign, and these variables
     * will be avaibable in the same name in the template.
     * 
     * The template can call template methods using $this->func, and some helper functions
     * are available. To include sub templates, you should use:
     *
     * include tpl('path/to/my/template');
     *
     * This will resolve the template all WASP modules. HTML escaping can be done by using
     * the txt function, which delegates to htmlentities.
     */
    class Template
    {
        use LoggerAwareStaticTrait;

        /** The request this template is in response to */
        protected $request;

        /** The path configuration of WASP */
        protected $path;

        /** The arguments provided for the template */
        protected $arguments = array();

        /** The title for the page. Will be autogenerated if left empty */
        protected $title = null;

        /** The path to the template */
        protected $template_path;

        /** The resolver that is used to resolve templates */
        protected $resolver = null;

        /** The asset manager manages scripts and styles */
        protected $asset_manager;

        /** The mime-type generated by the template */
        public $mime = "text/html";

        /**
         * Construct a template using a request.
         *
         * Since templates can be easily reused, a template is made available
         * in the WASP system, which you can obtain using System::template().
         * Templates rendered this way template will share all assigned
         * variables.
         * @param WASP\Http\Request $reques The request object
         */
        public function __construct(Request $request)
        {
            $this->request = $request;
            $this->path = $request->path;
            $this->setRequest($request);
        }

        /**
         * Set the template file to render later. If the file exists, it will
         * be used unmodified. If it doesn't exist, the resolver will be used
         * to find it in the WASP modules.
         *
         * @param string $template The template file
         * @param string $mime The mime-type generated by the template
         * @return WASP\Template Provides fluent interface
         */
        public function setTemplate(string $template, string $mime = "text/html")
        {
            if (file_exists($template))
                $tpl = realpath($template);
            else
                $tpl = $this->resolve($template);
            
            $this->template_path = $tpl;
            $this->setMimeType($mime);
            return $this;
        }

        /**
         * @return string The full path to the selected template
         */
        public function getTemplate()
        {
            return $this->template_path;
        }

        /**
         * Set the asset manager used by this object
         * @param WASP\AssetManager $mgr The asset manager to set
         * @return WASP\Template Provides fluent interface
         */
        public function setAssetManager(AssetManager $mgr)
        {
            $this->asset_manager = $mgr;
            return $this;
        }

        /**
         * @return WASP\AssetManager The asset manager managing JS and CSS
         */
        public function getAssetManager()
        {
            return $this->asset_manager;
        }

        /**
         * $return WASP\Http\Request The associated request object
         */
        public function setRequest(Request $request)
        {
            $this->request = $request;
            $this->setAssetManager($request->getResponseBuilder()->getAssetManager());
            $this->resolver = $request->getResolver();
        }

        /**
         * $return WASP\Http\Request The associated request object
         */
        public function getRequest()
        {
            return $this->request;
        }

        /**
         * Assign a variable to the template.
         * @param string $name The name of the variable
         * @param mixed $value What the variable contains
         * @return WASP\Template Provides fluent interface
         */
        public function assign(string $name, $value)
        {
            $this->arguments[$name] = $value;
            return $this;
        }

        /**
         * Set the title of the current page.
         * @param string $title The title
         * @return WASP\Template Provides fluent interface
         */
        public function setTitle(string $title)
        {
            $this->title = $title;
            return $this;
        }

        /**
         * Get the title for the current page. This is the title
         * set by Template::setTitle. If that was not done, a 
         * title is automatically generated using the currently selected
         * site's name, a - and the current route. For example:
         * Examplesite - /home
         *
         * @return string The title
         */
        public function title()
        {
            if ($this->title === null)
            {
                if ($this->request->vhost !== null)
                {
                    $site = $this->request->vhost->getSite();
                    $name = $site->getName();
                }
                else
                    $name = "Default";

                $route = $this->request->route;
                if ($name !== "default")
                    $this->title = $name . " - " . $route;
                else
                    $this->title = $this->request->route;
            }

            return $this->title;
        }

        /**
         * Set the mime type generated by the template
         * @param string $mime The mime-type generated
         * @return WASP\Template Provides fluent interface
         */
        public function setMimeType(string $mime)
        {
            $this->mime = $mime;
            return $this;
        }

        /**
         * @return string The mime type rendered by the template
         */
        public function getMimeType()
        {
            return $this->mime;
        }

        /**
         * Resolve the template identified by $name.
         * @param string $name The template to resolve
         * @return string The path to the template
         * @throws WASP\Http\Error When the template can not be found
         */
        public function resolve($name)
        {
            $path = null;
            if ($this->resolver !== null)
            {
                $path = $this->resolver->template($name);
            }

            if ($path === null)
                throw new HttpError(500, "Template file could not be found: " . $name);

            return $path;
        }

        /**
         * Renders the template. It uses renderReturn to get the return value
         * and throws it so the OutputHandler will catch it.
         *
         * @throws WASP\Http\Response The response to the request
         */
        public function render()
        {
            throw $this->renderReturn();
        }

        /**
         * Renders the template. Any output is buffered using an output buffer and wrapped
         * in a StringResponse object. If the template throws or returns a different type of
         * response, that response is returned instead. Any other exceptions are caught and
         * wrapped in an Internal Server Error exception.
         *
         * All configured variables are assigned before running the template.
         *
         * @return WASP\Http\Response A valid WASP HTTP Response
         */
        public function renderReturn()
        {
            extract($this->arguments);
            $request = $this->request;
            $language = $request->language;
            $config = $request->config;
            $dev = $config === null ? false : $config->get('site', 'dev');
            $cli = Request::CLI();

            try
            {
                ob_start();
                include $this->template_path;
                $output = ob_get_contents();
                $response = new StringResponse($output, $this->mime);
            }
            catch (Response $e)
            {
                $e->addMimeType($this->mime);
                self::$logger->debug("*** Finished processing {0} request to {1} with {2}", [$request->method, $request->url, get_class($e)]);
                return $e; 
            }
            catch (TerminateRequest $e)
            {
                self::$logger->debug("*** Finished processing {0} request to {1} with terminate request", [$request->method, $request->url]);
                return $e;
            }
            catch (Throwable $e)
            {
                self::$logger->error("Template threw exception: {0}", [$e]);
                self::$logger->debug("*** Finished processing {0} request to {1}", [$request->method, $request->url]);
                return new HttpError(500, "Template threw exception", "", $e);
            }
            finally
            {
                ob_end_clean();
            }
            return $response;
        }

        /**
         * Add a JS script to the AssetManager to be included in the response.
         * As the assets are injected into the HTML right before output, this
         * method can be called anywhere throughout the template without
         * influencing the result. Duplicates are also avoided, and based
         * on the setting of the development parameter, either the minified
         * or the unminified version is included, where available.
         *
         * @param string $script The script to add
         * @return WASP\Template Provides fluent interface
         */
        public function addJS(string $script)
        {
            $this->asset_manager->addScript($script);
            return $this;
        }

        /**
         * Add a JS variable to the AssetManager to be included in the response.
         * The variables will be injected inline in the response, using a template.
         * The default template injects them before any other scripts includes.
         *
         * @param string $name The name of the variable
         * @param string $value The value to set. Should be an array or JSONSerializable
         * @return WASP\Template Provides fluent interface
         */
        public function addJSVariable(string $name, $value)
        {
            $this->asset_manager->addVariable($name, $value);
            return $this;
        }

        /**
         * Add an inline CSS style to the AssetManager to be included in the
         * response. As the assets are injected into the HTML right before
         * output, this method can be called anywhere throughout the template
         * without influencing the result. 
         *
         * @param string $style The CSS style definition to include inline
         * @return WASP\Template Provides fluent interface
         */
        public function addStyle(string $style)
        {
            $this->asset_manager->addStyle($style);
            return $this;
        }

        /**
         * Add a CSS stylesheet to the AssetManager to be included in the
         * response.  As the assets are injected into the HTML right before
         * output, this method can be called anywhere throughout the template
         * without influencing the result. Duplicates are also avoided, and
         * based on the setting of the development parameter, either the
         * minified or the unminified version is included, where available.
         *
         * @param string $stylesheet The stylesheet to add
         * @return WASP\Template Provides fluent interface
         */
        public function addCSS(string $stylesheet)
        {
            $this->asset_manager->addCSS($stylesheet);
            return $this;
        }

        /**
         * Returns the placeholder for the javascripts. Should be called on preference
         * in the header or footer, and will be replaced by the AssetManager just
         * before being output.
         *
         * @return string A placeholder for Javascripts
         */
        public function insertJS()
        {
            return $this->asset_manager->injectScript();
        }

        /**
         * Returns the placeholder for the stylesheets. Should be called in the
         * header, and will be replaced by the AssetManager just before being
         * output.
         *
         * @return string A placeholder for stylesheets
         */
        public function insertCSS()
        {
            return $this->asset_manager->injectCSS();
        }

        /**
         * Set the template to an error template matching the specified exception. The template
         * is resolved using the class and the code. The code is only used if it is non-zero.
         * If a template is not available matching the exception, the same procedure it attempted
         * for its parent class until a match is found. The path of the template should match its
         * namespace, so that WASP\IOException will result in a template error/WASP/IOException.php
         * The only exception is the WASP\Http\Error which is used frequently, and therefore should be
         * located as error/HttpError.php, optionally specialized as error/HttpError404.php and so on.
         * 
         * @param Throwable $exception The exception to find a template for
         * @return WASP\Template Provides fluent interface
         * @throws RuntimeException When no valid template was found
         */
        public function setExceptionTemplate(Throwable $exception)
        {
            $class = get_class($exception);
            $code = $exception->getCode();

            $resolver = $this->resolver;
            $resolved = null;
            while ($class)
            {
                $path = 'error/' . str_replace('\\', '/', $class);
                if ($class === "WASP\\Http\\Error")
                    $path = 'error/HttpError'; 

                if (!empty($code))
                {
                    $resolved = $resolver->template($path . $code);
                    if ($resolved) break;
                }

                $resolved = $resolver->template($path);
                if ($resolved)
                    break;

                $class = get_parent_class($class);
            }

            if (!$resolved) throw new \RuntimeException("Could not find any matching template for " . get_class($exception));
            
            $this->template_path = $resolved;
            return $this;
        }
    }


    // @codeCoverageIgnoreStart
    Template::setLogger();
    // @codeCoverageIgnoreEnd
}

namespace 
{
    function tpl($name)
    {
        $tpl = WASP\System::template()->resolve($name);
        return $tpl;
    }

    function txt($str)
    {
        return htmlentities($str, ENT_HTML5 | ENT_SUBSTITUTE | ENT_QUOTES);
    }

    function URL($path)
    {
        $vhost = WASP\System::request()->vhost;
        return $vhost !== null ? $vhost->URL($path) : $path;
    }
}
