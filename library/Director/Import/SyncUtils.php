<?php

namespace Icinga\Module\Director\Import;

use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Objects\IcingaObject;

class SyncUtils
{
    /**
     * Extract variable names in the form ${var_name} from a given string
     *
     * @param  string $string
     *
     * @return array  List of variable names (without ${})
     */
    public static function extractVariableNames($string)
    {
        if (preg_match_all('/\${([A-Za-z0-9\._-]+)}/', $string, $m, PREG_PATTERN_ORDER)) {
            return $m[1];
        } else {
            return array();
        }
    }

    /**
     * Recursively extract a value from a nested structure
     *
     * For a $val looking like
     *
     * { 'vars' => { 'disk' => { 'sda' => { 'size' => '256G' } } } }
     *
     * and a key vars.disk.sda given as [ 'vars', 'disk', 'sda' ] this would
     * return { size => '255GB' }
     *
     * @param  string $val  The value to extract data from
     * @param  object $keys A list of nested keys pointing to desired data
     *
     * @return mixed
     */
    public static function getDeepValue($val, $keys)
    {
        $key = array_shift($keys);
        if (! property_exists($val, $key)) {
            return null;
        }

        if (empty($keys)) {
            return $val->$key;
        }

        return static::getDeepValue($val->$key, $keys);
    }

    /**
     * Return a specific value from a given row object
     *
     * Supports also keys pointing to nested structures like vars.disk.sda
     *
     * @param  object $row    stdClass object providing property values
     * @param  string $string Variable/property name
     *
     * @return mixed
     */
    public static function getSpecificValue($row, $var)
    {
        if (strpos($var, '.') === false) {
            if ($row instanceof IcingaObject) {
                return $row->$var;
            }
            if (! property_exists($row, $var)) {
                return null;
            }

            return $row->$var;
        } else {
            $parts = explode('.', $var);
            $main = array_shift($parts);
            if (! property_exists($row, $main)) {
                return null;
            }

            if (! is_object($row->$main)) {
                throw new IcingaException('Data is not nested, cannot access %s: %s', $var, var_export($row, 1));
            }

            return static::getDeepValue($row->$main, $parts);
        }
    }

    /**
     * Fill variables in the given string pattern
     *
     * This replaces all occurances of ${var_name} with the corresponding
     * property $row->var_name of the given row object. Missing variables are
     * replaced by an empty string. This works also fine in case there are
     * multiple variables to be found in your string.
     *
     * @param  string $string String with opional variables/placeholders
     * @param  object $row    stdClass object providing property values
     *
     * @return string
     */
    public static function fillVariables($string, $row)
    {
        if (preg_match('/^\${([A-Za-z0-9\._-]+)}$/', $string, $m)) {
            return static::getSpecificValue($row, $m[1]);
        }

        $func = function ($match) use ($row) {
            return static::getSpecificValue($row, $match[1]);
        };

        return preg_replace_callback('/\${([A-Za-z0-9\._-]+)}/', $func, $string);
    }

    public static function getRootVariables($vars)
    {
        $res = array();
        foreach ($vars as $p) {
            if (false === ($pos = strpos($p, '.'))) {
                $res[] = $p;
            } else {
                $res[] = substr($p, 0, $pos);
            }
        }

        return array_combine($res, $res);
    }
}
