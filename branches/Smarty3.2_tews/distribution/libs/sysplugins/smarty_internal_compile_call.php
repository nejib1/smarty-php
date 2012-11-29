<?php

/**
 * Smarty Internal Plugin Compile Function_Call
 *
 * Compiles the calls of user defined tags defined by {function}
 *
 * @package Smarty
 * @subpackage Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile Function_Call Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Call extends Smarty_Internal_CompileBase {

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $required_attributes = array('name');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $shorttag_order = array('name');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $optional_attributes = array('_any');

    /**
     * Compiles the calls of user defined tags defined by {function}
     *
     * @param array  $args      array with attributes from parser
     * @param object $compiler  compiler object
     * @param array  $parameter array with compilation parameter
     * @return string compiled code
     */
    public function compile($args, $compiler) {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        // save possible attributes
        if (isset($_attr['assign'])) {
            // output will be stored in a smarty variable instead of beind displayed
            $_assign = $_attr['assign'];
        } else {
            $_assign = "''";
        }
        $_name = $_attr['name'];
        if ($compiler->compiles_template_function) {
            $compiler->called_template_functions[trim($_name, "'\"")] = true;
        }
        // nocache option
        if ($_attr['nocache'] === true) {
            $compiler->tag_nocache = true;
        }
        if ($compiler->template->caching && ($compiler->tag_nocache || $compiler->nocache)) {
            $compiler->called_nocache_template_functions[trim($_name, "'\"")] = true;
        }
        unset($_attr['name'], $_attr['assign'], $_attr['nocache']);
        $_paramsArray = array();
        foreach ($_attr as $_key => $_value) {
            if (is_int($_key)) {
                $_paramsArray[] = "$_key=>$_value";
            } else {
                $_paramsArray[] = "'$_key'=>$_value";
            }
        }

        $this->iniTagCode($compiler);

        $_params = 'array(' . implode(",", $_paramsArray) . ')';

        $this->php("\$this->_callTemplateFunction ($_name,\$_smarty_tpl,{$_params},{$_assign});")->newline();

        $compiler->has_code = true;
        return $this->returnTagCode($compiler);
    }

}
