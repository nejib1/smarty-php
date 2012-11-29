<?php

/**
 * Smarty Internal Plugin Debug
 *
 * Class to collect data for the Smarty Debugging Consol
 *
 * @package Smarty
 * @subpackage Debug
 * @author Uwe Tews
 */

/**
 * Smarty Internal Plugin Debug Class
 *
 * @package Smarty
 * @subpackage Debug
 */
class Smarty_Internal_Debug extends Smarty_Internal_Data {

    /**
     * template data
     *
     * @var array
     */
    public static $_template_data = array();

    /**
     * Start logging of compile time
     *
     * @param object $_template
     */
    public static function start_compile($_template) {
        $key = self::get_key($_template);
        self::$_template_data[$key]['start_time'] = microtime(true);
    }

    /**
     * End logging of compile time
     *
     * @param object $_template
     */
    public static function end_compile($_template) {
        $key = self::get_key($_template);
        self::$_template_data[$key]['compile_time'] += microtime(true) - self::$_template_data[$key]['start_time'];
    }

    /**
     * Start logging of render time
     *
     * @param object $_template
     */
    public static function start_render($_template) {
        $key = self::get_key($_template);
        self::$_template_data[$key]['start_time'] = microtime(true);
    }

    /**
     * End logging of compile time
     *
     * @param object $_template
     */
    public static function end_render($_template) {
        $key = self::get_key($_template);
        self::$_template_data[$key]['render_time'] += microtime(true) - self::$_template_data[$key]['start_time'];
    }

    /**
     * Start logging of cache time
     *
     * @param object $_template cached template
     */
    public static function start_cache($_template) {
        $key = self::get_key($_template);
        self::$_template_data[$key]['start_time'] = microtime(true);
    }

    /**
     * End logging of cache time
     *
     * @param object $_template cached template
     */
    public static function end_cache($_template) {
        $key = self::get_key($_template);
        self::$_template_data[$key]['cache_time'] += microtime(true) - self::$_template_data[$key]['start_time'];
    }

    /**
     * Opens a window for the Smarty Debugging Consol and display the data
     *
     * @param Smarty_Internal_Template|Smarty $obj object to debug
     */
    public static function display_debug($obj) {
        // prepare information of assigned variables
        $ptr = self::get_debug_vars($obj);
        $_template = clone $obj;
        unset($_template->source, $_template->compiled, $_template->cached, $_template->compiler, $_template->mustCompile);
        $_template->tpl_vars = new Smarty_Variable_Container($_template);
        $_template->template_resource = $_template->debug_tpl;
        $_template->registered_filters = array();
        $_template->autoload_filters = array();
        $_template->default_modifiers = array();
        $_template->force_compile = false;
        $_template->left_delimiter = '{';
        $_template->right_delimiter = '}';
        $_template->debugging = false;
        $_template->force_compile = false;
        $_template->caching = false;
        $_template->disableSecurity();
        $_template->cache_id = null;
        $_template->compile_id = null;
        $_assigned_vars = $ptr->tpl_vars;
        ksort($_assigned_vars);
        $_config_vars = $ptr->config_vars;
        ksort($_config_vars);
        if ($obj->is_template) {
            $_template->assign('template_name', $obj->source->type . ':' . $obj->source->name);
        } else {
            $_template->assign('template_name', null);
        }
        if (!$obj->is_template) {
            $_template->assign('template_data', self::$_template_data);
        } else {
            $_template->assign('template_data', null);
        }
        $_template->assign('assigned_vars', $_assigned_vars);
        $_template->assign('config_vars', $_config_vars);
        $_template->assign('execution_time', microtime(true) - $_template->start_time);
        echo $_template->fetch();
    }

    /**
     * Recursively gets variables from all template/data scopes
     *
     * @param Smarty_Internal_Template|Smarty_Data $obj object to debug
     * @return StdClass
     */
    public static function get_debug_vars($obj) {
        $config_vars = array();
        $tpl_vars = array();
        foreach ($obj->tpl_vars as $key => $value) {
            if ($key != '___smarty__data') {
                if (strpos($key, '___config_var_') !== 0) {
                    $tpl_vars[$key] = $value;
                    if ($obj->is_template) {
                        $tpl_vars[$key]['source'] = $obj->source->type . ':' . $obj->source->name;
                    } elseif (!property_exists($obj, 'is_template')) {
                        $tpl_vars[$key]['source'] = 'Data object';
                    } else {
                        $tpl_vars[$key]['source'] = 'Smarty object';
                    }
                } else {
                    $key = substr($key, 14);
                    $config_vars[$key] = $value;
                    if ($obj->is_template) {
                        $config_vars[$key]['source'] = $obj->source->type . ':' . $obj->source->name;
                    } elseif (!property_exists($obj, 'is_template')) {
                        $config_vars[$key]['source'] = 'Data object';
                    } else {
                        $config_vars[$key]['source'] = 'Smarty object';
                    }
                }
            }
        }

        if (isset($obj->parent)) {
            $parent = self::get_debug_vars($obj->parent);
            $tpl_vars = array_merge($parent->tpl_vars, $tpl_vars);
            $config_vars = array_merge($parent->config_vars, $config_vars);
        } else {
            foreach (Smarty::$global_tpl_vars as $key => $var) {
                if (strpos($key, '___smarty__data') !== 0) {
                    if (!isset($tpl_vars[$key])) {
                        if (strpos($key, '___smarty_conf_') !== 0) {
                            $tpl_vars[$key] = $var;
                            $tpl_vars[$key]['source'] = 'Global';
                        } else {
                            
                        }
                    }
                }
            }
        }
        return (object) array('tpl_vars' => $tpl_vars, 'config_vars' => $config_vars);
    }

