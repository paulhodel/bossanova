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
 * CMS Plugin
 */
namespace bossanova\Plugins;

use bossanova\Render\Render;

class Nodes
{
    public function run()
    {
        // Possible username
        if (Render::$notFound == 1) {
            // Default module to render users
            if (file_exists("modules/Nodes/Nodes.php")) {
                // Locate route
                $realpath = implode('/', Render::$urlParam);

                // Model users
                $nodes = new \models\Nodes();

                if (isset(Render::$urlParam[0]) && isset(Render::$urlParam[1]) && Render::$urlParam[0] == 'nodes' && (int)Render::$urlParam[1] > 0) {
                    $row = $nodes->getById((int)Render::$urlParam[1]);
                } else {
                    $row = $nodes->getByRoute($realpath);
                }

                // User found with this route
                if (isset($row['node_id'])) {
                    // UserId
                    Render::$configuration['node_id'] = $row['node_id'];
                    // Reset flag
                    Render::$notFound = 0;
                    // Module should be use to render the user information
                    Render::$configuration['module_name'] = "Nodes";
                }
            }
        }
    }
}