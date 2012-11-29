<?php

/**
 * Smarty Internal Compile Call Of Inheritance Templates
 *
 * Compiles code to load inheritance child and parent template
 *
 * @package Smarty
 * @subpackage Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Internal Compile Call Of Inheritance Templates Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Private_Inheritance_template extends Smarty_Internal_CompileBase {

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $required_attributes = array('file');
    public $option_flags = array('child');

    /**
     * Compiles code for callind interitance templaes
     *
     * @param array  $args      array with attributes from parser
     * @param object $compiler  compiler object
     * @param array  $parameter array with compilation parameter
     * @param string $tag       name of block plugin
     * @param string $function  PHP function name
     * @return string compiled code
     */
    public function compile($args, $compiler, $parameter, $tag, $function) {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        $_caching = Smarty::CACHING_OFF;
        // parents must not create cache files
        if ($compiler->template->caching) {
            $_caching = Smarty::CACHING_NOCACHE_CODE;
        }
        $file = realpath(trim($_attr['file'], "'"));

        $this->iniTagCode($compiler);

        if ($_attr['child'] === true) {
            $this->php("\$tpl = \$this->_createInheritanceTemplate ('{$file}', \$_smarty_tpl->cache_id, \$_smarty_tpl->compile_id, {$_caching}, (isset(\$tpl) ? \$tpl : \$_smarty_tpl), true);")->newline();
            $this->php("\$tpl->fetch(null, null, null, null, false, true, false);")->newline();
        } else {
            $this->php("\$tpl = \$this->_createInheritanceTemplate ('{$file}', \$_smarty_tpl->cache_id, \$_smarty_tpl->compile_id, {$_caching}, (isset(\$tpl) ? \$tpl : \$_smarty_tpl));")->newline();
            $this->php("echo \$tpl->fetch(null, null, null, null, false, true, false);")->newline();
        }
        $compiler->has_code = true;

        return $this->returnTagCode($compiler);
    }

}
