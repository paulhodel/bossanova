<?php

namespace bossanova\Module;

use bossanova\Auth\Auth;
use bossanova\Render\Render;
use bossanova\Database\Database;
use bossanova\Services\Services;
use bossanova\Redis\Redis;
use bossanova\Mail\Mail;
use bossanova\Common\Post;
use bossanova\Common\Request;
use bossanova\Common\Ident;
use bossanova\Common\Configuration;
use bossanova\Common\Params;
use bossanova\View\View;
use bossanova\Config\Config;

class Module
{
    use Configuration, Ident, Params, Request, Post;

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
     * Connect to the database
     */
    public function __construct()
    {
        // Make sure a database instance is available
        $this->database = Database::getInstance(null, [
            DB_CONFIG_TYPE,
            DB_CONFIG_HOST,
            DB_CONFIG_USER,
            DB_CONFIG_PASS,
            DB_CONFIG_NAME
        ]);

        // Redis provider
        if (class_exists('Redis') && defined('REDIS_CONFIG_HOST') && REDIS_CONFIG_HOST) {
            $this->redis = Redis::getInstance([
                REDIS_CONFIG_HOST,
                REDIS_CONFIG_PORT
            ]);
        }
    }

    /**
     * The __default is call when method is not defined
     */
    public function __default()
    {
    }

    /**
     * Process one ajax request
     * @param Services $service
     * @param integer $id
     * @return array
     */
    public function processRestRequest(Services $service, $id = null)
    {
        if ($this->getRequestMethod() == "POST" || $this->getRequestMethod() == "PUT") {
            // Post variables
            $post = $this->getPost();

            // Before Process POST
            if (count($post) && is_callable(array($service, 'processPost'))) {
                $post = $service->processPost($post, $id);
            }

            if (! $id || $id === 'new') {
                $data = $service->insert($post);

                if (isset($data['id']) && $data['id']) {
                    $id = $data['id'];
                }
            } else {
                $data = $service->update($id, $post);
            }

            // After Process POST
            $post = $this->getPost();
            if (count($post) && is_callable(array($service, 'processAfterPost'))) {
                $ret = $service->processAfterPost($post, $id, $data);
                if ($ret) {
                    $data = $ret;
                }
            }
        } else if ($this->getRequestMethod() == "DELETE") {
            $data = $service->delete($id);
        } else {
            if (! $id || $id === 'search') {
                if (is_callable(array($service, 'search'))) {
                    $data = $service->search();
                } else {
                    $data = null;
                }
            } else {
                $data = $service->select($id);
                // Process data
                if (is_callable(array($service, 'processData'))) {
                    $data = $service->processData($data);
                }
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
                    $this->setLocale($locale);

                    // Redirect to the main module
                    $url = $this->getParam(0);

                    // Redirect to the main page
                    header("Location: /$url");
                }
            }
        } else {
            // Internal calls
            if (isset($locale)) {
                // Check if the source file exists
                if (file_exists("resources/locales/$locale.csv")) {
                    $this->setLocale($locale);
                }
            } else {
                // Return the current locale in the memory
                return $this->getLocale();
            }
        }
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

        // View class
        $v = new View($this->view);
        return $v->render($viewPath);
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
            header("Content-type: text/json");
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
    public function sendmail($to, $subject, $html, $from, $files = null)
    {
        if (! $this->mail) {
            // Get preferable mail adapter
            $adapter = Config::get('mail');
            // Create instance
            $this->mail = new Mail($adapter);
        }

        ob_start();
        $instance = $this->mail->sendmail($to, $subject, $html, $from, $files);
        ob_get_clean();

        return $instance;
    }

    /**
     * Login actions
     *
     * @return void
     */
    public function login()
    {
        if (class_exists('\services\Auth')) {
            $auth = new \services\Auth;
            $data = $auth->login();

            // Deal with the authetantion service return
            if (Render::isAjax()) {
                return $data;
            } else {
                if (isset($data['url'])) {
                    $this->redirect($data['url']);
                } else {
                    if ($data) {
                        $this->setMessage($data);
                    }
                }
            }
        } else {
            return [
                'error' => 1,
                'message' => 'No \services\auth handler found',
            ];
        }
    }

    /**
     * Redirect to a new page @TODO: Persistencia sem sessao
     */
    public function redirect($url, $message = null)
    {
        if ($message) {
            if (! is_array($message)) {
                $message = [ 'message' => $message ];
            }

            // Expire in 5 seconds
            $expires = time() + 5;
            $message = json_encode($message);

            header("Set-Cookie: bossanova_message={$message}; path=/; SameSite=Lax; expires={$expires};");
        }

        header('Location:' . $url);
        exit;
    }

    /**
     * Referer
     */
    public function setReferer($url = null)
    {
        if (! $url) {
            $url = implode('/', $this->getParam());
        }
        // Expire in 1 hour
        $expires = time() + 3600;
        header("Set-Cookie: bossanova_referer={$url}; path=/; SameSite=Lax; expires={$expires};");
    }

    public function getReferer($clear = true)
    {
        if ($clear) {
            header("Set-Cookie: bossanova_referer=0; path=/; SameSite=Lax; expires=0;");
        }

        return isset($_COOKIE['bossanova_referer']) && $_COOKIE['bossanova_referer'] ? $_COOKIE['bossanova_referer'] : '';
    }

    /**
     * Message from backend to frontend
     *
     * @return integer $permission_id
     */
    public function setMessage($message)
    {
        if ($message) {
            if (! is_array($message)) {
                $message = [ 'message' => $message ];
            }

            Render::setMessage(json_encode($message));
        }
    }

    public function getRequestMethod()
    {
        return (isset($_SERVER['REQUEST_METHOD'])) ? $_SERVER['REQUEST_METHOD'] : 'GET';
    }

    public function isAjax($jsonOnly = false)
    {
        return Render::isAjax($jsonOnly);
    }
}
