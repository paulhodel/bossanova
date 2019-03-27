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
 * Error Handler
 */
namespace bossanova\Error;

class Error
{
    // Singleton needs
    private function __construct()
    {

    }

    // Cannot be clonned
    private function __clone()
    {

    }

    /**
     * Error Handling
     */
    public static function handler($description, $e)
    {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $description = strip_tags($description);
            $e = strip_tags($e);
            $data['message'] = $description;
            echo json_encode($data);
        } else {
            echo "<h1>Bossanova Framework</h1>";
            echo "<p>{$description}</p>";
        }

        exit();
    }
}
