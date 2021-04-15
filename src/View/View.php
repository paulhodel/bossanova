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
 * Module View
 */
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

        // Auth
        $this->auth = new Auth();
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
}