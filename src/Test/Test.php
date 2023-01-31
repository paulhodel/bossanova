<?php

namespace bossanova\Test;

use PHPUnit\Framework\TestCase;
use bossanova\Render\Render;
use bossanova\Database\Database;

include_once 'config.php';

class Test extends TestCase
{
    public function __construct()
    {
        $this->database = Database::getInstance(null, [
            DB_CONFIG_TYPE,
            DB_CONFIG_HOST,
            DB_CONFIG_USER,
            DB_CONFIG_PASS,
            DB_CONFIG_NAME
        ]);

        parent::__construct();
    }

    public function mockRequest($url, $get = null, $post = null)
    {
        $render = new Render($url);

        if (isset($get) && is_array($get)) {
            $this->setRequest('GET', $get);
        }

        if (isset($get) && is_array($post)) {
            $this->setRequest('POST', $post);
        }

        return $render;
    }

    /**
     * Force request values
     * @param string $type
     * @param array $value
     */
    private function setRequest($type, Array $value)
    {
        if ($type == 'GET') {
            $_GET = $value;
        } else if ($type == 'POST') {
            $_POST = $value;
        }
    }
}
