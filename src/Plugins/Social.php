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
 * Plugins Social Network Names
 */
namespace bossanova\Plugins;

use bossanova\Render\Render;
use bossanova\Database\Database;

class Social
{
    public function run()
    {
        // Potential user
        $userName = isset(Render::$urlParam[0]) ? Render::$urlParam[0] : '';

        // Possible username
        if (strlen($userName) && $userName != '' && Render::$notFound == 1) {
            // Default module to render users
            if (file_exists("modules/Me/Me.php") ||
                file_exists("modules/Me/Me.class.php")) {
                // Locate route
                $realpath = strtolower($userName);

                // Model users
                $user = new \models\Users();

                if ((int)$realpath > 0) {
                    $row = $user->getById((int)$realpath);
                } else {
                    $row = $user->getUserByLogin($realpath);
                }

                // User found with this route
                if (isset($row['user_id'])) {
                    // UserId
                    Render::$configuration['user_id'] = $row['user_id'];
                    // Reset flag
                    Render::$notFound = 0;
                    // Module should be use to render the user information
                    Render::$configuration['module_name'] = "Me";

                    // Verify controller
                    if (isset(Render::$urlParam[1]) && $controller_name = Render::$urlParam[1]) {
                        $controller_name = ucfirst(strtolower($controller_name));
                        // Controller found with the requested route
                        if (file_exists("modules/Me/controllers/$controller_name.php") ||
                            file_exists("modules/Me/controllers/$controller_name.class.php")) {
                            // Controller should be used
                            Render::$configuration['module_controller'] = $controller_name;
                        } else {
                            $realpath = Render::$urlParam;
                            array_shift($realpath);
                            $realpath = implode('/', $realpath);

                            // Possible user page
                            $nodes = new \models\Nodes();
                            $row = $nodes->getByRoute($realpath, $row['user_id']);

                            if ($row) {
                                // Node
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
        }
    }
}