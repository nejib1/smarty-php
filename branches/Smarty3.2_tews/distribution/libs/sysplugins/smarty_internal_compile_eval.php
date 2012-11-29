<?php

/**
 * Smarty Internal Plugin Compile Eval
 *
 * Compiles the {eval} tag.
 *
 * @package Smarty
 * @subpackage Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile Eval Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Eval extends Smarty_Internal_CompileBase {

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $required_attributes = array('var');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $optional_attributes = array('assign');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $shorttag_order = array('var', 'assign');

    /**
     * Compiles code for the {eval} tag
     *
     * @param array  $args     array with attributes from parser
     * @param object $compiler compiler object
     * @return string compiled code
     */
    public function compile($args, $compiler) {
        $this->required_attributes = array('var');
        $this->optional_attributes = array('assign');
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        if (isset($_attr['assign'])) {
            // output will be stored in a smarty variable instead of beind displayed
            $_assign = $_attr['assign'];
        }
        $this->iniTagCode($compiler);

        // create template object
        $this->php("\$_template = new {$compiler->template->template_class}('eval:'." . $_attr['var'] . ", \$_smarty_tpl->smarty, \$_smarty_tpl);")->newline();
        //was there an assign attribute?
        if (isset($_assign)) {
            $this->php("\$_smarty_tpl->assign($_assign,\$_template->fetch());")->newline();
        } else {
            $this->php("echo \$_template->fetch();")->newline();
        }
        $this->php("unset(\$_template);")->newline();

        return $this->returnTagCode($compiler);
    }

}
