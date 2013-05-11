<?php

/**
 * Smarty Internal Plugin Compile While
 *
 * Compiles the {while} tag
 *
 *
 * @package Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile While Class
 *
 *
 * @package Compiler
 */
class Smarty_Internal_Compile_While extends Smarty_Internal_CompileBase
{

    /**
     * Compiles code for the {while} tag
     *
     * @param array $args      array with attributes from parser
     * @param object $compiler  compiler object
     * @param array $parameter array with compilation parameter
     * @return string compiled code
     */
    public function compile($args, $compiler, $parameter)
    {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        $this->openTag($compiler, 'while', $compiler->nocache);

        if (!array_key_exists("if condition", $parameter)) {
            $compiler->trigger_template_error("missing while condition", $compiler->lex->taglineno);
        }

        // maybe nocache because of nocache variables
        $compiler->nocache = $compiler->nocache | $compiler->tag_nocache;

        $this->iniTagCode($compiler);

        if (is_array($parameter['if condition'])) {
            // set flag that variable container must be cloned
            $compiler->must_clone_vars = true;
            if (is_array($parameter['if condition']['var'])) {
                $var = trim($parameter['if condition']['var']['var'], "'");
            } else {
                $var = trim($parameter['if condition']['var'], "'");
            }
            if ($compiler->nocache) {
                $_nocache = 'true';
                // create nocache var to make it know for further compiling
                $compiler->tpl_obj->tpl_vars->$var = new Smarty_Variable(null, true);
            } else {
                $_nocache = 'false';
            }
            if (is_array($parameter['if condition']['var'])) {
                $this->php("if (!isset(\$_scope->{$var}) || !is_array(\$_scope->{$var}->value)) {")->newline()->indent();
                $this->php("\$this->_createLocalArrayVariable(" . $parameter['if condition']['var']['var'] . ", \$_smarty_tpl, {$_nocache});")->newline();
                $this->outdent()->php("}")->newline();
                $this->php("while (\$_scope->{$var}->value" . $parameter['if condition']['var']['smarty_internal_index'] . " = " . $parameter['if condition']['value'] . "){")->newline()->indent();
            } else {
                $this->php("if (!isset(\$_scope->{$var})) {")->newline()->indent();
                $this->php("\$_scope->{$var} = new Smarty_Variable(null, {$_nocache});")->newline();
                $this->outdent()->php("}")->newline();
                $this->php("while (\$_scope->{$var}->value = " . $parameter['if condition']['value'] . "){")->newline()->indent();
            }
        } else {
            $this->php("while ({$parameter['if condition']}){")->newline()->indent();
        }

        return $this->returnTagCode($compiler);
    }

}

/**
 * Smarty Internal Plugin Compile Whileclose Class
 *
 *
 * @package Compiler
 */
class Smarty_Internal_Compile_Whileclose extends Smarty_Internal_CompileBase
{

    /**
     * Compiles code for the {/while} tag
     *
     * @param array $args     array with attributes from parser
     * @param object $compiler compiler object
     * @return string compiled code
     */
    public function compile($args, $compiler)
    {
        // must endblock be nocache?
        if ($compiler->nocache) {
            $compiler->tag_nocache = true;
        }
        $compiler->nocache = $this->closeTag($compiler, array('while'));

        $this->iniTagCode($compiler);

        $this->outdent()->php("}")->newline();

        return $this->returnTagCode($compiler);
    }

}
