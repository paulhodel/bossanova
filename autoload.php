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
* Autoload
*/

spl_autoload_register('autoloader');

function autoloader($class_name)
{
    $path = [
        '',
        'vendor/',
        'vendor/bossanova',
    ];

    $fileName1 = str_replace('\\', '/', $class_name) . '.php';
    $fileName2 = str_replace('\\', '/', $class_name) . '.class.php';

    foreach ($path as $k => $v) {
        if (file_exists($v . $fileName1)) {
            include $v . $fileName1;
            break;
        } else if (file_exists($v . $fileName2)) {
            include $v . $fileName2;
            break;
        }
    }
}