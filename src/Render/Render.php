<?php
/**
 * (c) 2013 Bossanova PHP Framework 5
 * https://bossanova.uk/php-framework
 *
 * @category PHP
 * @package  Bossanova
 * @author   Paul Hodel <paul.hodel@gmail.com>
 * @license  The MIT License (MIT)
 * @link     https://bossanova.uk/php-framework
 *
 * Render Library
 */
namespace bossanova\Render;

use bossanova\Config\Config;
use bossanova\Auth\Auth;
use bossanova\Error\Error;
use bossanova\Layout\Layout;

class Render
{
    /**
     * Class Instances
     *
     * @var $debug boolean
     */
    public static $classInstance = [];

    /**
     * Page not found
     *
     * @var $notFound boolean
     */
    public static $notFound = false;

    /**
     * Route based on the URL
     *
     * @var $urlParam array
     */
    public static $urlParam = [];

    /**
     * Bossanova main configuration it is populated by the method router
     *
     * @var $configuration array();
     */
    public static $configuration = [
        'template_area' => null,
        'template_path' => null,
        'template_render' => 1,
        'template_recursive' => null,
        'template_meta' => [
            'title' => '',
            'author' => '',
            'keywords' => '',
            'description' => '',
        ],
        'module_name' => null,
        'module_controller' => null,
        'view' => null,
        'view_render' => 1,
        'extra_config' => [],
        'message' => null,
    ];

    /**
     * Manage all necessary for the render
     *
     * @return void
     */
    public static function run($params = null, $plugins = null)
    {
        // Defined which is the request
        self::request($params);

        // Configuration based on the requested route
        self::config();

        // Execute the modules
        self::execute($plugins);
    }

    /**
     * Explode de URL request to define the route and create the main global database instance connection
     *
     * @return void
     */
    public static function request($params = null)
    {
        if (! $params) {
            $params = [];

            // Arguments
            if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI']) {
                $params = $_SERVER['REQUEST_URI'];
            } else if (isset($_SERVER['argv']) && isset($_SERVER['argv'][1]) && $_SERVER['argv'][1]) {
                $params = $_SERVER['argv'][1];
            }

            // First param can't be null
            if (substr($params, 0, 1) == '/') {
                $params = substr($params, 1);
            }
        }

        // Lower
        $params = strtolower($params);

        // Remove query string
        $requestUri = explode('?', $params);

