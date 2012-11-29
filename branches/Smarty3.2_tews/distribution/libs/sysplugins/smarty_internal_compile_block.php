<?php

/**
 * Smarty Internal Plugin Compile Block
 *
 * Compiles the {block}{/block} tags
 *
 * @package Smarty
 * @subpackage Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile Block Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Block extends Smarty_Internal_CompileBase {

    /**
     * block tag nesting level
     *
     * @var int
     */
    static public $block_nesting_level = 0;

    /**
     * current block name
     *
     * @var int
     */
    static public $current_block_name = '';

    /**
     * called {$smarty.block.child}
     *
     * @var bool
     */
    static public $called_child = false;

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
    public $option_flags = array('nocache', 'hide', 'append', 'prepend', 'overwrite');

    /**
     * Compiles code for the {block} tag
     *
     * @param array  $args     array with attributes from parser
     * @param object $compiler compiler object
     * @return boolean true
     */
    public function compile($args, $compiler) {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);

        $name = trim($_attr['name'], "'\"");

        $this->openTag($compiler, 'block', array($_attr, $compiler->buffer, $compiler->indentation, $compiler->nocache, self::$current_block_name, self::$called_child, $compiler->template->has_nocache_code, $compiler->lex->taglineno));
        if ($_attr['nocache'] == true) {
            $compiler->nocache = true;
        }
        $compiler->buffer = '';
        $compiler->indentation = 3;
        // maybe nocache because of nocache variables
        $compiler->nocache = $compiler->nocache | $compiler->tag_nocache;
        //nesting level
        self::$block_nesting_level++;
        // set current block name
        self::$current_block_name = $name;

        $compiler->template->has_nocache_code = false;
        $compiler->has_code = false;

        return true;
    }

}

/**
 * Smarty Internal Plugin Compile BlockClose Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Blockclose extends Smarty_Internal_CompileBase {

    /**
     * Compiles code for the {/block} tag
     *
     * @param array  $args     array with attributes from parser
     * @param object $compiler compiler object
     * @return string compiled code
     */
    public function compile($args, $compiler) {
        $compiler->has_code = true;
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        //nesting level
        Smarty_Internal_Compile_Block::$block_nesting_level--;

        $saved_data = $this->closeTag($compiler, array('block'));
        $name = trim($saved_data[0]['name'], "'\"");
        $block_id = $saved_data[1];
        // must endblock be nocache?
        if ($compiler->nocache) {
            $compiler->tag_nocache = true;
        }
        $compiler->nocache = $saved_data[3];

        // get resource info for traceback code
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

        $this->php("/* Line {$saved_data[7]} */")->newline();
        $this->php("function smarty_block_{$name}(\$_smarty_tpl) {")->newline()->indent();
        $this->php("ob_start();")->newline();
        $this->php("array_unshift(\$_smarty_tpl->trace_call_stack, array('{$resource}', {$saved_data[7]}, '{$compiler->template->source->type}'));")->newline();
        if ($compiler->template->has_nocache_code) {
            $this->php("echo '/*%%SmartyNocache%%*/array_unshift(\$_smarty_tpl->trace_call_stack, array(\'{$resource}\', {$saved_data[7]}, \'{$compiler->template->source->type}\'));/*/%%SmartyNocache%%*/';")->newline();
        }
        $compiler->block_functions_code[$name] = $this->buffer;

        $this->buffer = '';
        if ($compiler->template->has_nocache_code) {
            $this->php("echo '/*%%SmartyNocache%%*/array_shift(\$_smarty_tpl->trace_call_stack);/*/%%SmartyNocache%%*/';")->newline();
        }
        $this->php("array_shift(\$_smarty_tpl->trace_call_stack);")->newline();
        $this->php("return ob_get_clean();")->newline();
        $this->outdent()->php("}")->newline();

        $compiler->block_functions_code[$name] .= $compiler->buffer . $this->buffer;

        $compiler->buffer = $saved_data[1];
        $compiler->indentation = $saved_data[2];
        $this->iniTagCode($compiler);

        $compiler->block_functions[$name] = array();
        if (Smarty_Internal_Compile_Block::$called_child) {
            $compiler->block_functions[$name]['child'] = true;
        }
        if ($saved_data[0]['hide'] === true) {
            $compiler->block_functions[$name]['hide'] = true;
        }
        if ($saved_data[0]['prepend'] === true) {
            $compiler->block_functions[$name]['prepend'] = true;
        }
        if ($saved_data[0]['append'] === true) {
            $compiler->block_functions[$name]['append'] = true;
        }
        if ($saved_data[0]['overwrite'] === true) {
            $compiler->block_functions[$name]['overwrite'] = true;
        }
        if (Smarty_Internal_Compile_Block::$block_nesting_level == 0) {
            $this->php("\$this->block_functions['$name']['valid'] = true;")->newline();
            $this->php("if (!\$_smarty_tpl->is_child) {")->newline()->indent();
        }
        $this->php("echo \$this->_fetch_block_child_template (\$_smarty_tpl, '{$name}');")->newline();
        if (Smarty_Internal_Compile_Block::$block_nesting_level == 0) {
            $this->outdent()->php("}")->newline();
        }

        // restore current block name
        Smarty_Internal_Compile_Block::$current_block_name = $saved_data[4];
        Smarty_Internal_Compile_Block::$called_child = $saved_data[5];

        $compiler->template->has_nocache_code = $compiler->template->has_nocache_code | $saved_data[6];

        $compiler->has_code = true;

        return $this->returnTagCode($compiler);
    }

}

/**
 * Smarty Internal Plugin Compile Block Parent Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Private_Block_Parent extends Smarty_Internal_CompileBase {

    /**
     * Compiles code for the {$smart.block.parent} tag
     *
     * @param array  $args     array with attributes from parser
     * @param object $compiler compiler object
     * @return string compiled code
     */
    public function compile($args, $compiler) {
        $compiler->has_code = true;
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        $name = Smarty_Internal_Compile_Block::$current_block_name;

        $this->iniTagCode($compiler);

        $this->raw("\$this->_fetch_block_parent_template (\$_smarty_tpl, '{$name}')");

        return $this->returnTagCode($compiler);
    }

}

/**
 * Smarty Internal Plugin Compile Block Parent Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Private_Block_Child extends Smarty_Internal_CompileBase {

    /**
     * Compiles code for the {$smart.block.child} tag
     *
     * @param array  $args     array with attributes from parser
     * @param object $compiler compiler object
     * @return string compiled code
     */
    public function compile($args, $compiler) {
        $compiler->has_code = true;
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        $name = Smarty_Internal_Compile_Block::$current_block_name;
        Smarty_Internal_Compile_Block::$called_child = true;

        $this->iniTagCode($compiler);

        $this->raw("\$this->_fetch_block_child_template (\$_smarty_tpl->parent, '{$name}')");

        return $this->returnTagCode($compiler);
    }

}
