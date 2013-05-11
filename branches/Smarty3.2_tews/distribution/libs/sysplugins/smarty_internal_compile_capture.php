<?php

/**
 * Smarty Internal Plugin Compile Capture
 *
 * Compiles the {capture} tag
 *
 *
 * @package Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile Capture Class
 *
 *
 * @package Compiler
 */
class Smarty_Internal_Compile_Capture extends Smarty_Internal_CompileBase
{

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see $tpl_obj
     */
    public $shorttag_order = array('name');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see $tpl_obj
     */
    public $optional_attributes = array('name', 'assign', 'append');


    /**
     * capture  stack during compilation
     *
     * @var array
     */
    public static $_capture_stack = array();

    /**
     * Compiles code for the {capture} tag
     *
     * @param array $args     array with attributes from parser
     * @param object $compiler compiler object
     * @return string compiled code
     */
    public function compile($args, $compiler)
    {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);

        $buffer = isset($_attr['name']) ? $_attr['name'] : "'default'";
        $assign = isset($_attr['assign']) ? $_attr['assign'] : 'null';
        $append = isset($_attr['append']) ? $_attr['append'] : 'null';

        if (isset($_attr['assign']) || isset($_attr['append'])) {
            // set flag that variable container must be cloned
            $compiler->must_clone_vars = true;
        }

        self::$_capture_stack[] = array($buffer, $assign, $append, $compiler->nocache);
        // maybe nocache because of nocache variables
        $compiler->nocache = $compiler->nocache | $compiler->tag_nocache;

        $this->iniTagCode($compiler);

        $this->php("\$_smarty_tpl->_capture_stack[0][] = array($buffer, $assign, $append);")->newline();
        $this->php("ob_start();")->newline();

        return $this->returnTagCode($compiler);
    }

}

/**
 * Smarty Internal Plugin Compile Captureclose Class
 *
 *
 * @package Compiler
 */
class Smarty_Internal_Compile_CaptureClose extends Smarty_Internal_CompileBase
{

    /**
     * Compiles code for the {/capture} tag
     *
     * @param array $args     array with attributes from parser
     * @param object $compiler compiler object
     * @return string compiled code
     */
    public function compile($args, $compiler)
    {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        // must end block be nocache?
        if ($compiler->nocache) {
            $compiler->tag_nocache = true;
        }

        list($buffer, $assign, $append, $compiler->nocache) = array_pop(Smarty_Internal_Compile_Capture::$_capture_stack);

        $this->iniTagCode($compiler);

        $this->php("list(\$_capture_buffer, \$_capture_assign, \$_capture_append) = array_pop(\$_smarty_tpl->_capture_stack[0]);")->newline();
        $this->php("if (!empty(\$_capture_buffer)) {")->newline()->indent();
        $this->php("if (isset(\$_capture_assign)) {")->newline()->indent();
        $this->php("\$_smarty_tpl->assign(\$_capture_assign, ob_get_contents());")->newline()->indent();
        $this->outdent()->php("}")->newline();
        $this->php("if (isset( \$_capture_append)) {")->newline()->indent();
        $this->php("\$_smarty_tpl->append(\$_capture_append, ob_get_contents());")->newline()->indent();
        $this->outdent()->php("}")->newline();
        $this->php("Smarty::\$_smarty_vars['capture'][\$_capture_buffer]=ob_get_clean();")->newline();
        $this->outdent()->php("} else {")->newline()->indent();
        $this->php("\$_smarty_tpl->_capture_error();")->newline();
        $this->outdent()->php("}")->newline();

        return $this->returnTagCode($compiler);
    }

}
