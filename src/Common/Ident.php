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
 * Ident useful methods
 */
namespace bossanova\Common;

use bossanova\Jwt\Jwt;

Trait Ident
{
    public $jwt = null;

    /**
     * Get the registered user_id
     *
     * @return integer
     */
    public function getUser()
    {
        if (! $this->jwt) {
            $this->jwt = new Jwt();
        }

        return isset($this->jwt->user_id) ? $this->jwt->user_id : null;
    }

    /**
     * Get the registered parent_id
     *
     * @return integer
     */
    public function getParentUser()
    {
        if (! $this->jwt) {
            $this->jwt = new Jwt();
        }

        return isset($this->jwt->parent_id) ? $this->jwt->parent_id : null;
    }

    /**
     * Get the registered permission_id
     *
     * @return integer
     */
    public function getGroup()
    {
        if (! $this->jwt) {
            $this->jwt = new Jwt();
        }

        return isset($this->jwt->permission_id) ? $this->jwt->permission_id : null;
    }

    /**
     * Get the registered scope
     *
     * @return array
     */
    public function getPermissions()
    {
        if (! $this->jwt) {
            $this->jwt = new Jwt();
        }

        return isset($this->jwt->permissions) ? $this->jwt->permissions : null;
    }

    /**
     * Alias for isAuthorized
     */
    public function getPermission($route)
    {
        if (! $this->jwt) {
            $this->jwt = new Jwt();
        }

        return $this->isAuthorized($route);
    }

    /**
     * Check if is a user is authorized based on the route
     */
    public function isAuthorized($route)
    {
        if (! $this->jwt) {
            $this->jwt = new Jwt();
        }

        if (isset($this->jwt->permissions) && $this->jwt->permissions) {
            return property_exists($this->jwt->permissions, $route)
                ? true : false;
        } else {
            return false;
        }
    }

    /**
     * Get the registered locale
     *
     * @return integer
     */
    public function getLocale()
    {
        if (! $this->jwt) {
            $this->jwt = new Jwt();
        }

        return isset($this->jwt->locale) ? $this->jwt->locale : null;
    }

    /**
     * Get the registered locale
     *
     * @return integer
     */
    public function setLocale($locale)
    {
        if (! $this->jwt) {
            $this->jwt = new Jwt();
        }

        if (isset($this->jwt->user_id) && $this->jwt->user_id) {
            $this->jwt->locale = $locale;
            $this->jwt->save();
        }
    }
}