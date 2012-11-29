<?php

/**
 * Smarty Internal Plugin Compile Section
 *
 * Compiles the {section} {sectionelse} {/section} tags
 *
 * @package Smarty
 * @subpackage Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile Section Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Section extends Smarty_Internal_CompileBase {

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $required_attributes = array('name', 'loop');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $shorttag_order = array('name', 'loop');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $optional_attributes = array('start', 'step', 'max', 'show');

    /**
     * Compiles code for the {section} tag
     *
     * @param array  $args     array with attributes from parser
     * @param object $compiler compiler object
     * @return string compiled code
     */
    public function compile($args, $compiler) {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);

        $this->openTag($compiler, 'section', array('section', $compiler->nocache));
        // maybe nocache because of nocache variables
        $compiler->nocache = $compiler->nocache | $compiler->tag_nocache;
        $this->iniTagCode($compiler);

        $section_name = $_attr['name'];

        $this->php("if (isset(\$_smarty_tpl->tpl_vars->smarty['section'][$section_name])) {")->newline()->indent();
        $this->php("unset(\$_smarty_tpl->tpl_vars->smarty['section'][$section_name]);")->newline();
        $this->outdent()->php("}")->newline();

        $section_props = "\$_smarty_tpl->tpl_vars->smarty['section'][$section_name]";

        foreach ($_attr as $attr_name => $attr_value) {
            switch ($attr_name) {
                case 'loop':
                    $this->php("{$section_props}['loop'] = is_array(\$_loop=$attr_value) ? count(\$_loop) : max(0, (int)\$_loop);unset(\$_loop);")->newline();
                    break;

                case 'show':
                    if (is_bool($attr_value))
                        $show_attr_value = $attr_value ? 'true' : 'false';
                    else
                        $show_attr_value = "(bool)$attr_value";
                    $this->php("{$section_props}['show'] = $show_attr_value;")->newline();
                    break;

                case 'name':
                    $this->php("{$section_props}['$attr_name'] = $attr_value;")->newline();
                    break;

                case 'max':
                case 'start':
                    $this->php("{$section_props}['$attr_name'] = (int)$attr_value;")->newline();
                    break;

                case 'step':
                    $this->php("{$section_props}['$attr_name'] = ((int)$attr_value) == 0 ? 1 : (int)$attr_value;")->newline();
                    break;
            }
        }

        if (!isset($_attr['show'])) {
            $this->php("{$section_props}['show'] = true;")->newline();
        }

        if (!isset($_attr['loop'])) {
            $this->php("{$section_props}['loop'] = 1;")->newline();
        }

        if (!isset($_attr['max'])) {
            $this->php("{$section_props}['max'] = {$section_props}['loop'];")->newline();
        } else {
            $this->php("if ({$section_props}['max'] < 0) {")->newline()->indent();
            $this->php("{$section_props}['max'] = {$section_props}['loop'];")->newline();
            $this->outdent()->php("}")->newline();
        }

        if (!isset($_attr['step'])) {
            $this->php("{$section_props}['step'] = 1;")->newline();
        }

        if (!isset($_attr['start'])) {
            $this->php("{$section_props}['start'] = {$section_props}['step'] > 0 ? 0 : {$section_props}['loop']-1;")->newline();
        } else {
            $this->php("if ({$section_props}['start'] < 0) {")->newline()->indent();
            $this->php("{$section_props}['start'] = max({$section_props}['step'] > 0 ? 0 : -1, {$section_props}['loop'] + {$section_props}['start']);")->newline();
            $this->outdent()->php("} else {")->newline()->indent();
            $this->php("{$section_props}['start'] = min({$section_props}['start'], {$section_props}['step'] > 0 ? {$section_props}['loop'] : {$section_props}['loop']-1);")->newline();
            $this->outdent()->php("}")->newline();
        }

        $this->php("if ({$section_props}['show']) {")->newline()->indent();
        if (!isset($_attr['start']) && !isset($_attr['step']) && !isset($_attr['max'])) {
            $this->php("{$section_props}['total'] = {$section_props}['loop'];")->newline();
        } else {
            $this->php("{$section_props}['total'] = min(ceil(({$section_props}['step'] > 0 ? {$section_props}['loop'] - {$section_props}['start'] : {$section_props}['start']+1)/abs({$section_props}['step'])), {$section_props}['max']);")->newline();
        }
        $this->php("if ({$section_props}['total'] == 0) {")->newline()->indent();
        $this->php("{$section_props}['show'] = false;")->newline();
        $this->outdent()->php("}")->newline();
        $this->outdent()->php("} else {")->newline()->indent();
        $this->php("{$section_props}['total'] = 0;")->newline();
        $this->outdent()->php("}")->newline();

        $this->php("if ({$section_props}['show']) {")->newline()->indent();
        $this->php("for ({$section_props}['index'] = {$section_props}['start'], {$section_props}['iteration'] = 1; {$section_props}['iteration'] <= {$section_props}['total']; {$section_props}['index'] += {$section_props}['step'], {$section_props}['iteration']++) {")->newline()->indent();
        $this->php("{$section_props}['rownum'] = {$section_props}['iteration'];")->newline();
        $this->php("{$section_props}['index_prev'] = {$section_props}['index'] - {$section_props}['step'];")->newline();
        $this->php("{$section_props}['index_next'] = {$section_props}['index'] + {$section_props}['step'];")->newline();
        $this->php("{$section_props}['first'] = ({$section_props}['iteration'] == 1);")->newline();
        $this->php("{$section_props}['last']  = ({$section_props}['iteration'] == {$section_props}['total']);")->newline();

        return $this->returnTagCode($compiler);
    }

}

/**
 * Smarty Internal Plugin Compile Sectionelse Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Sectionelse extends Smarty_Internal_CompileBase {

    /**
     * Compiles code for the {sectionelse} tag
     *
     * @param array  $args     array with attributes from parser
     * @param object $compiler compiler object
     * @return string compiled code
     */
    public function compile($args, $compiler) {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);

        list($openTag, $nocache) = $this->closeTag($compiler, array('section'));
        $this->openTag($compiler, 'sectionelse', array('sectionelse', $nocache));

        $this->iniTagCode($compiler);

        $this->outdent()->php("}")->newline();
        $this->outdent()->php("} else {")->newline()->indent();

        return $this->returnTagCode($compiler);
    }

}

/**
 * Smarty Internal Plugin Compile Sectionclose Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Sectionclose extends Smarty_Internal_CompileBase {

    /**
     * Compiles code for the {/section} tag
     *
     * @param array  $args     array with attributes from parser
     * @param object $compiler compiler object
     * @return string compiled code
     */
    public function compile($args, $compiler) {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);

        // must endblock be nocache?
        if ($compiler->nocache) {
            $compiler->tag_nocache = true;
        }

        list($openTag, $compiler->nocache) = $this->closeTag($compiler, array('section', 'sectionelse'));

        $this->iniTagCode($compiler);

        $this->outdent()->php("}")->newline();
        if ($openTag != 'sectionelse') {
            $this->outdent()->php("}")->newline();
        }

        return $this->returnTagCode($compiler);
    }

}
