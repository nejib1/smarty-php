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
class Smarty_Internal_Compile_Block extends Smarty_Internal_CompileBase
{

    /**
     * block tag nesting level
     *
     * @var int
     */
    static public $block_nesting_level = 0;

    /**
     * current block name
     *
     * @var string
     */
    static public $current_block_name = '';

    /**
     *  route block name
     *
     * @var int
     */
    static public $root_block_name = '';

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
     * @param array $args     array with attributes from parser
     * @param object $compiler compiler object
     * @return boolean true
     */
    public function compile($args, $compiler)
    {
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
        // set current block name
        self::$current_block_name = $name;
        // set root block name
        if (self::$block_nesting_level == 0) {
            self::$root_block_name = $name;
        }
        //nesting level
        self::$block_nesting_level++;

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
class Smarty_Internal_Compile_Blockclose extends Smarty_Internal_CompileBase
{

    /**
     * Compiles code for the {/block} tag
     *
     * @param array $args     array with attributes from parser
     * @param object $compiler compiler object
     * @return string compiled code
     */
    public function compile($args, $compiler)
    {
        $compiler->has_code = true;
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        // set inheritance flags
        $compiler->isInheritance = true;

        //nesting level
        Smarty_Internal_Compile_Block::$block_nesting_level--;

        $saved_data = $this->closeTag($compiler, array('block'));
        $name = trim($saved_data[0]['name'], "'\"");
        // must endblock be nocache?
        if ($compiler->nocache) {
            $compiler->tag_nocache = $compiler->nocache && $compiler->template->caching;
        }
        $compiler->nocache = $saved_data[3];

        // get resource info for traceback code
        if ($compiler->template->source->type == 'eval' || $compiler->template->source->type == 'string') {
            $resource = $compiler->template->source->type;
        } else {
            $resource = $compiler->template->template_resource;
            // sanitize extends resource
            if (strpos($resource, 'extends:') !== false) {
                $start = strpos($resource, ':');
                $end = strpos($resource, '|');
                $resource = substr($resource, $start + 1, $end - $start - 1);
            }
        }
        $function_name = "block_{$name}_" . str_replace('.', '_', uniqid('', true));
        $this->buffer = '';
        $this->indentation = 2;
        $this->no_indent = false;

        $this->php("/* Line {$saved_data[7]} */")->newline();
        $this->php("function {$function_name}(\$_smarty_tpl, \$template_stack, \$scope_tpl) {")->newline()->indent();
        $this->php("ob_start();")->newline();
        $this->php("array_unshift(\$_smarty_tpl->trace_call_stack, array('{$resource}', {$saved_data[7]}, '{$compiler->template->source->type}'));")->newline();
        if ($compiler->template->caching) {
            $this->php("echo '/*%%SmartyNocache%%*/array_unshift(\$_smarty_tpl->trace_call_stack, array(\'{$resource}\', {$saved_data[7]}, \'{$compiler->template->source->type}\'));/*/%%SmartyNocache%%*/';")->newline();
        }
        $compiler->inheritance_blocks_code[$name] = $this->buffer;

        $this->buffer = '';
        if ($compiler->template->caching) {
            $this->php("echo '/*%%SmartyNocache%%*/array_shift(\$_smarty_tpl->trace_call_stack);/*/%%SmartyNocache%%*/';")->newline();
        }
        $this->php("array_shift(\$_smarty_tpl->trace_call_stack);")->newline();
        $this->php("return ob_get_clean();")->newline();
        $this->outdent()->php("}")->newline();

        $compiler->inheritance_blocks_code[$name] .= $compiler->buffer . $this->buffer;

        $compiler->buffer = $saved_data[1];
        $compiler->indentation = $saved_data[2];
        $this->iniTagCode($compiler);

        $compiler->inheritance_blocks[$name]['function'] = $function_name;
        if (Smarty_Internal_Compile_Block::$called_child) {
            $compiler->inheritance_blocks[$name]['child'] = true;
        }
        if ($saved_data[0]['hide'] === true) {
            $compiler->inheritance_blocks[$name]['hide'] = true;
        }
        if ($saved_data[0]['prepend'] === true) {
            $compiler->inheritance_blocks[$name]['prepend'] = true;
        }
        if ($saved_data[0]['append'] === true) {
            $compiler->inheritance_blocks[$name]['append'] = true;
        }
        if ($saved_data[0]['overwrite'] === true) {
            $compiler->inheritance_blocks[$name]['overwrite'] = true;
        }
        if ($compiler->template->caching && $compiler->tag_nocache) {
            $compiler->inheritance_blocks[$name]['nocache'] = true;
        }
        if (Smarty_Internal_Compile_Block::$block_nesting_level > 0) {
            $compiler->inheritance_blocks[Smarty_Internal_Compile_Block::$root_block_name]['subblock'][] = $name;
        }

        $this->php("\$this->inheritance_blocks['$name']['valid'] = true;")->newline();
        if ($compiler->tag_nocache) {
            $compiler->postfix_code[] = "\$this->inheritance_blocks['$name']['valid'] = true;\n";
        }
        if (!$compiler->isInheritanceChild) {
            if (!$compiler->tag_nocache) {
                $this->php("echo \$this->_getBlock (\$_smarty_tpl, '{$name}', \$_smarty_tpl, 0);")->newline();
            } else {
                $compiler->postfix_code[] = "echo \$this->_getBlock (\$_smarty_tpl, '{$name}', \$_smarty_tpl, 1);\n";
            }
        }
        if (Smarty_Internal_Compile_Block::$block_nesting_level > 0 && $compiler->isInheritanceChild) {
            $this->php("echo \$this->_getBlock (\$_smarty_tpl, '{$name}', \$_smarty_tpl, 0);")->newline();
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
class Smarty_Internal_Compile_Private_Block_Parent extends Smarty_Internal_CompileBase
{

    /**
     * Compiles code for the {$smart.block.parent} tag
     *
     * @param array $args     array with attributes from parser
     * @param object $compiler compiler object
     * @return string compiled code
     */
    public function compile($args, $compiler)
    {
        $compiler->has_code = true;
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        $name = Smarty_Internal_Compile_Block::$current_block_name;

        $this->iniTagCode($compiler);

        $this->raw("\$this->_fetch_block_parent_template ('{$name}', \$template_stack, \$scope_tpl)");

        return $this->returnTagCode($compiler);
    }

}

/**
 * Smarty Internal Plugin Compile Block Parent Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Private_Block_Child extends Smarty_Internal_CompileBase
{

    /**
     * Compiles code for the {$smart.block.child} tag
     *
     * @param array $args     array with attributes from parser
     * @param object $compiler compiler object
     * @return string compiled code
     */
    public function compile($args, $compiler)
    {
        $compiler->has_code = true;
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        $name = Smarty_Internal_Compile_Block::$current_block_name;
        Smarty_Internal_Compile_Block::$called_child = true;

        $this->iniTagCode($compiler);

        $this->raw("\$this->_fetchBlockChildTemplate (\$_smarty_tpl->parent, '{$name}', \$scope_tpl)");

        return $this->returnTagCode($compiler);
    }

}
