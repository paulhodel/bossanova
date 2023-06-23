<?php

namespace bossanova\View;

use bossanova\Auth\Auth;
use bossanova\Common\Ident;
use bossanova\Common\Params;
use bossanova\Common\Post;
use bossanova\Common\Request;

class View
{
    use Ident, Params, Request, Post;

    public $view;

    /**
     * @argument $view array - Variables available in the view
     * @param string $view
     */
    public function __construct($view = null)
    {
        if ($view) {
            $this->view = $view;
        }
    }

    public function render($viewPath = null)
    {
        if (! $viewPath && isset($this->module) && isset($this->viewFile)) {
            $viewPath = 'modules/' . ucfirst(strtolower($this->module)) . '/views/' . strtolower($this->viewFile) . '.html';
        }

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
}
