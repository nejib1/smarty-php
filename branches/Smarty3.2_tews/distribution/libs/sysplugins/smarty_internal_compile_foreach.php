<?php

/**
 * Smarty Internal Plugin Compile Foreach
 *
 * Compiles the {foreach} {foreachelse} {/foreach} tags
 *
 * @package Smarty
 * @subpackage Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile Foreach Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Foreach extends Smarty_Internal_CompileBase {

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $required_attributes = array('from', 'item');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $optional_attributes = array('name', 'key');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $shorttag_order = array('from', 'item', 'key', 'name');

    /**
     * Compiles code for the {foreach} tag
     *
     * @param array  $args      array with attributes from parser
     * @param object $compiler  compiler object
     * @param array  $parameter array with compilation parameter
     * @return string compiled code
     */
    public function compile($args, $compiler, $parameter) {
        $tpl = $compiler->template;
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);

        $from = $_attr['from'];
        $item = trim($_attr['item'], '\'"');
        if ("\$_smarty_tpl->tpl_vars->{$item}" == $from) {
            $compiler->trigger_template_error("item variable {$item} may not be the same variable as at 'from'", $compiler->lex->taglineno);
        }

        if (isset($_attr['key'])) {
            $key = trim($_attr['key'], '\'"');
        } else {
            $key = null;
        }

        $this->openTag($compiler, 'foreach', array('foreach', $compiler->nocache, $item, $key));
        // maybe nocache because of nocache variables
        $compiler->nocache = $compiler->nocache | $compiler->tag_nocache;

        $this->iniTagCode($compiler);

        if (isset($_attr['name'])) {
            $name = $_attr['name'];
            $has_name = true;
            $SmartyVarName = '$smarty.foreach.' . trim($name, '\'"') . '.';
        } else {
            $name = null;
            $has_name = false;
        }
        $ItemVarName = '$' . $item . '@';
        // evaluates which Smarty variables and properties have to be computed
        if ($has_name) {
            $usesSmartyFirst = strpos($tpl->source->content, $SmartyVarName . 'first') !== false;
            $usesSmartyLast = strpos($tpl->source->content, $SmartyVarName . 'last') !== false;
            $usesSmartyIndex = strpos($tpl->source->content, $SmartyVarName . 'index') !== false;
            $usesSmartyIteration = strpos($tpl->source->content, $SmartyVarName . 'iteration') !== false;
            $usesSmartyShow = strpos($tpl->source->content, $SmartyVarName . 'show') !== false;
            $usesSmartyTotal = strpos($tpl->source->content, $SmartyVarName . 'total') !== false;
        } else {
            $usesSmartyFirst = false;
            $usesSmartyLast = false;
            $usesSmartyTotal = false;
            $usesSmartyShow = false;
        }

        $usesPropFirst = $usesSmartyFirst || strpos($tpl->source->content, $ItemVarName . 'first') !== false;
        $usesPropLast = $usesSmartyLast || strpos($tpl->source->content, $ItemVarName . 'last') !== false;
        $usesPropIndex = $usesPropFirst || strpos($tpl->source->content, $ItemVarName . 'index') !== false;
        $usesPropIteration = $usesPropLast || strpos($tpl->source->content, $ItemVarName . 'iteration') !== false;
        $usesPropShow = strpos($tpl->source->content, $ItemVarName . 'show') !== false;
        $usesPropTotal = $usesSmartyTotal || $usesSmartyShow || $usesPropShow || $usesPropLast || strpos($tpl->source->content, $ItemVarName . 'total') !== false;
        // generate output code
        $this->php("\$_smarty_tpl->tpl_vars->{$item} = array('loop' => false);")->newline();
        $this->php("\$_from = $from;")->newline();
        $this->php("if (!is_array(\$_from) && !is_object(\$_from)) {")->newline()->indent()->php("settype(\$_from, 'array');")->newline()->outdent()->php("}")->newline();
        if ($usesPropTotal) {
            $this->php("\$_smarty_tpl->tpl_vars->{$item}['total'] = \$this->_count(\$_from);")->newline();
        }
        if ($usesPropIteration) {
            $this->php("\$_smarty_tpl->tpl_vars->{$item}['iteration'] = 0;")->newline();
        }
        if ($usesPropIndex) {
            $this->php("\$_smarty_tpl->tpl_vars->{$item}['index'] = -1;")->newline();
        }
        if ($usesPropShow) {
            $this->php("\$_smarty_tpl->tpl_vars->{$item}['show'] = (\$_smarty_tpl->tpl_vars->{$item}['total'] > 0);")->newline();
        }
        if ($has_name) {
            if ($usesSmartyTotal) {
                $this->php("\$_smarty_tpl->tpl_vars->smarty['foreach'][{$name}]['total'] = \$_smarty_tpl->tpl_vars->{$item}['total'];")->newline();
            }
            if ($usesSmartyIteration) {
                $this->php("\$_smarty_tpl->tpl_vars->smarty['foreach'][{$name}]['iteration'] = 0;")->newline();
            }
            if ($usesSmartyIndex) {
                $this->php("\$_smarty_tpl->tpl_vars->smarty['foreach'][{$name}]['index'] = -1;")->newline();
            }
            if ($usesSmartyShow) {
                $this->php("\$_smarty_tpl->tpl_vars->smarty['foreach'][{$name}]['show']=(\$_smarty_tpl->tpl_vars->{$item}['total'] > 0);")->newline();
            }
        }
        if ($key != null) {
            $this->php("\$_smarty_tpl->tpl_vars->{$key} = array();")->newline();
        }
        $this->php("foreach (\$_from as \$_smarty_tpl->tpl_vars->{$item}['key'] => \$_smarty_tpl->tpl_vars->{$item}['value']){")->indent()->newline();
        $this->php("\$_smarty_tpl->tpl_vars->{$item}['loop'] = true;")->newline();
        if ($key != null) {
            $this->php("\$_smarty_tpl->tpl_vars->{$key}['value'] = \$_smarty_tpl->tpl_vars->{$item}['key'];")->newline();
        }
        if ($usesPropIteration) {
            $this->php("\$_smarty_tpl->tpl_vars->{$item}['iteration']++;")->newline();
        }
        if ($usesPropIndex) {
            $this->php("\$_smarty_tpl->tpl_vars->{$item}['index']++;")->newline();
        }
        if ($usesPropFirst) {
            $this->php("\$_smarty_tpl->tpl_vars->{$item}['first'] = \$_smarty_tpl->tpl_vars->{$item}['index'] === 0;")->newline();
        }
        if ($usesPropLast) {
            $this->php("\$_smarty_tpl->tpl_vars->{$item}['last'] = \$_smarty_tpl->tpl_vars->{$item}['iteration'] === \$_smarty_tpl->tpl_vars->{$item}['total'];")->newline();
        }
        if ($has_name) {
            if ($usesSmartyFirst) {
                $this->php("\$_smarty_tpl->tpl_vars->smarty['foreach'][{$name}]['first'] = \$_smarty_tpl->tpl_vars->{$item}['first'];")->newline();
            }
            if ($usesSmartyIteration) {
                $this->php("\$_smarty_tpl->tpl_vars->smarty['foreach'][{$name}]['iteration']++;")->newline();
            }
            if ($usesSmartyIndex) {
                $this->php("\$_smarty_tpl->tpl_vars->smarty['foreach'][{$name}]['index']++;")->newline();
            }
            if ($usesSmartyLast) {
                $this->php("\$_smarty_tpl->tpl_vars->smarty['foreach'][{$name}]['last'] = \$_smarty_tpl->tpl_vars->{$item}['last'];")->newline();
            }
        }
        return $this->returnTagCode($compiler);
    }

}

