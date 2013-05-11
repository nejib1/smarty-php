<?php

/**
 * Smarty Internal Plugin Compile Insert
 *
 * Compiles the {insert} tag
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
class Smarty_Internal_Compile_Insert extends Smarty_Internal_CompileBase
{

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see $tpl_obj
     */
    public $required_attributes = array('name');

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
    public $optional_attributes = array('_any');

    /**
     * Compiles code for the {insert} tag
     *
     * @param array $args     array with attributes from parser
     * @param object $compiler compiler object
     * @return string compiled code
     */
    public function compile($args, $compiler)
    {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        // never compile as nocache code
        $compiler->suppressNocacheProcessing = true;
        $compiler->tag_nocache = true;
        $_smarty_tpl = $compiler->tpl_obj;
        $_name = null;
        $_script = null;

        $this->iniTagCode($compiler);

        // save possible attributes
        eval('$_name = ' . $_attr['name'] . ';');
        if (isset($_attr['assign'])) {
            // output will be stored in a smarty variable instead of being displayed
            $_assign = $_attr['assign'];
            // set flag that variable container must be cloned
            $compiler->must_clone_vars = true;
            // create variable to make sure that the compiler knows about its nocache status
            $compiler->tpl_obj->tpl_vars->{trim($_attr['assign'], "'")} = new Smarty_Variable(null, true);
        }
        if (isset($_attr['script'])) {
            // script which must be included
            $_function = "smarty_insert_{$_name}";
            $_smarty_tpl = $compiler->tpl_obj;
            $_filepath = false;
            eval('$_script = ' . $_attr['script'] . ';');
            if (!isset($compiler->tpl_obj->security_policy) && file_exists($_script)) {
                $_filepath = $_script;
            } else {
                if (isset($compiler->tpl_obj->security_policy)) {
                    $_dir = $compiler->tpl_obj->security_policy->trusted_dir;
                } else {
                    $_dir = $compiler->tpl_obj->trusted_dir;
                }
                if (!empty($_dir)) {
                    foreach ((array)$_dir as $_script_dir) {
                        $_script_dir = rtrim($_script_dir, '/\\') . DS;
                        if (file_exists($_script_dir . $_script)) {
                            $_filepath = $_script_dir . $_script;
                            break;
                        }
                    }
                }
            }
            if ($_filepath == false) {
                $compiler->trigger_template_error("{insert} missing script file '{$_script}'", $compiler->lex->taglineno);
            }
            // code for script file loading
            $this->php("require_once '{$_filepath}';")->newline();
            require_once $_filepath;
            if (!is_callable($_function)) {
                $compiler->trigger_template_error(" {insert} function '{$_function}' is not callable in script file '{$_script}'", $compiler->lex->taglineno);
            }
        } else {
            $_function = "insert_{$_name}";
            // function in PHP script ?
            if (!is_callable($_function)) {
                // try plugin
                if (!$_function = $compiler->getPlugin($_name, 'insert')) {
                    $compiler->trigger_template_error("{insert} no function or plugin found for '{$_name}'", $compiler->lex->taglineno);
                }
            }
        }
        // delete {insert} standard attributes
        unset($_attr['name'], $_attr['assign'], $_attr['script'], $_attr['nocache']);
        // convert attributes into parameter array string
        $_paramsArray = array();
        foreach ($_attr as $_key => $_value) {
            $_paramsArray[] = "'$_key' => $_value ";
        }
        $_params = 'array(' . implode(", ", $_paramsArray) . ')';
        // call insert
        if (isset($_assign)) {
            if ($_smarty_tpl->caching) {
                $this->buffer .= str_repeat(' ', $this->indentation * 4);

                $this->raw(str_repeat(' ', $this->indentation * 4))->raw("\$tmp_p = var_export({$_params}, true);")->raw("\n");
                $this->raw(str_repeat(' ', $this->indentation * 4))->raw("echo \"/*%%SmartyNocache%%*/\\\$_smarty_tpl->assign({$_assign} , {$_function}(\$tmp_p, \\\$_smarty_tpl), true);/*/%%SmartyNocache%%*/\";")->raw("\n");
            } else {
                $this->php("\$_smarty_tpl->assign({$_assign} , {$_function} ({$_params},\$_smarty_tpl), true);")->newline();
            }
        } else {
            $compiler->has_output = true;
            if ($_smarty_tpl->caching) {
                $this->raw(str_repeat(' ', $this->indentation * 4))->raw("\$tmp_p = var_export({$_params}, true);")->raw("\n");
                $this->raw(str_repeat(' ', $this->indentation * 4))->raw("echo \"/*%%SmartyNocache%%*/echo {$_function}(\$tmp_p, \\\$_smarty_tpl);/*/%%SmartyNocache%%*/\";")->raw("\n");
            } else {
                $this->php("echo {$_function}({$_params},\$_smarty_tpl);")->newline();
            }
        }

        return $this->returnTagCode($compiler);
    }

}
