<?php

/**
 * Smarty Internal Plugin Compile Assign
 *
 * Compiles the {assign} tag
 *
 * @package Smarty
 * @subpackage Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile Assign Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Assign extends Smarty_Internal_CompileBase {

    /**
     * Compiles code for the {assign} tag
     *
     * @param array  $args      array with attributes from parser
     * @param object $compiler  compiler object
     * @param array  $parameter array with compilation parameter
     * @return string compiled code
     */
    public function compile($args, $compiler, $parameter) {
        // the following must be assigned at runtime because it will be overwritten in Smarty_Internal_Compile_Append
        $this->required_attributes = array('var', 'value');
        $this->shorttag_order = array('var', 'value');
        $this->optional_attributes = array('scope');
        $this->option_flags = array('nocache', 'cachevalue');
        $_nocache = 'false';
        $_scope = Smarty::SCOPE_LOCAL;
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        $var = trim($_attr['var'], '\'"');
        // nocache ?
        if ($compiler->tag_nocache || $compiler->nocache) {
            $_nocache = 'true';
            // create nocache var to make it know for further compiling
            if (isset($compiler->template->tpl_vars->$var)) {
                $value = $compiler->template->tpl_vars->$var;
                $value['nocache'] = true;
                $compiler->template->tpl_vars->$var = $value;
            } else {
                $compiler->template->tpl_vars->$var = array('value' => null, 'nocache' => true);
            }
        }
        // scope setup
        if (isset($_attr['scope'])) {
            $_attr['scope'] = trim($_attr['scope'], "'\"");
            if ($_attr['scope'] == 'parent') {
                $_scope = Smarty::SCOPE_PARENT;
            } elseif ($_attr['scope'] == 'root') {
                $_scope = Smarty::SCOPE_ROOT;
                $_scope = Smarty::SCOPE_ROOT;
            } elseif ($_attr['scope'] == 'global') {
                $_scope = Smarty::SCOPE_GLOBAL;
            } else {
                $compiler->trigger_template_error('illegal value for "scope" attribute', $compiler->lex->taglineno);
            }
        }
        // compiled output
        $this->iniTagCode($compiler);

        if (isset($parameter['smarty_internal_index'])) {
            $this->php("\$this->_createLocalArrayVariable({$_attr['var']}, \$_smarty_tpl, {$_nocache});")->newline();
            $this->php("\$_smarty_tpl->tpl_vars->{$var}['value']{$parameter['smarty_internal_index']} = {$_attr['value']};")->newline();
        } else {
            if ($compiler->template->smarty instanceof SmartyBC) {
                $this->php("if (isset(\$_smarty_tpl->tpl_vars->{$var})) {")->newline()->indent();
                $this->php("\$_smarty_tpl->tpl_vars->{$var}['value'] = {$_attr['value']};")->newline();
                $this->php("\$_smarty_tpl->tpl_vars->{$var}['nocache'] = {$_nocache};")->newline();
                $this->outdent()->php("} else {")->newline()->indent();
                $this->php("\$_smarty_tpl->tpl_vars->{$var} = array('value' => {$_attr['value']}, 'nocache' => {$_nocache});")->newline();
                $this->outdent()->php("}")->newline();
            } else {
                $this->php("\$_smarty_tpl->tpl_vars->{$var} = array('value' => {$_attr['value']}, 'nocache' => {$_nocache});")->newline();
            }
        }
        if ($_scope == Smarty::SCOPE_PARENT) {
            $this->php("if (\$_smarty_tpl->parent != null) {")->newline()->indent();
            $this->php("\$_smarty_tpl->parent->tpl_vars->{$var} = \$_smarty_tpl->tpl_vars->{$var};")->newline();
            $this->outdent()->php("}")->newline();
        } elseif ($_scope == Smarty::SCOPE_ROOT || $_scope == Smarty::SCOPE_GLOBAL) {
            $this->php("\$_ptr = \$_smarty_tpl->parent;")->newline();
            $this->php("while (\$_ptr != null) {")->newline()->indent();
            $this->php("\$_ptr->tpl_vars->{$var} = \$_smarty_tpl->tpl_vars->{$var};")->newline();
            $this->php("\$_ptr = \$_ptr->parent;")->newline();
            $this->outdent()->php("}")->newline();
        }
        if ($_scope == Smarty::SCOPE_GLOBAL) {
            $this->php("Smarty::\$global_tpl_vars->{$var} =  \$_smarty_tpl->tpl_vars->{$var};")->newline();
        }
        if ($_attr['cachevalue'] === true && $compiler->template->caching) {
            if (isset($parameter['smarty_internal_index'])) {
                $compiler->trigger_template_error('cannot assign to array with "cachevalue" option', $compiler->lex->taglineno);
            } else {
                if (!$compiler->tag_nocache && !$compiler->nocache) {
                    $this->php("echo '/*%%SmartyNocache%%*/\$_smarty_tpl->tpl_vars->{$var} = array(\'value\' => ' . \$_smarty_tpl->_export_cache_value({$_attr['value']}) . ');/*/%%SmartyNocache%%*/';")->newline();
                } else {
                    $compiler->trigger_template_error('cannot assign with "cachevalue" option inside nocache section', $compiler->lex->taglineno);
                }
            }
        }
        return $this->returnTagCode($compiler);
    }

}
