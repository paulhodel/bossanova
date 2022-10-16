<?php

namespace bossanova\Common;

Trait Ident
{
    /**
     * Get the registered user_id
     *
     * @return integer $user_id
     */
    public function getIdent()
    {
        return $this->auth->getIdent();
    }

    /**
     * Get the registered user_id
     *
     * @return integer
     */
    public function getUser()
    {
        return $this->auth->getUser();
    }

    /**
     * Get the registered parent_id
     *
     * @return integer
     */
    public function getParentUser()
    {
        return $this->auth->getParentUser();
    }

    /**
     * Get the registered permission_id
     *
     * @return integer
     */
    public function getGroup()
    {
        return $this->auth->getGroup();
    }

    /**
     * Get the registered scope
     *
     * @return array
     */
    public function getPermissions()
    {
        return $this->auth->getPermissions();
    }

    /**
     * Alias for isAuthorized
     */
    public function getPermission($route)
    {
        return $this->auth->isAuthorized($route);
    }

    /**
     * Check if is a user is authorized based on the route
     */
    public function isAuthorized($route)
    {
        return $this->auth->isAuthorized($route);
    }

    /**
     * Get the registered locale
     *
     * @return integer
     */
    public function getLocale()
    {
        return $this->auth->getLocale();
    }

    /**
     * Get the registered locale
     *
     * @return integer
     */
    public function setLocale($locale)
    {
        $this->auth->setLocale($locale);
    }

    /**
     * Logout actions
     *
     * @return void
     */
    public function logout()
    {
        return $this->auth->logout();
    }
}
