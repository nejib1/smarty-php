<?php

/**
 * Smarty Internal Plugin Compile Include PHP
 *
 * Compiles the {include_php} tag
 *
 *
 * @package Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile Insert Class
 *
 *
 * @package Compiler
 */
class Smarty_Internal_Compile_Include_Php extends Smarty_Internal_CompileBase
{

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see $tpl_obj
     */
    public $required_attributes = array('file');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see $tpl_obj
     */
    public $shorttag_order = array('file');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see $tpl_obj
     */
    public $optional_attributes = array('once', 'assign');

    /**
     * Compiles code for the {include_php} tag
     *
     * @param array $args     array with attributes from parser
     * @param object $compiler compiler object
     * @throws SmartyException
     * @return string compiled code
     */
    public function compile($args, $compiler)
    {
        if (!($compiler->tpl_obj instanceof SmartyBC)) {
            throw new SmartyException("{include_php} is deprecated, use SmartyBC class to enable");
        }
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        $_smarty_tpl = $compiler->tpl_obj;
        $_filepath = false;
        eval('$_file = ' . $_attr['file'] . ';');
        if (!isset($compiler->tpl_obj->security_policy) && file_exists($_file)) {
            $_filepath = $_file;
        } else {
            if (isset($compiler->tpl_obj->security_policy)) {
                $_dir = $compiler->tpl_obj->security_policy->trusted_dir;
            } else {
                $_dir = $compiler->tpl_obj->trusted_dir;
            }
            if (!empty($_dir)) {
                foreach ((array)$_dir as $_script_dir) {
                    $_script_dir = rtrim($_script_dir, '/\\') . DS;
                    if (file_exists($_script_dir . $_file)) {
                        $_filepath = $_script_dir . $_file;
                        break;
                    }
                }
            }
        }
        if ($_filepath == false) {
            $compiler->trigger_template_error("{include_php} file '{$_file}' is not readable", $compiler->lex->taglineno);
        }

        if (isset($compiler->tpl_obj->security_policy)) {
            $compiler->tpl_obj->security_policy->isTrustedPHPDir($_filepath);
        }

        if (isset($_attr['assign'])) {
            // output will be stored in a smarty variable instead of being displayed
            $_assign = $_attr['assign'];
            // set flag that variable container must be cloned
            $compiler->must_clone_vars = true;
        }
        $_once = '_once';
        if (isset($_attr['once'])) {
            if ($_attr['once'] == 'false') {
                $_once = '';
            }
        }

        $this->iniTagCode($compiler);

        if (isset($_assign)) {
            $this->php('ob_start();')->newline();
            $this->php("include{$_once} ('{$_filepath}');")->newline();
            $this->php("\$_smarty_tpl->assign({$_assign},ob_get_clean());")->newline();
        } else {
            $this->php("include{$_once} ('{$_filepath}');")->newline();
        }

        return $this->returnTagCode($compiler);
    }

}