    /**
     * Return key into $_template_data for template
     *
     * @param object $_template  template object
     * @return string   key into $_template_data
     */
    private static function get_key($_template) {
        static $_is_stringy = array('string' => true, 'eval' => true);
        // calculate Uid if not already done
        if ($_template->source->uid == '') {
            $_template->source->filepath;
        }
        $key = $_template->source->uid;
        if (isset(self::$_template_data[$key])) {
            return $key;
        } else {
            if (isset($_is_stringy[$_template->source->type])) {
                self::$_template_data[$key]['name'] = '\'' . substr($_template->source->name, 0, 25) . '...\'';
            } else {
                self::$_template_data[$key]['name'] = $_template->source->filepath;
            }
            self::$_template_data[$key]['compile_time'] = 0;
            self::$_template_data[$key]['render_time'] = 0;
            self::$_template_data[$key]['cache_time'] = 0;
            return $key;
        }
    }

}

/**
 * Smarty debug_print_var modifier
 *
 * Type:     modifier<br>
 * Name:     debug_print_var<br>
 * Purpose:  formats variable contents for display in the console
 *
 * @param array|object $var     variable to be formatted
 * @param integer      $depth   maximum recursion depth if $var is an array
 * @param integer      $length  maximum string length if $var is a string
 * @param bool         $root    flag true if called in debug.tpl
 * @return string
 */
function smarty_modifier_debug_print_var($var, $depth = 0, $length = 40, $root = true) {
    $_replace = array("\n" => '<i>\n</i>',
        "\r" => '<i>\r</i>',
        "\t" => '<i>\t</i>'
    );

    switch (gettype($var)) {
        case 'array' :
            if ($root) {
                $results = '';
            } else {
                $results = '<b>Array (' . count($var) . ')</b>';
            }
            foreach ($var as $curr_key => $curr_val) {
                $results .= '<br>' . str_repeat('&nbsp;', $depth * 2)
                        . '<b>' . strtr($curr_key, $_replace) . '</b> =&gt; '
                        . smarty_modifier_debug_print_var($curr_val, ++$depth, $length, false);
                $depth--;
            }
            break;

        case 'object' :
            $object_vars = get_object_vars($var);
            $results = '<b>' . get_class($var) . ' Object (' . count($object_vars) . ')</b>';
            foreach ($object_vars as $curr_key => $curr_val) {
                $results .= '<br>' . str_repeat('&nbsp;', $depth * 2)
                        . '<b> -&gt;' . strtr($curr_key, $_replace) . '</b> = '
                        . smarty_modifier_debug_print_var($curr_val, ++$depth, $length, false);
                $depth--;
            }
            break;

        case 'boolean' :
        case 'NULL' :
        case 'resource' :
            if (true === $var) {
                $results = 'true';
            } elseif (false === $var) {
                $results = 'false';
            } elseif (null === $var) {
                $results = 'null';
            } else {
                $results = htmlspecialchars((string) $var);
            }
            $results = '<i>' . $results . '</i>';
            break;

        case 'integer' :
        case 'float' :
            $results = htmlspecialchars((string) $var);
            break;

        case 'string' :
            $results = strtr($var, $_replace);
            if (Smarty::$_MBSTRING) {
                if (mb_strlen($var, Smarty::$_CHARSET) > $length) {
                    $results = mb_substr($var, 0, $length - 3, Smarty::$_CHARSET) . '...';
                }
            } else {
                if (isset($var[$length])) {
                    $results = substr($var, 0, $length - 3) . '...';
                }
            }

            $results = htmlspecialchars('"' . $results . '"');
            break;

        case 'unknown type' :
        default :
            if (Smarty::$_MBSTRING) {
                if (mb_strlen($results, Smarty::$_CHARSET) > $length) {
                    $results = mb_substr($results, 0, $length - 3, Smarty::$_CHARSET) . '...';
                }
            } else {
                if (strlen($results) > $length) {
                    $results = substr($results, 0, $length - 3) . '...';
                }
            }

            $results = htmlspecialchars($results);
    }

    return $results;
}
