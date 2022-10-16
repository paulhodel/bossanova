<?php

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
