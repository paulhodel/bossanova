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
 * Configuration
 */
namespace bossanova\Common;

use bossanova\Render\Render;

Trait Configuration
{
    /**
     * This function BF configuration definition
     *
     * @return array $configuration
     */
    public function getConfiguration($option = null)
    {
        // Return value
        if ($option) {
            $configuration = isset(Render::$configuration[$option]) ? Render::$configuration[$option] : null;
        } else {
            $configuration = Render::$configuration;
        }

        return $configuration;
    }

    /**
     * Get current view
     *
     * @return mixed - disabled or the current defined view
     */
    public function getView()
    {
        return Render::$configuration['view_render'] ? Render::$configuration['view'] : false;
    }

    /**
     * Enable disable automatic view load
     *
     * @param integer $mode - set to show the view in case exists
     * @return void
     */
    public function setView($render = false)
    {
        Render::$configuration['view_render'] = ($render) ? 1 : 0;

        if (isset($render) && is_string($render)) {
            Render::$configuration['view'] = $render;
        }
    }

    /**
     * Get current layout
     *
     * @return mixed - disabled or the current defined layout
     */
    public function getLayout()
    {
        return Render::$configuration['template_render'] ? Render::$configuration['template_path'] : false;
    }

    /**
     * Enable disable layout
     *
     * @param integer $mode
     * @return void
     */
    public function setLayout($render = false)
    {
        Render::$configuration['template_render'] = ($render) ? 1 : 0;

        if (isset($render) && is_string($render)) {
            Render::$configuration['template_path'] = $render;
        }
    }

    /**
     * Set Layout Title
     *
     * @param string $author
     * @return void
     */
    public function setTitle($data)
    {
        Render::$configuration['template_meta']['title'] = $data;
    }

    /**
     * Set Layout Author Meta
     *
     * @param string $author
     * @return void
     */
    public function setAuthor($data)
    {
        Render::$configuration['template_meta']['author'] = $data;
    }

    /**
     * Set Layout Description Meta
     *
     * @param string $value
     */
    public function setDescription($data)
    {
        Render::$configuration['template_meta']['description'] = $data;
    }

    /**
     * Set Layout Keywords Meta
     *
     * @param string $value
     * @return void
     */
    public function setKeywords($data)
    {
        Render::$configuration['template_meta']['keywords'] = $data;
    }

    /**
     * Set new content area
     *
     * @param string $value
     * @return void
     */
    public function setContent($data)
    {
        Render::$configuration['extra_config'][] = $data;
    }

}