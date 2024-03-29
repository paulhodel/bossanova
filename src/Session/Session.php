<?php

namespace bossanova\Session;

class Session
{
    public static function get($k)
    {
        if (! self::valid()) {
            session_start();
        }

        return isset($_SESSION[$k]) && $_SESSION[$k] ? $_SESSION[$k] : null;
    }

    public static function set($k, $v)
    {
        if (! self::valid()) {
            session_start();
        }

        // Persist data
        $_SESSION[$k] = $v;
    }

    public static function valid()
    {
        return session_id() === '' ? FALSE : TRUE;
    }
}