/**
 * Smarty Internal Plugin Compile Foreachelse Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Foreachelse extends Smarty_Internal_CompileBase {

    /**
     * Compiles code for the {foreachelse} tag
     *
     * @param array  $args array with attributes from parser
     * @param object $compiler compiler object
     * @param array  $parameter array with compilation parameter
     * @return string compiled code
     */
    public function compile($args, $compiler, $parameter) {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);

        list($openTag, $nocache, $item, $key) = $this->closeTag($compiler, array('foreach'));
        $this->openTag($compiler, 'foreachelse', array('foreachelse', $nocache, $item, $key));

        $this->iniTagCode($compiler);

        $this->outdent()->php("}")->newline();
        $this->php("if (!\$_smarty_tpl->tpl_vars->{$item}['loop']) {")->newline()->indent();

        return $this->returnTagCode($compiler);
    }

}

/**
 * Smarty Internal Plugin Compile Foreachclose Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Foreachclose extends Smarty_Internal_CompileBase {

    /**
     * Compiles code for the {/foreach} tag
     *
     * @param array  $args      array with attributes from parser
     * @param object $compiler  compiler object
     * @param array  $parameter array with compilation parameter
     * @return string compiled code
     */
    public function compile($args, $compiler, $parameter) {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        // must endblock be nocache?
        if ($compiler->nocache) {
            $compiler->tag_nocache = true;
        }

        list($openTag, $compiler->nocache, $item, $key) = $this->closeTag($compiler, array('foreach', 'foreachelse'));

        $this->iniTagCode($compiler);

        $this->outdent()->php("}")->newline();

        return $this->returnTagCode($compiler);
    }

}
