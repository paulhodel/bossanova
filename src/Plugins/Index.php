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
 * Index module
 */
namespace bossanova\Plugins;

use bossanova\Render\Render;

class Index
{
    public function run()
    {
        // Possible username
        if (Render::$notFound == 1) {
            // Default module
            if (file_exists("modules/Index/Index.php")) {
                // Locate route
                $realpath = implode('/', Render::$urlParam);

                if (! $realpath) {
                    $filename = "modules/Index/views/index.html";
                } else {
                    $filename = "modules/Index/views/" . $realpath . ".html";
                }

                if (file_exists($filename)) {
                    Render::$configuration['module_name'] = "Index";
                    Render::$configuration['view_name'] = (! $realpath) ? 'index' : $realpath;
                }
            }
        }
    }
}