<?php

namespace bossanova\Common;

use bossanova\Render\Render;

Trait Params
{
    /**
     * This function return the parameters from the URL
     *
     * @param  integer $index number of the param http://domain/0/1/2/3/4/5/6/7...
     * @return mixed
     */
    public function getParam($index = null)
    {
        $value = null;

        // Get the global value defined in the router class
        if (isset($index)) {
            if (isset(Render::$urlParam[$index])) {
                $value = Render::$urlParam[$index];
            }
        } else {
            $value = Render::$urlParam;
        }

        // Return value
        return $value;
    }
}