        // Get the route URL
        if ($requestUri[0]) {
            // Windows compatibility
            $requestUri[0] = str_replace('\\', '/', str_replace("'", "", $requestUri[0]));

            // Escape the request
            $str = trim($requestUri[0]);
            if (get_magic_quotes_gpc()) {
                $str = stripslashes($str);
            }
            $str = htmlentities($str);
            $search = array("\\", "\0", "\n", "\r", "\x1a", "'", '"');
            $replace = array("", "", "", "", "", "", "");
            $str = str_replace($search, $replace, $str);

            // Explode route based the URL
            self::$urlParam = explode("/", $str);

            // Check if last item is empty
            $index = count(self::$urlParam) - 1;

            // If yes redirect to previous path
            if (self::$urlParam[$index] == '') {
                unset(self::$urlParam[$index]);
            }
        }
    }

    /**
     * Based on the request populate configuration array
     *
     * @return void
     */
    public static function config()
    {
        // If is an ajax call don't show the main template. This can be overwrite by the module.
        if (self::isAjax()) {
            // Do not render the template for an ajax request
            self::$configuration['template_render'] = 0;
            // Only load the view for GET requests (Don't load for POST, PUT, DELETE)
            if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] != "GET") {
                self::$configuration['view_render'] = 0;
            }
        }

        // Current URL request
        $route = str_replace('-', '_', implode('/', self::$urlParam));

        // Available routes
        $routes = Config::get('routes');

        // Global config.inc.php definitions
        foreach ($routes as $k => $v) {
            $routes[strtolower(str_replace('-', '_', $k))] = $v;
        }

        // Order routes
        ksort($routes);

        // Looking for a global configuration for the URL
        if (isset($routes) && count($routes)) {
            if (isset($routes[$route])) {
                // Set the configuration
                self::setConfiguration($routes[$route]);
            } else {
                // Could not find any global configuration, search for any parent URL the is recursive
                if (count(self::$urlParam) && self::$urlParam[count(self::$urlParam) - 1] != 'login') {
                    $url = '';
                    foreach (self::$urlParam as $k => $v) {
                        // Loading configuration
                        if (isset($routes[$url])) {
                            // Set the configuration
                            self::setConfiguration($routes[$url]);
                        }

                        if ($url) {
                            $url .= '/';
                        }

                        $url .= str_replace('-', '_', $v);
                    }
                }
            }
        }

        // Template elements
        $elements = Config::get('elements');

        // Find persistent elements
        $persistentElem = isset($elements['']) ? $elements[''] : [];
        // Could not find any global configuration, search for any parent URL the is recursive

        if (count(self::$urlParam)) {
            $url = '';

            foreach (self::$urlParam as $k => $v) {
                // Requested URL piece from less relevant recursivelly to most relevant
                if ($url) {
                    $url .= '/';
                }
                $url .= $v;

                // Loading configuration
                if (isset($elements[$url])) {
                    $persistentElem = $elements[$url];
                }
            }
        }

        if (count($persistentElem)) {
            foreach ($persistentElem as $k => $v) {
                self::$configuration['extra_config'][] = (object) $v;
            }
        }

        if (isset(self::$urlParam[0]) && self::$urlParam[0]) {
            $module_name = str_replace('-', '_', self::$urlParam[0]);
            $module_name = ucfirst(strtolower($module_name));
        } else {
            if (file_exists("modules/Index/Index.php")) {
                $module_name = 'Index';
            }
        }

        // Looking for modules and elements from a module such as views, controllers, methods
        if (isset($module_name) && $module_name) {
            // Module exists TODO: improve speed by cache the the IO checkings
            if (file_exists("modules/$module_name/$module_name.php") ||
                file_exists("modules/$module_name/$module_name.class.php")) {
                // Module name
                self::$configuration['module_name'] = $module_name;
            }

            if (self::$configuration['module_name']) {
                // Controller information: check if the call referes to a existing module
                if (isset(self::$urlParam[1])) {
                    // Check configuration for the login page
                    if (! self::isAjax()) {
                        if (self::$urlParam[count(self::$urlParam) - 1] == 'login') {
                            if (! self::$configuration['template_path']) {
                                self::$configuration['template_path'] = "default/login.html";
                                self::$configuration['template_area'] = "content";
                                self::$configuration['template_render'] = 1;
                            }
                        }
                    }

                    // Verify if second param is a controllers
                    $controller_name = ucfirst(strtolower(str_replace('-', '_', self::$urlParam[1])));
                    if (file_exists("modules/$module_name/controllers/$controller_name.php") ||
                        file_exists("modules/$module_name/controllers/$controller_name.class.php")) {
                            // Controller found
                            self::$configuration['module_controller'] = $controller_name;
                        }
                } else {
                    // Check configuration for the login page
                    if (isset(self::$urlParam[0]) && self::$urlParam[0] == 'login') {
                        if (! self::isAjax()) {
                            if (! self::$configuration['template_path']) {
                                self::$configuration['template_path'] = "default/login.html";
                                self::$configuration['template_area'] = "content";
                                self::$configuration['template_render'] = 1;
                            }
                        }
                    }
                }

                // View information
                if (defined('BOSSANOVA_AUTOLOAD_VIEW') && BOSSANOVA_AUTOLOAD_VIEW) {
                    if (count(self::$urlParam) <= 2) {
                        if (isset(self::$urlParam[1]) && ! is_numeric(self::$urlParam[1])) {
                            $view_name = self::$urlParam[1];
                        } else if (isset(self::$urlParam[0])) {
                            $view_name = self::$urlParam[0];
                        }

                        if (isset($view_name) && $view_name) {
                            $view_name = strtolower(str_replace('-', '_', $view_name));

                            // Check if view exist
                            if (file_exists("modules/$module_name/views/$view_name.html")) {
                                self::$configuration['view'] = $view_name;
                            }
                        }
                    }
                }
            } else {
                // Default for page not found
                self::$notFound = 1;
            }
        }
    }

    public static function execute($plugins) {
        // Content container
        $content = '';

        // Authentication
        $auth = new Auth();

        // Check the user permission to this section
        $restrictedRoute = $auth->isRestricted(Render::$urlParam);

        // Re-check the user permission to this section
        if ($restrictedRoute !== false && ! $auth->isAuthorized($restrictedRoute)) {
            // If not login send user to the login page
            if ($auth->getUser()) {
                // Default template for errors
                if (defined("TEMPLATE_ERROR")) {
                    self::$configuration['template_path'] = TEMPLATE_ERROR;
                }

                // User message
                if (self::isAjax()) {
                    $content = json_encode([
                        'error'=>'1',
                        'message' => '^^[Permission denied]^^'
                    ]);
                } else {
                    header("HTTP/1.1 403 Forbidden");
                    $content = "^^[Permission denied]^^";
                }
            } else {
                if (self::isAjax()) {
                    $content = json_encode([
                        'error'=>'1',
                        'message' => '^^[User not authenticated]^^'
                    ]);
                } else {
                    // Redirect the user to the login page
                    $module = isset(self::$urlParam[0]) && self::$urlParam[0] ? self::$urlParam[0] : '';
                    if ($module) {
                        $module .= '/';
                    }
                    // Redirect to the login page
                    $url = self::getLink($module . 'login');
                    header("Location: $url");
                    exit;
                }
            }
        } else {
            if (is_callable($plugins)) {
                $plugins();
            }

            // Executing module
            if (self::$configuration['module_name']) {
                // Load the content from the main module
                $content = self::getContent();
            } elseif (self::$notFound == 1) {
                // Default template for errors
                if (defined("TEMPLATE_ERROR")) {
                    self::$configuration['template_path'] = TEMPLATE_ERROR;
                }

                // User message
                if (self::isAjax()) {
                    $content = json_encode([
                        'error'=>'1',
                        'message' => '^^[Page not found]^^'
                    ]);
                } else {
                    header("HTTP/1.1 404 Not found");
                    $content = "^^[Page not found]^^";
                }
            }
        }

        // Loading template
        if (self::$configuration['template_path'] && self::$configuration['template_render'] == 1) {
            // Get extra contents
            $contents = self::getExtraContents($content);

            // Template path
            $templatePath = "templates/" . self::$configuration['template_path'];

            // Render layout
            $template = new Layout();
            $template->title = self::$configuration['template_meta']['title'];
            $template->author = self::$configuration['template_meta']['author'];
            $template->keywords = self::$configuration['template_meta']['keywords'];
            $template->description = self::$configuration['template_meta']['description'];

            // Message
            if (isset($_COOKIE['bossanova_message']) && $_COOKIE['bossanova_message']) {
                $message = $_COOKIE['bossanova_message'];
                header("Set-Cookie: bossanova_message=; path=/; SameSite=Lax; expires=0;");
            } else if (self::$configuration['message']) {
                $message = self::$configuration['message'];
            } else {
                $message = null;
            }

            $content = $template->render($templatePath, $contents, $message);

        }

        // Showing content
        if (isset($content) && $content) {
            // Show content
            echo $content;
        } else {
            // Pending message
            if (isset(self::$configuration['message']) && self::$configuration['message']) {
                // Automatic message
                echo self::$configuration['message'];
            }
        }
    }

    /**
     * Get the main module content
     *
     * return string $content
     */
    public static function getContent()
    {
        // Loading module
        $module_name = ucfirst(strtolower(self::$configuration['module_name']));

        // Default method name
        $method_name = "__default";

        try {
            if (self::$configuration['module_controller']) {
                $controller_name = ucfirst(strtolower(self::$configuration['module_controller']));
                $name = "\\modules\\$module_name\\controllers\\$controller_name";
                if (! isset(self::$classInstance[$name])) {
                    self::$classInstance[$name] = new $name();
                }

                // Other method name
                if (isset(self::$urlParam[2]) && self::$urlParam[2]) {
                    $m = str_replace('-', '_', self::$urlParam[2]);
                    if (method_exists(self::$classInstance[$name], $m)) {
                        $method_name = $m;
                    }
                }
            } else {
                // Creating an instance of the module that matches this call
                $name = "\\modules\\$module_name\\$module_name";
                if (! isset(self::$classInstance[$name])) {
                    self::$classInstance[$name] = new $name();
                }

                // Other method name
                if (isset(self::$urlParam[1]) && self::$urlParam[1]) {
                    $m = str_replace('-', '_', self::$urlParam[1]);
                    if (method_exists(self::$classInstance[$name], $m)) {
                        $method_name = $m;
                    }
                }
            }

            // If there is any method call it.
            if ($method_name) {
                ob_start();
                $content = self::$classInstance[$name]->$method_name();
                if (is_array($content)) {
                    $content = json_encode($content);
                }
                $content .= ob_get_clean();

                if (is_array($content)) {
                    $content = json_encode($content);
                }
            }

            // Automatic load view
            if (self::$configuration['view_render'] == 1 && self::$configuration['view']) {
                $view = self::$classInstance[$name]->loadView(self::$configuration['view'], $module_name);

                if (isset($view)) {
                    $content = $view . $content;
                }
            }
        } catch (\Exception $e) {
            Error::handler("Error loading main module files.", $e);
        }

        return $content;
    }

    /**
     * Get any extra content
     *
     * return array $contents
     */
    public static function getExtraContents($content)
    {
        // Array of contents
        $contents = [];

        // Default area if not defined
        if (! self::$configuration['template_area']) {
            self::$configuration['template_area'] = 'content';
        }

        // Create the default area if exist content for it
        if ($content) {
            $contents[self::$configuration['template_area']] = $content;
        }

        // Check for any configured route
        if (self::$configuration['extra_config']) {
            // Extra configuration
            $extra_config = self::$configuration['extra_config'];

            foreach ($extra_config as $k => $v) {
                // Make sure string is in correct format
                if (isset($extra_config[$k]->module_name)) {
                    $extra_config[$k]->module_name = ucfirst(strtolower($extra_config[$k]->module_name));
                }
                if (isset($extra_config[$k]->controller_name)) {
                    $extra_config[$k]->controller_name = ucfirst(strtolower($extra_config[$k]->controller_name));
                }

                // Area
                $area = $extra_config[$k]->template_area;

                // If nothing yet loaded in the template area
                if (! isset($contents[$area])) {
                    $contents[$area] = '';
                }

                // Extra modules to be called
                if (isset($extra_config[$k]->module_name) && $extra_config[$k]->module_name) {
                    $module_name = $extra_config[$k]->module_name;

                    // Check information about the module call
                    if (isset($extra_config[$k]->controller_name) && $extra_config[$k]->controller_name) {
                        // It is a controlle?
                        $cn = "modules\\{$module_name}\\controllers\\" . $extra_config[$k]->controller_name;
                        if (class_exists($cn)) {
                            if (! isset(self::$classInstance[$cn])) {
                                self::$classInstance[$cn] = new $cn();
                            }
                        }
                    } else {
                        // It is a method inside the module
                        $cn = "modules\\{$module_name}\\" . $extra_config[$k]->module_name;
                        if (class_exists($cn)) {
                            if (! isset(self::$classInstance[$cn])) {
                                self::$classInstance[$cn] = new $cn();
                            }
                        }
                    }

                    // Classname exists
                    if (isset(self::$classInstance[$cn])) {
                        // Check if there is OB active for translations
                        if (count(ob_list_handlers()) > 1) {
                            // Loadind methods content including translation
                            ob_start();
                            $content = self::$classInstance[$cn]->{$extra_config[$k]->method_name}();
                            $content .= ob_get_clean();
                        } else {
                            // Loading methods content
                            $content = self::$classInstance[$cn]->{$extra_config[$k]->method_name}();
                        }

                        // Place content in the correct area
                        if (! isset( $contents[$extra_config[$k]->template_area])) {
                            $contents[$extra_config[$k]->template_area] = '';
                        }

                        // If there is content returned
                        if ($content) {
                            $contents[$extra_config[$k]->template_area] .= $content;
                        }
                    }
                }
            }
        }

        return $contents;
    }


    /**
     * Set the configuration based on config or in a record save in the route table
     *
     * @param  array $row Configuration to be loaded
     * @return void
     */
    public static function setConfiguration($row)
    {
        if (isset($row['extra_config'])) {
            $row['extra_config'] = json_decode($row['extra_config']);
        }

        // Avoid notices
        foreach ($row as $k => $v) {
            if ($v != '') {
                self::$configuration[$k] = $v;
            }
        }
    }

    /**
     * Get the configuration loaded
     *
     * @return array $configuration Bossanova loaded configuration
     */
    public static function getConfiguration()
    {
        return self::$configuration;
    }

    /**
     * Is ajax?
     *
     * @return bool
     */
    public static function isAjax()
    {
        $ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strpos(strtolower($_SERVER['HTTP_X_REQUESTED_WITH']), 'http') !== false) ||
            (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'json') !== false) ||
            (isset($_SERVER['CONTENT_TYPE']) && strpos(strtolower($_SERVER['CONTENT_TYPE']), 'json') !== false);

        return $ajax;
    }

    /**
     * Return domain name
     *
     * @return string $domain
     */
    public static function getDomain()
    {
        $domain = isset($_SERVER["HTTP_HOST"]) ? $_SERVER["HTTP_HOST"] : $_SERVER['SERVER_NAME'];

        return $domain;
    }

    /**
     * Return full url
     *
     * @return string
     */
    public static function getUrl()
    {
        return $_SERVER["HTTP_HOST"] . $_SERVER["SCRIPT_NAME"];
    }

    /**
     * Return full url
     *
     * @return string
     */
    public static function getLink($page = null)
    {
        $scheme = 'http';

        if (isset($_SERVER['REQUEST_SCHEME'])) {
            $scheme = $_SERVER['REQUEST_SCHEME'];
        }

        if (!isset($_SERVER["HTTP_HOST"])) {
            $_SERVER["HTTP_HOST"] = '';
        }

        $script = $_SERVER["HTTP_HOST"] . $_SERVER["SCRIPT_NAME"];
        $url = $scheme . '://' . str_replace('index.php', '', $script);

        if (substr($url, - 1, 1) != '/') {
            $url .= '/';
        }

        $url .= $page;

        return $url;
    }

    /**
     * Print debug information
     *
     * @return string
     */
    public static function debug()
    {
        echo "<h1>Bossanova Framework</h1>";
        echo '<pre>';
        echo '1 . Request<br>' . implode('/', self::$urlParam) . '<br><br>';
        echo '2 . Configuration loaded based on the request<br>';
        print_r(self::$configuration);
        print_r(debug_backtrace());
    }
}
