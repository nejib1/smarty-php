<?php

/**
 * Smarty Internal Plugin Compile extend
 *
 * Compiles the {extends} tag
 *
 * @package Smarty
 * @subpackage Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile extend Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Extends extends Smarty_Internal_CompileBase {

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $required_attributes = array('file');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $shorttag_order = array('file');

    /**
     * mbstring.overload flag
     *
     * @var int
     */
    public $mbstring_overload = 0;

    /**
     * Compiles code for the {extends} tag
     *
     * @param array  $args     array with attributes from parser
     * @param object $compiler compiler object
     * @return string compiled code
     */
    public function compile($args, $compiler) {
        // do not compile tag if template is recompiled to create nocache {block} code
        if ($compiler->nocache) {
            $compiler->has_code = false;
            return true;
        }
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        if ($_attr['nocache'] === true) {
            $compiler->trigger_template_error('nocache option not allowed', $compiler->lex->taglineno);
        }
        // this template is child
        $compiler->template->is_child = true;
        $_caching = Smarty::CACHING_OFF;
        // parents must not create cache files
        if ($compiler->template->caching) {
            $_caching = Smarty::CACHING_NOCACHE_CODE;
        }

        $this->iniTagCode($compiler);

        $this->php("ob_get_clean();")->newline();
        $this->php("\$tpl = \$this->_createInheritanceTemplate ({$_attr['file']}, \$_smarty_tpl->cache_id, \$_smarty_tpl->compile_id, {$_caching}, \$_smarty_tpl);")->newline();
        $this->php("echo \$tpl->fetch(null, null, null, null, false, true, false);")->newline();

        $compiler->extends_resource_name = $this->buffer;
        $this->buffer = '';

        // code for grabbing all output of child template which must be dropped
        $this->php("ob_start();")->newline();
        $this->php("\$_smarty_tpl->is_child = true;")->newline();
        $compiler->has_code = true;

        return $this->returnTagCode($compiler);
    }

}
