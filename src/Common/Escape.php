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
 * Escape
 */
namespace bossanova\Common;

Trait Escape
{
    /**
     * Scape string
     *
     * @param  string
     * @return string
     */
    public function escape($str)
    {
        $str = trim($str);
        if (get_magic_quotes_gpc()) {
            $str = stripslashes($str);
        }
        $str = htmlentities($str);
        $search = array("\\", "\0", "\n", "\r", "\x1a", "'", '"');
        $replace = array("", "", "", "", "", "", "");
        return str_replace($search, $replace, $str);
    }
}