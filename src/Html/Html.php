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
 * HTML Helper
 */
namespace bossanova\Html;

class Html
{
    /**
     * This function is creating a <select> combo box
     * @Param array $options - all options contained in the new combo box
     * @Param string $value - selected option
     * @Param array $attr - attributes from the <select> tag
     * @Return string $html - return the HTML <select> combo syntax
     */
    public function select($options, $value, $attr)
    {
        $html = "<select";

        if (count($attr)) {
            foreach ($attr as $k => $v) {
                if ($k) {
                    $html .= " $k=\"$v\"";
                }
            }
        }

        $html .= ">";

        if (is_array($options) && count($options)) {
            foreach ($options as $k => $v) {
                if (is_array($v)) {
                    $html .= "<option value='{$v['id']}'";

                    if (is_array($value) && isset($value[$v['id']]) && $value[$v['id']]) {
                        $html .= " selected='selected'";
                    } else {
                        if ($v['id'] === $value) {
                            $html .= " selected='selected'";
                        }
                    }

                    if (isset($v['name'])) {
                        $v['label'] = $v['name'];
                    }

                    $html .= ">{$v['label']}</option>";
                } else {
                    $html .= "<option value='$k'";

                    if (is_array($value) && isset($value[$k]) && $value[$k]) {
                        $html .= " selected='selected'";
                    } else {
                        if ($k === $value) {
                            $html .= " selected='selected'";
                        }
                    }

                    $html .= ">$v</option>";
                }
            }
        } else {
            $html .= "<option value=''></option>";

            $num = explode(',', $options);

            if ($num[0] > 0 && $num[1] > 0) {
                $len = strlen($num[0]);

                if ($num[0] < $num[1]) {
                    for ($i = $num[0]; $i <= $num[1]; $i ++) {
                        $i = sprintf("%0{$len}d", $i);

                        $html .= "<option value='$i'";

                        if ($i === $value) {
                            $html .= " selected='selected'";
                        }

                        $html .= ">$i</option>";
                    }
                } else {
                    for ($i = $num[0]; $i >= $num[1]; $i --) {
                        $i = sprintf("%0{$len}d", $i);

                        $html .= "<option value='$i'";

                        if ($i === $value) {
                            $html .= " selected='selected'";
                        }

                        $html .= ">$i</option>";
                    }
                }
            }
        }

        $html .= "</select>";

        return $html;
    }

    public function bootstrapRadio($options, $value, $attr)
    {
        $prop = '';

        if (count($attr)) {
            foreach ($attr as $k => $v) {
                if ($k) {
                    $prop .= " $k=\"$v\"";
                }
            }
        }

        $html = '<div class="btn-group btn-group-toggle" data-toggle="buttons">';

        if (is_array($options) && count($options)) {
            foreach ($options as $k => $v) {
                if (is_array($v)) {
                    $html .= "<label class='btn btn-secondary'><input type='radio' $prop value='{$v['id']}'";

                    if ($v['id'] === $value) {
                        $html .= " checked='checked'";
                    }

                    $html .= ">{$v['label']}</label>";
                } else {
                    $html .= "<label class='btn btn-secondary'><input type='radio' $prop value='{$k}'";

                    if ($k === $value) {
                        $html .= " checked='checked'";
                    }

                    $html .= ">$v</label>";
                }
            }
        }


        $html .= '</div>';

        return $html;
    }

    public function checkbox()
    {
    }

    public function radiobox()
    {
    }

    public function textarea()
    {
    }

    /*public function selected($value, $item)
    {
        if (is_array($value)) {
            return isset($value[$item]) : true : false;
        } else {
            if ($values === $item) {
                return true;
            } else {
                return false;
            }
        }
    }*/
}
