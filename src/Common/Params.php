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
 * URL Params
 */
namespace bossanova\Common;

use bossanova\Render\Render;

Trait Params
{
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
}