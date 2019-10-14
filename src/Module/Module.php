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
 * Module Library
 */
namespace bossanova\Module;

use bossanova\Auth\Auth;
use bossanova\Render\Render;
use bossanova\Database\Database;
use bossanova\Mail\Mail;
use bossanova\Common\Post;
use bossanova\Common\Request;
use bossanova\Services\Services;

class Module
{
    use Post, Request;

    /**
     * Global authentication instance
     *
     * @var $auth
     */
    public $auth;

    /**
     * Global database instance
     *
     * @var $query
     */
    public $query;

    /**
     * Global sendmail instance
     *
     * @var $mail
     */
    public $mail;

    /**
     * Global data object to be available in the view scope
     *
     * @var $view
     */
    public $view = [];

    /**
     * Keep the requested method
     *
     * @var $requestMethod
     */
    public $requestMethod = 'GET';

    /**
     * Allow native methods - For security reasons is disabled
     *
     * @var $view
     */
    protected $nativeMethods = false;

    /**
     * Connect to the database
     */
    public function __construct()
    {
        $this->query = Database::getInstance(null, array(
            DB_CONFIG_TYPE,
            DB_CONFIG_HOST,
            DB_CONFIG_USER,
            DB_CONFIG_PASS,
            DB_CONFIG_NAME
        ));

        if (defined('BOSSANOVA_NATIVE_METHODS')) {
            $this->nativeMethods = BOSSANOVA_NATIVE_METHODS ? true : false;
        }

        // Keep method
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD']) {
            $this->requestMethod = $_SERVER['REQUEST_METHOD'];
        }
    }

    /**
     * The __default module function can be used for RESTful requests if the
     * module name has the name of the table in the database
     *
     * @return string $json
     */
    public function __default()
    {
    }

    /**
     * Process one ajax request
     * @param Service $service
     * @param integer $id
     * @return array
     */
    public function processRestRequest(Services $service, $id = null)
    {
        if ($this->getRequestMethod() == "POST" || $this->getRequestMethod() == "PUT") {
            if (! $id) {
                $data = $service->insert($this->getPost());
            } else {
                $data = $service->update($id, $this->getPost());
            }
        } else if ($this->getRequestMethod() == "DELETE") {
            $data = $service->delete($id);
        } else {
            if ($id) {
                $data = $service->select($id);
            } else {
                $data = null;
            }
        }

        return $data;
    }

    /**
     * Locale information about the current session
     *
     * @return string json with new inserted id and messages
     */
    public function locale($locale = null)
    {
        // External updates
        if ($this->getParam(1) == 'locale') {
            if ($locale = $this->getParam(2)) {
                if (file_exists("resources/locales/$locale.csv")) {
                    // Update the session language reference
                    $_SESSION['locale'] = $this->getParam(2);

                    // Exclude the current dictionary words
                    unset($_SESSION['dictionary']);

                    // If the user is defined update the user preferences in the table
                    if (isset($_SESSION['user_id']) && $_SESSION['user_id']) {
                        $user = new \models\Users;
                        $user->get($_SESSION['user_id']);
                        $user->user_locale = $_SESSION['locale'];
                        $user->save();
                    }

                    $url = $this->getParam(0);

                    // Redirect to the main page
                    header("Location: /$url");
                }
            } else {
                // Return the current locale in the memory
                return isset($_SESSION['locale']) ? $_SESSION['locale'] : '';
            }
        } else {
            // Internal calls
            if (isset($locale)) {
                // Check if the source file exists
                if (file_exists("resources/locales/$locale.csv")) {
                    // Update the session language reference
                    $_SESSION['locale'] = $locale;

                    // Exclude the current dictionary words
                    unset($_SESSION['dictionary']);
                }
            } else {
                // Return the current locale in the memory
                return isset($_SESSION['locale']) ? $_SESSION['locale'] : '';
            }
        }
    }

    /**
     * This function return the parameters from the URL
     *
     * @param  integer $index number of the param http://domain/0/1/2/3/4/5/6/7...
     * @return mixed
     */
    public function getParam($index = null)
    {
        $value = null;

        // Get the global value defined in the router class
        if (isset($index)) {
            if (isset(Render::$urlParam[$index])) {
                $value = Render::$urlParam[$index];
            }
        } else {
            $value = Render::$urlParam;
        }

        // Return value
        return $value;
    }


    /**
     * This function BF configuration definition
     *
     * @return array $configuration
     */
    public function getConfiguration($option = null)
    {
        // Return value
        if ($option) {
            $configuration = isset(Render::$configuration[$option]) ? Render::$configuration[$option] : null;
        } else {
            $configuration = Render::$configuration;
        }

        return $configuration;
    }

    /**
     * Get current view
     *
     * @return mixed - disabled or the current defined view
     */
    public function getView()
    {
        return Render::$configuration['view_render'] ? Render::$configuration['view'] : false;
    }

    /**
     * Enable disable automatic view load
     *
     * @param integer $mode - set to show the view in case exists
     * @return void
     */
    public function setView($render = false)
    {
        Render::$configuration['view_render'] = ($render) ? 1 : 0;

        if (isset($render) && is_string($render)) {
            Render::$configuration['view'] = $render;
        }
    }

    /**
     * Get current layout
     *
     * @return mixed - disabled or the current defined layout
     */
    public function getLayout()
    {
        return Render::$configuration['template_render'] ? Render::$configuration['template_path'] : false;
    }

    /**
     * Enable disable layout
     *
     * @param integer $mode
     * @return void
     */
    public function setLayout($render = false)
    {
        Render::$configuration['template_render'] = ($render) ? 1 : 0;

        if (isset($render) && is_string($render)) {
            Render::$configuration['template_path'] = $render;
        }
    }

    /**
     * Set Layout Title
     *
     * @param string $author
     * @return void
     */
    public function setTitle($data)
    {
        Render::$configuration['template_meta']['title'] = $data;
    }

    /**
     * Set Layout Author Meta
     *
     * @param string $author
     * @return void
     */
    public function setAuthor($data)
    {
        Render::$configuration['template_meta']['author'] = $data;
    }

    /**
     * Set Layout Description Meta
     *
     * @param string $value
     */
    public function setDescription($data)
    {
        Render::$configuration['template_meta']['description'] = $data;
    }

    /**
     * Set Layout Keywords Meta
     *
     * @param string $value
     * @return void
     */
    public function setKeywords($data)
    {
        Render::$configuration['template_meta']['keywords'] = $data;
    }

    /**
     * Set new content area
     *
     * @param string $value
     * @return void
     */
    public function setContent($data)
    {
        Render::$configuration['extra_config'][] = $data;
    }

    /**
     * Set autoloadview
     *
     * @param string $value
     * @return void
     */
    public function setAutoLoadView($data)
    {
        Render::$autoLoadView = $data ? true : false;
    }

    /**
     * This method reads and return a view content
     *
     * @param  string $moduleName
     * @param  string $viewName
     * @return string $html
     */
    public function loadView($viewName, $moduleName = null)
    {
        // Module
        if (! $moduleName) {
            $moduleName = $this->getParam(0);
        }

        // View full path
        $viewPath = 'modules/' . ucfirst(strtolower($moduleName)) . '/views/' . strtolower($viewName) . '.html';

        // Call view if exists
        if (file_exists($viewPath)) {
            ob_start();
            include_once $viewPath;
            return ob_get_clean();
        }
    }

    /**
     * This method reads and return a view content
     *
     * @param  string $moduleName
     * @param  string $helperName
     * @return string $html
     */
    public function loadHelpers($moduleName, $helperName)
    {
        // View full path
        $viewPath = 'modules/' . ucfirst(strtolower($moduleName)) . '/helpers/' . strtolower($helperName) . '.html';

        // Call view if exists
        if (file_exists($viewPath)) {
            ob_start();
            include_once $viewPath;
            return ob_get_clean();
        }
    }

    /**
     * Return a json format
     *
     * @return string $json
     */
    public function jsonEncode($data)
    {
        // Disable layout for Ajax requests
        $this->setLayout(0);

        // Headers
        if (! isset($_SERVER['argv'])) {
            header("Content-type:text/json");
        }

        // Encode string
        $data = json_encode($data);

        // Return json
        return $data;
    }

    /**
     * Default sendmail function, used by the modules to send used email
     *
     * @return void
     */
    protected function sendmail($to, $subject, $html, $from, $files = null)
    {
        if (! $this->mail) {
            $this->mail = new Mail();
        }

        ob_start();
        $instance = $this->mail->sendmail($to, $subject, $html, $from, $files);
        $result = ob_get_clean();

        return $instance;
    }

    /**
     * Return the full link of the page
     *
     * @return string $link;
     */
    public function getLink($page = null)
    {
        return Render::getLink($page);
    }

    /**
     * Return the full domain name
     *
     * @return string $domain
     */
    public function getDomain()
    {
        return Render::getDomain();
    }

    /**
     * Login actions
     *
     * @return void
     */
    public function login()
    {
        if (! $this->auth) {
            $this->auth = new Auth();
        }

        $data = $this->auth->login();

        // Deal with the authetantion service return
        if (Render::isAjax()) {
            $data = $this->jsonEncode($data);
        } else {
            if (isset($data['url'])) {
                $this->redirect($data['url'], $data);
            } else {
                if ($data) {
                    $this->setMessage($data);
                }
            }
        }

        return $data;
    }

    /**
     * Logout actions
     *
     * @return void
     */
    public function logout()
    {
        if (! $this->auth) {
            $this->auth = new Auth();
        }

        return $this->auth->logout();
    }

    /**
     * Get the registered user_id
     *
     * @return integer $user_id
     */
    public function getIdent()
    {
        if (! $this->auth) {
            $this->auth = new Auth();
        }

        return $this->auth->getIdent();
    }

    /**
     * Get the registered user_id
     *
     * @return integer $user_id
     */
    public function getUser()
    {
        return (isset($_SESSION['user_id'])) ? $_SESSION['user_id'] : 0;
    }

    /**
     * Get the registered permission_id
     *
     * @return integer $permission_id
     */
    public function getGroup()
    {
        return (isset($_SESSION['permission_id'])) ? $_SESSION['permission_id'] : 0;
    }

    /**
     * Get the registered permission_id
     *
     * @return integer $permission_id
     */
    public function getPermission($url)
    {
        $url = explode('/', $url);

        return (Render::isRestricted($url)) ? false : true;
    }

    /**
     * Get the registered permission_id
     *
     * @return integer $permission_id
     */
    public function getPermissions()
    {
        if (! $this->auth) {
            $this->auth = new Auth();
        }

        return $this->auth->getPermissions();
    }

    /**
     * Redirect to a new page
     */
    public function redirect($url, $message = null)
    {
        if ($message) {
            $this->setMessage($message);
        }

        header('Location:' . $url);
        exit;
    }

    /**
     * Set the BF global message
     *
     * @return integer $permission_id
     */
    public function setMessage($message)
    {
        if ($message) {
            if (! is_array($message)) {
                $message = [ 'message' => $message ];
            }

            $_SESSION['bossanova_message'] = json_encode($message);
        }
    }

    public function getRequestMethod()
    {
        return (isset($_SERVER['REQUEST_METHOD'])) ? $_SERVER['REQUEST_METHOD'] : 'GET';
    }
}
