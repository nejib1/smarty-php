<?php

/**
 * Smarty Internal Plugin Compile Compiler Plugin
 *
 * Compiles code of a compiler plugin
 *
 * @package Smarty
 * @subpackage Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile Compiler Plugin Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Private_Compiler_Plugin extends Smarty_Internal_CompileBase {

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $required_attributes = array();

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $optional_attributes = array('_any');

    /**
     * Compiles code for the execution of function plugin
     *
     * @param array $args array with attributes from parser
     * @param object $compiler compiler object
     * @param array $parameter array with compilation parameter
     * @param string $tag name of function plugin
     * @param string $function PHP function name
     * @return string compiled code
     */
    public function compile($args, $compiler, $parameter, $tag, $function) {
        // This tag does create output
        $compiler->has_output = true;

        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        if ($_attr['nocache'] === true) {
            $compiler->tag_nocache = true;
        }
        // convert arguments format for old compiler plugins
        $new_args = array();
        foreach ($args as $key => $mixed) {
            if (is_array($mixed)) {
                $new_args = array_merge($new_args, $mixed);
            } else {
                $new_args[$key] = $mixed;
            }
        }

        $plugin = 'smarty_compiler_' . $tag;
        if (isset($compiler->template->registered_plugins[Smarty::PLUGIN_COMPILER][$tag]) || isset($compiler->default_handler_plugins[Smarty::PLUGIN_COMPILER][$tag])) {
            if (isset($compiler->template->registered_plugins[Smarty::PLUGIN_COMPILER][$tag])) {
                if (!$compiler->template->registered_plugins[Smarty::PLUGIN_COMPILER][$tag][1]) {
                    $this->tag_nocache = true;
                }
                $function = $compiler->template->registered_plugins[Smarty::PLUGIN_COMPILER][$tag][0];
            } else {
                if (!$compiler->default_handler_plugins[Smarty::PLUGIN_COMPILER][$tag][1]) {
                    $this->tag_nocache = true;
                }
                $function = $compiler->default_handler_plugins[Smarty::PLUGIN_COMPILER][$tag][0];
            }
            if (!is_array($function)) {
                $raw_code = $function($new_args, $this);
            } else if (is_object($function[0])) {
                $raw_code = $compiler->template->registered_plugins[Smarty::PLUGIN_COMPILER][$tag][0][0]->$function[1]($new_args, $this);
            } else {
                $raw_code = call_user_func_array($function, array($new_args, $this));
            }
        } elseif (is_callable($plugin)) {
            $raw_code = $plugin($new_args, $this->smarty);
        } elseif (class_exists($plugin, false)) {
            $plugin_object = new $plugin;
            if (method_exists($plugin_object, 'compile')) {
                $raw_code = $plugin_object->compile($args, $this);
            }
        } else {
            // todo  error message
        }

        // compile code
        $this->iniTagCode($compiler);

        $raw_code = preg_replace('%(<\?php)[\r\n\t ]*|[\r\n\t ]*(\?>)%', '', $raw_code);

        $this->formatPHP($raw_code);

        return $this->returnTagCode($compiler);
    }

}
