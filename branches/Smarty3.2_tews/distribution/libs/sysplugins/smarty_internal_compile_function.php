<?php

/**
 * Smarty Internal Plugin Compile Function
 *
 * Compiles the {function} {/function} tags
 *
 * @package Smarty
 * @subpackage Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile Function Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Function extends Smarty_Internal_CompileBase {

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
     * Compiles code for the {function} tag
     *
     * @param array $args array with attributes from parser
     * @param object $compiler compiler object
     * @param array $parameter array with compilation parameter
     * @return boolean true
     */
    public function compile($args, $compiler, $parameter) {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);

        if ($_attr['nocache'] === true) {
            $compiler->trigger_template_error('nocache option not allowed', $compiler->lex->taglineno);
        }
        unset($_attr['nocache']);
        $this->openTag($compiler, 'function', array($_attr, $compiler->buffer, $compiler->indentation, $compiler->template->has_nocache_code, $compiler->lex->taglineno, $compiler->required_plugins));

        $compiler->buffer = '';
        $compiler->indentation = 3;

        $compiler->compiles_template_function = true;
        $compiler->template->has_nocache_code = false;
        $compiler->has_code = false;

        return true;
    }

}

/**
 * Smarty Internal Plugin Compile Functionclose Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Functionclose extends Smarty_Internal_CompileBase {

    /**
     * Compiles code for the {/function} tag
     *
     * @param array $args array with attributes from parser
     * @param object $compiler compiler object
     * @param array $parameter array with compilation parameter
     * @return boolean true
     */
    public function compile($args, $compiler, $parameter) {
        $_attr = $this->getAttributes($compiler, $args);

        $saved_data = $this->closeTag($compiler, array('function'));
        $_name = trim($saved_data[0]['name'], "'\"");
        unset($saved_data[0]['name']);
        // set flag that we are compiling a template function
        $compiler->template_functions[$_name]['parameter'] = array();
        $_smarty_tpl = $compiler->template;
        foreach ($saved_data[0] as $_key => $_data) {
            eval('$tmp=' . $_data . ';');
            $compiler->template_functions[$_name]['parameter'][$_key] = $tmp;
        }
        // if caching save template function for possible nocache call
        if ($compiler->template->caching) {
            if (!empty($compiler->called_template_functions)) {
                $compiler->template_functions[$_name]['called_functions'] = $compiler->called_template_functions;
                $compiler->called_template_functions = array();
            }
            $plugins = array();
            foreach ($compiler->required_plugins['compiled'] as $plugin => $tmp) {
                if (!isset($saved_data[5]['compiled'][$plugin])) {
                    foreach ($tmp as $data) {
                        $plugins[$data['file']] = $data['function'];
                    }
                }
            }
            if (!empty($plugins)) {
                $compiler->template_functions[$_name]['used_plugins'] = $plugins;
            }
        }

        if ($compiler->template->source->type == 'eval' || $compiler->template->source->type == 'string') {
            $resource = $compiler->template->source->type;
        } else {
            $resource = $compiler->template->template_resource;
            // santitize extends resource
            if (strpos($resource, 'extends:') !== false) {
                $start = strpos($resource, ':');
                $end = strpos($resource, '|');
                $resource = substr($resource, $start + 1, $end - $start - 1);
            }
        }

        $this->buffer = '';
        $this->indentation = 2;

        $this->php("/* Line {$saved_data[4]} */")->newline();
        $this->php("function smarty_template_function_{$_name}(\$_smarty_tpl,\$params) {")->newline()->indent();
        $this->php("array_unshift(\$_smarty_tpl->trace_call_stack, array('{$resource}',{$saved_data[4]} , '{$compiler->template->source->type}'));")->newline();
        $this->php("\$saved_tpl_vars = clone \$_smarty_tpl->tpl_vars;")->newline();
        $this->php("foreach (\$this->template_functions['{$_name}']['parameter'] as \$key => \$value) {")->newline()->indent();
        $this->php("\$_smarty_tpl->tpl_vars->\$key = array('value' => \$value);")->newline();
        $this->outdent()->php("}")->newline();
        $this->php("foreach (\$params as \$key => \$value) {")->newline()->indent();
        $this->php("\$_smarty_tpl->tpl_vars->\$key = array('value' => \$value);")->newline();
        $this->outdent()->php("}")->newline();

        $compiler->template_functions_code[$_name] = $this->buffer;
        $this->buffer = '';

        $this->php("\$_smarty_tpl->tpl_vars = \$saved_tpl_vars;")->newline();
        $this->php("array_shift(\$_smarty_tpl->trace_call_stack);")->newline();
        $this->outdent()->php("}")->newline();

        $compiler->template_functions_code[$_name] .= $compiler->buffer . $this->buffer;

        // reset flag that we are compiling a template function
        $compiler->compiles_template_function = false;
        // restore old compiler status
        $compiler->buffer = $saved_data[1];
        $compiler->indentation = $saved_data[2];
        $this->iniTagCode($compiler);

        $compiler->template->has_nocache_code = $compiler->template->has_nocache_code | $saved_data[3];
        $compiler->has_code = false;
        return true;
    }

}
