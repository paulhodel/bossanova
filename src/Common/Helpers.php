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
 * Helpers
 */
namespace bossanova\Common;

trait Helpers
{
    /**
     * This method reads and return a view content
     *
     * @param  string $moduleName
     * @param  string $viewName
     * @return string $html
     */
    public function loadHelperAsHtmlContainer($moduleName, $viewName, $data, $forceRefresh = true)
    {
        $hash = sha1($viewName . $moduleName);

        if ($forceRefresh) {
            $_SESSION['view'][$hash] = '';
        }

        // @TODO: cache this!!! change Load in case is not loaded yet
        if (! isset($_SESSION['view'][$hash]) || ! $_SESSION['view'][$hash]) {
            // View full path
            $viewPath = 'modules/' . ucfirst(strtolower($moduleName)) . '/helpers/' . strtolower($viewName) . '.html';

            // Call view if exists
            if (file_exists($viewPath)) {
                // Get content;
                $_SESSION['view'][$hash] = file_get_contents($viewPath);
            }
        }

        // Replace the data in the HTML container
        $html = '';

        if (isset($_SESSION['view'][$hash])) {
            $html = $this->jTemplate($_SESSION['view'][$hash], $data);
        }

        return $html;
    }

    public function jTemplate($template, $data)
    {
        $html = '';

        if (isset($data[0]) && count($data[0])) {
            foreach ($data as $k => $v) {
                $txt = $template;

                foreach ($v as $k1 => $v1) {
                    $txt = str_replace("{{". $k1 . "}}", $v1, $txt);
                }

                $html .= $txt;
            }
        } else {
            $html = $template;

            foreach ($data as $k => $v) {
                $html = str_replace("{{". $k . "}}", $v, $html);
            }
        }

        return $html;
    }
}