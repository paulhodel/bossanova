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
 * Incoming Get Variables
 */
namespace bossanova\Common;

trait Request
{
    /**
     * This function is to return $_POST values
     *
     * @param  array $filter Array with the values you want to get from the $_POST, if is NULL return the whole $_POST.
     * @return array $row    Array with values from $_POST
     */
    public function getRequest($filter = null)
    {
        // Return all variables in the post
        if (! isset($filter)) {
            if (isset($_GET)) {
                $row = $_GET;
            }
        } else {
            // Return only what you have defined as important
            if (is_string($filter)) {
                $row = isset($_GET[$filter]) ? $_GET[$filter] : null;
            } else {
                if (is_array($filter)) {
                    foreach ($filter as $k => $v) {
                        if (isset($_GET[$v])) {
                            $row[$v] = $_GET[$v];
                        }
                    }
                }
            }
        }

        return isset($row) ? $row : null;
    }
}