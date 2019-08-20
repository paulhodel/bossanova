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
 * Test Library
 */
namespace bossanova\Test;

use PHPUnit\Framework\TestCase;
use bossanova\Render\Render;
use bossanova\Database\Database;

class Test extends TestCase
{
    public function __construct()
    {
        Database::getInstance(null, [
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

include_once 'config.php';