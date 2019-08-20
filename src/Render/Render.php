<?php
/**
 * (c) 2013 Bossanova PHP Framework 4
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

use bossanova\Database\Database;
use bossanova\Auth\Auth;
use bossanova\Error\Error;

class Render
{
    /**
     * Database default instance holder
     *
     * @var $database resource
     */
    public static $database = null;

    /**
     * Route not found
     *
     * @var $debug boolean
     */
    public static $debug = false;

    /**
     * Class Instances
     *
     * @var $debug boolean
     */
    public static $classInstance = [];

    /**
     * View namematch autoload
     *
     * @var $notFound boolean
     */
    public static $autoLoadView = false;

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
        'template_meta' => [],
        'module_name' => null,
        'module_controller' => null,
        'view' => null,
        'view_render' => 1,
        'extra_config' => []
    ];

    /**
     * Explode de URL request to define the route and create the main global database instance connection
     *
     * @return void
     */
    public function __construct($params = null)
    {
        if (! $params) {
            $params = [];

            // Arguments
            if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI']) {
                $params = $_SERVER['REQUEST_URI'];
            } else if (isset($_GET['bossanova']) && $_GET['bossanova']) {
                $params = $_GET['bossanova'];
                unset($_GET['bossanova']);
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

            // Explode route based the URL
            self::$urlParam = explode("/", $this->escape($requestUri[0]));

            // Check if last item is empty
            $index = count(self::$urlParam) - 1;

            // If yes redirect to previous path
            if (self::$urlParam[$index] == '') {
                unset(self::$urlParam[$index]);
            }
        } else {
            // Index module
            if (file_exists("modules/Index/Index.php")) {
                self::$urlParam = [];
                self::$urlParam[0] = 'index';
            }
        }

        // Automatic load view
        if (defined('BOSSANOVA_AUTOLOAD_VIEW')) {
            self::$autoLoadView = BOSSANOVA_AUTOLOAD_VIEW ? true : false;
        }

        // Autoload View
        self::$configuration['view_render'] = self::$autoLoadView;

        // Loading route
        $this->route();

        // Debug mode
        if (self::$debug == true) {
            $this->debugMode();
        }
    }

    /**
     * Render method is the most used method, get all definitions in the configuration loaded by the router
     * and loads files, create instances, loads templates and return the contents to the user.
     *
     * @return void
     */
    public function run($plugins = null)
    {
        // Content container
        $content = '';

        // If is an ajax call don't show the main template. This can be overwrite by the module.
        if (self::isAjax()) {
            self::$configuration['template_render'] = 0;
            // Only load the view for GET requestse (Don't load for POST, PUT, DELETE)
            if ((isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] != "GET") || self::isAjax()) {
                self::$configuration['view_render'] = 0;
            }
        }

        // Check the user permission to this section
        if (self::isRestricted()) {
            if (! isset($_SESSION['user_id'])) {
                // Connect to the database
                if (! isset(self::$database)) {
                    self::$database = Database::getInstance(null, [
                        DB_CONFIG_TYPE,
                        DB_CONFIG_HOST,
                        DB_CONFIG_USER,
                        DB_CONFIG_PASS,
                        DB_CONFIG_NAME
                    ]);
                }

                // Recover the session by the cookie
                $auth = new Auth();
                $auth->sessionRecovery();
            }
        }

        // Re-check the user permission to this section
        if (self::isRestricted()) {
            // If not login send user to the login page
            if (isset($_SESSION['user_id'])) {
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
                    // Keep redirection
                    if (! isset($_SESSION['HTTP_REFERER']) || ! $_SESSION['HTTP_REFERER']) {
                        $_SESSION['HTTP_REFERER'] = '/' . implode("/", Render::$urlParam);
                    }
                    // Redirect to the login page
                    $url = self::getLink($module . 'login');
                    header("Location: $url");
                    exit;
                }
            }
        } else {
            // Plugins
            if (is_callable($plugins)) {
                $plugins();
            }

            // Executing module
            if (self::$configuration['module_name']) {
                // Load the content from the main module
                $content = $this->getContent();
            } elseif (self::$notFound == 1) {
                // Default template for errors
                if (defined("TEMPLATE_ERROR")) {
                    self::$configuration['template_path'] = TEMPLATE_ERROR;
                }

                // User message
                if (self::isAjax()) {
                    $content = json_encode(array('error'=>'1', 'message' => '^^[Page not found]^^'));
                } else {
                    header("HTTP/1.1 404 Not found");
                    $content = "^^[Page not found]^^";
                }
            }
        }

        // Loading template
        if (self::$configuration['template_path'] && self::$configuration['template_render'] == 1) {
            if (file_exists("public/templates/" . self::$configuration['template_path'])) {
                // Get extra contents
                $contents = $this->getContents($content);
                // Loading template layout
                $content = $this->template($contents);
            } else {
                $content = "^^[Template not found]^^ templates/" . self::$configuration['template_path'];
            }
        }

        // Showing content
        if (isset($content) && $content) {
            // Show content
            echo $content;
        } else {
            // Pending message
            if (isset($_SESSION['bossanova_message']) && $_SESSION['bossanova_message']) {
                // Automatic message
                echo $_SESSION['bossanova_message'];
                // Remove message
                unset($_SESSION['bossanova_message']);
            }
        }
    }

    /**
     * Get the main module content
     *
     * return string $content
     */
    private function getContent()
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
                // Check if there is OB active for translations
                if (count(ob_list_handlers()) > 1) {
                    // Loadind methods content including translation
                    ob_start();
                    $content = self::$classInstance[$name]->$method_name();
                    if (is_array($content)) {
                        $content = json_encode($content);
                    }
                    $content .= ob_get_clean();
                } else {
                    // Loading methods content
                    $content = self::$classInstance[$name]->$method_name();
                }

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
    private function getContents($content)
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
     * Process the URL and populate the configuration.
     * Configuration defines which module,
     * controller, view, template and other configurations to be loaded.
     *
     * @return void
     */
    private function route()
    {
        // Current URL request
        $route = str_replace('-', '_', implode('/', self::$urlParam));

        // Available routes
        $routes = [];

        // Global config.inc.php definitions
        if (isset($GLOBALS['route'])) {
            foreach ($GLOBALS['route'] as $k => $v) {
                $routes[strtolower(str_replace('-', '_', $k))] = $v;
            }
        }

        // If database routing is defined
        if (defined('BOSSANOVA_DATABASE_ROUTES') && BOSSANOVA_DATABASE_ROUTES == true) {
            // Load information from database conection
            self::$database = Database::getInstance(null, [
                DB_CONFIG_TYPE,
                DB_CONFIG_HOST,
                DB_CONFIG_USER,
                DB_CONFIG_PASS,
                DB_CONFIG_NAME
            ]);

            if (! self::$database->error) {
                // Search for the URL configuration
                $result = self::$database->table("routes")
                    ->select()
                    ->execute();

                while ($row = self::$database->fetch_assoc($result)) {
                    $routes[strtolower(str_replace('-', '_', $k))] = $row;
                }
            }
        }

        // Order routes
        ksort($routes);

        // Looking for a global configuration for the URL
        if (isset($routes) && count($routes)) {
            if (isset($routes[$route])) {
                // Set the configuration
                $this->setConfiguration($routes[$route]);
            } else {
                // Could not find any global configuration, search for any parent URL the is recursive
                if (count(self::$urlParam) && self::$urlParam[count(self::$urlParam) - 1] != 'login') {
                    $url = '';
                    foreach (self::$urlParam as $k => $v) {
                        // Loading configuration
                        if (isset($routes[$url])) {
                            // Set the configuration
                            $this->setConfiguration($routes[$url]);
                        }

                        if ($url) {
                            $url .= '/';
                        }

                        $url .= str_replace('-', '_', $v);
                    }
                }
            }
        }

        // Find persistent elements
        $persistentElem = isset($GLOBALS['persistent_elements']['']) ? $GLOBALS['persistent_elements'][''] : [];
        // Could not find any global configuration, search for any parent URL the is recursive

        if (count(self::$urlParam)) {
            $url = '';

            foreach (self::$urlParam as $k => $v) {
                // Requested URL piece from less relevant recursivelly to most relevant and match full URL
                if ($url) {
                    $url .= '/';
                }
                $url .= $v;

                // Loading configuration
                if (isset($GLOBALS['persistent_elements'][$url])) {
                    $persistentElem = $GLOBALS['persistent_elements'][$url];
                }
            }
        }

        if (count($persistentElem)) {
            foreach ($persistentElem as $k => $v) {
                self::$configuration['extra_config'][] = (object) $v;
            }
        }

        // Looking for modules and elements from a module such as views, controllers, methods
        if (isset(self::$urlParam[0])) {
            // Module information, check if the call referes to a existing module
            $module_name = ucfirst(strtolower(str_replace('-', '_', self::$urlParam[0])));

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
                    if (self::$urlParam[count(self::$urlParam) - 1] == 'login') {
                        if (! self::$configuration['template_path']) {
                            self::$configuration['template_path'] = "default/login.html";
                            self::$configuration['template_area'] = "content";
                            self::$configuration['template_render'] = 1;
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
                    if (self::$urlParam[0] == 'login') {
                        if (! self::$configuration['template_path']) {
                            self::$configuration['template_path'] = "default/login.html";
                            self::$configuration['template_area'] = "content";
                            self::$configuration['template_render'] = 1;
                        }
                    }
                }

                // View information
                if (count(self::$urlParam) <= 2) {
                    if (isset(self::$urlParam[1]) && ! is_numeric(self::$urlParam[1])) {
                        $view_name = self::$urlParam[1];
                    } else if (isset(self::$urlParam[0])) {
                        $view_name = self::$urlParam[0];
                    }
                    $view_name = strtolower(str_replace('-', '_', $view_name));

                    // Check if view exist
                    if (file_exists("modules/$module_name/views/$view_name.html")) {
                        self::$configuration['view'] = $view_name;
                    }
                }
            } else {
                // Default for page not found
                self::$notFound = 1;
            }
        }
    }

    /**
     * Set the configuration based on config or in a record save in the route table
     *
     * @param  array $row Configuration to be loaded
     * @return void
     */
    public function setConfiguration($row)
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
    public function getConfiguration()
    {
        return self::$configuration;
    }

    /**
     * Loading the HTML layout including the bossanova needs (base href, and javascript in the end)
     *
     * @param  string $content
     * @return string $html
     */
    public function template($contents)
    {
        // Scheme
        $request_scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https:' : 'http:';

        // Template name
        $template_path = self::$configuration['template_path'];

        // Load HTML layoiut
        $html = file_get_contents("public/templates/{$template_path}");

        // Defining baseurl for a correct template images, styling, javascript reference
        $url = defined('BOSSANOVA_BASEURL') ? BOSSANOVA_BASEURL : substr($_SERVER["SCRIPT_NAME"], 0, strrpos($_SERVER["SCRIPT_NAME"], "/"));
        $url = $_SERVER["HTTP_HOST"] . $url;
        $baseurl = explode('/', $template_path);
        array_pop($baseurl);
        $baseurl = implode('/', $baseurl);

        // Page configuration
        $extra = '';

        if (isset(self::$configuration['template_meta']['title'])) {
            $extra .= "\n<title>" . self::$configuration['template_meta']['title'] . "</title>";
        }
        if (isset(self::$configuration['template_meta']['author'])) {
            $value = self::$configuration['template_meta']['author'];
            $extra .= "\n<meta itemprop='author' property='og:author' name='author' content='$value'>";
        }
        if (isset(self::$configuration['template_meta']['keywords'])) {
            $value = self::$configuration['template_meta']['keywords'];
            $extra .= "\n<meta itemprop='keywords' property='og:keywords' name='keywords' content='$value'>";
        }
        if (isset(self::$configuration['template_meta']['description'])) {
            $value = self::$configuration['template_meta']['description'];
            $extra .= "\n<meta itemprop='description' property='og:description' name='description' content='$value'>";
        }
        if (isset(self::$configuration['template_meta']['news_keywords'])) {
            $value = self::$configuration['template_meta']['news_keywords'];
            $extra .= "\n<meta name='news_keywords' content='$value'>";
        }
        if (isset(self::$configuration['template_meta']['title'])) {
            $value = self::$configuration['template_meta']['title'];
            $extra .= "\n<meta itemprop='title' property='og:title' name='title' content='$value'>";
        }

        // Dynamic Tags (TODO: implement a more effient replace)
        $html = preg_replace("<head.*>", "head>\n<base href='$request_scheme//$url/templates/$baseurl/'>$extra", $html, 1);

        // Process message
        if (isset($_SESSION['bossanova_message']) && $_SESSION['bossanova_message']) {
            // Force remove html tag to avoid duplication
            $html = str_replace("</html>", "", $html);

            // Inject message to the frontend
            $html .= "<script>\n";
            $html .= "var bossanova_message = {$_SESSION['bossanova_message']}\n";
            $html .= "</script>\n";
            $html .= "</html>";

            // Remove message
            unset($_SESSION['bossanova_message']);
        }

        // Looking for the template area to insert the content
        if ($contents) {
            $id = '';
            $tag = 0;
            $test = strtolower($html);

            // Is id found?
            $found = 0;

            // Merging HTML
            $merged = $html{0};

            for ($i = 1; $i < strlen($html); $i ++) {
                $merged .= $html{$i};

                // Inside a tag
                if ($tag > 0) {
                    // Inside an id property?
                    if ($tag > 1) {
                        if ($tag == 2) {
                            // Found [=]
                            if ($test{$i} == chr(61)) {
                                $tag = 3;
                            } else {
                                // [space], ["], [']
                                if ($test{$i} != chr(32) && $test{$i} != chr(34) && $test{$i} != chr(39)) {
                                    $tag = 1;
                                }
                            }
                        } else {
                            // Separate any valid id character
                            if ((ord($test{$i}) >= 0x30 && ord($test{$i}) <= 0x39) ||
                                (ord($test{$i}) >= 0x61 && ord($test{$i}) <= 0x7A) ||
                                (ord($test{$i}) == 95) ||
                                (ord($test{$i}) == 45)) {
                                $id .= $test{$i};
                            }

                            // Checking end of the id string
                            if ($id) {
                                // Check for an string to be closed in the next character [>], [space], ["], [']
                                if ($test{$i + 1} == chr(62) ||
                                    $test{$i + 1} == chr(32) ||
                                    $test{$i + 1} == chr(34) ||
                                    $test{$i + 1} == chr(39)) {
                                    // Id found mark flag
                                    if (isset($contents[$id])) {
                                        $found = $contents[$id];
                                    }

                                    $id = '';
                                    $tag = 1;
                                }
                            }
                        }
                    } elseif ($test{$i - 1} == chr(105) && $test{$i} == chr(100)) {
                        // id found start testing
                        $tag = 2;
                    }
                }

                // Tag found <
                if ($test{$i - 1} == chr(60)) {
                    $tag = 1;
                }

                // End of a tag >
                if ($test{$i} == chr(62)) {
                    $id = '';
                    $tag = 0;

                    // Inserted content in the correct position
                    if ($found) {
                        $merged .= $found;
                        $found = '';
                    }
                }
            }

            $html = $merged;
        }

        return $html;
    }

    /**
     * This method check if the URL has any defined restriction in the global scope
     *
     * @param  array  $route      Route
     * @return string $restricted First restricted route from the most to less significative argument
     */
    public static function isRestricted(array $urlRoute = null)
    {
        // Get restriction defined in the config.inc.php
        $restriction = [];

        foreach ($GLOBALS['restriction'] AS $k => $v) {
            $restriction[strtolower(str_replace('-', '_', $k))] = $v;
        }

        // Route he trying to access
        $access_route = (isset($urlRoute)) ? $urlRoute : self::$urlParam;

        // Check the access url against the restriction array definition in config.inc.php
        if (count($access_route)) {
            $route = '';

            foreach ($access_route as $k => $v) {
                // Check all route possibilities
                if ($route) {
                    $route .= '/';
                }
                $route .= strtolower(str_replace('-', '_', $v));

                // Restriction exists for this route
                if (isset($restriction[$route])) {
                    $restricted = $route;
                }
            }

            // Always allow login/logout method
            if (count($access_route) < 3) {
                $param = $access_route[count($access_route) - 1];
                if ($param == 'login' || $param == 'logout') {
                    unset($restricted);
                }
            }
        } else {
            if (isset($restriction[''])) {
                $restricted = '';
            }
        }

        // If there is a restriction check the permission, this should be implemented by the login function

        if (isset($restricted)) {
            if (isset($_SESSION['permission'])) {
                // Check if the user has access to the module
                $key = $restricted;

                if (isset($_SESSION['permission'][$key])) {
                    unset($restricted);
                }
            }
        }

        return isset($restricted) ? true : false;
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
    public function debugMode()
    {
        echo "<h1>Bossanova Framework</h1>";
        echo "<p>Debug mode active</p>";
        echo '<pre>';
        echo '1 . Request<br>' . implode('/', self::$urlParam) . '<br><br>';
        echo '2 . Configuration loaded based on the request<br>';
        print_r(self::$configuration);
        print_r($trace = debug_backtrace());
    }

    /**
     * Scape string
     *
     * @param  string
     * @return string
     */
    private function escape($str)
    {
        $str = trim($str);
        if (get_magic_quotes_gpc()) {
            $str = stripslashes($str);
        }
        $str = htmlentities($str);
        $search = array("\\", "\0", "\n", "\r", "\x1a", "'", '"');
        $replace = array("", "", "", "", "", "", "");
        return str_replace($search, $replace, $str);
    }
}
