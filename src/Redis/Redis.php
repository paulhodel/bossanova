<?php

namespace bossanova\Redis;

class Redis
{
    /**
     * Connection resource handler for the database connections
     *
     * @var $connection
     */
    private static $connection = null;

    /**
     * Singleton class
     */
    private function __construct()
    {
    }

    /**
     * Cannot be clonned
     */
    private function __clone()
    {
    }

    /**
     * This method create the first instance, create the connection and return
     * the singleton connection from the second call.
     *
     * @param  string $id     Define an arbitrary instance name
     * @param  array  $config Database connetion configuration
     * @return $this
     */
    public static function getInstance($config = null)
    {
        if (! self::$connection && $config && $config[0] && $config[1]) {
            try {
                self::$connection = new \Redis();
                self::$connection->connect($config[0], $config[1]);
            }
            catch (\Exception $e) {
                die($e->getMessage());
            }
        }

        return self::$connection;
    }
}
