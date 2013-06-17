<?php

/**
 * Smarty Internal Plugin Compile Block
 *
 * Compiles the {block}{/block} tags
 *
 *
 * @package Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile Block Class
 *
 *
 * @package Compiler
 */
class Smarty_Internal_Compile_Block extends Smarty_Internal_CompileBase
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

        $this->openTag($compiler, 'block', array($_attr, $compiler->template_code, $compiler->nocache, $name, $compiler->has_nocache_code, $compiler->lex->taglineno));
        if ($_attr['nocache'] == true && $compiler->caching) {
            $compiler->nocache = true;
        }
        $compiler->template_code = new Smarty_Internal_Code(3);
        // maybe nocache because of nocache variables
        $compiler->nocache = $compiler->nocache | $compiler->tag_nocache;

        //nesting level
        $compiler->block_nesting_level++;
        if ($compiler->block_nesting_level == 1) {
            $int_name = $name;
        } else {
            $compiler->block_name_index++;
            $int_name = $name . '_' . $compiler->block_name_index;
        }
        array_unshift($compiler->block_nesting_info, array('name' => $name, 'int_name' => $int_name, 'function' => '_' . $int_name . '_Interitance_Block_' . str_replace('.', '_', uniqid('', true))));


        $compiler->has_nocache_code = false;
        $compiler->has_code = false;

        return true;
    }

}

/**
 * Smarty Internal Plugin Compile BlockClose Class
 *
 *
 * @package Compiler
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

        $saved_data = $this->closeTag($compiler, array('block'));
        $name = trim($saved_data[0]['name'], "'\"");
        // must endblock be nocache?
        if ($compiler->nocache) {
            $compiler->tag_nocache = $compiler->nocache && $compiler->caching;
        }
        $compiler->nocache = $saved_data[2];

        // get resource info for traceback code
        if ($compiler->tpl_obj->source->type == 'eval' || $compiler->tpl_obj->source->type == 'string') {
            $resource = $compiler->tpl_obj->source->type;
        } else {
            $resource = $compiler->tpl_obj->template_resource;
            // sanitize extends resource
            if (strpos($resource, 'extends:') !== false) {
                $start = strpos($resource, ':');
                $end = strpos($resource, '|');
                $resource = substr($resource, $start + 1, $end - $start - 1);
            }
        }

        if ($saved_data[0]['hide']) $compiler->block_nesting_info[0]['hide'] = true;
        if ($saved_data[0]['prepend']) $compiler->block_nesting_info[0]['prepend'] = true;
        if ($saved_data[0]['append']) $compiler->block_nesting_info[0]['append'] = true;
        if ($saved_data[0]['overwrite']) $compiler->block_nesting_info[0]['overwrite'] = true;

        $block_code = new Smarty_Internal_Code(2);
        $block_code->php("public function " . $compiler->block_nesting_info[0]['function'] . " (\$_smarty_tpl, \$current_tpl) {")->newline()->indent();
        $block_code->php("ob_start();")->newline();
        $block_code->php("/* Line {$saved_data[5]} */")->newline();
        $block_code->php("array_unshift(\$_smarty_tpl->trace_call_stack, array('{$resource}', {$saved_data[5]}, '{$compiler->tpl_obj->source->type}'));")->newline();
        if ($compiler->caching) {
            $block_code->php("echo '/*%%SmartyNocache%%*/array_unshift(\$_smarty_tpl->trace_call_stack, array(\'{$resource}\', {$saved_data[5]}, \'{$compiler->tpl_obj->source->type}\'));/*/%%SmartyNocache%%*/';")->newline();
        }
        $block_code->buffer .= $compiler->template_code->buffer;
        if ($compiler->caching) {
            $block_code->php("echo '/*%%SmartyNocache%%*/array_shift(\$_smarty_tpl->trace_call_stack);/*/%%SmartyNocache%%*/';")->newline();
        }
        $block_code->php("array_shift(\$_smarty_tpl->trace_call_stack);")->newline();
        $block_code->php("return ob_get_clean();")->newline();

        $block_code->outdent()->php('}')->newline(3);


        $compiler->inheritance_blocks_code[] .= $block_code->buffer;

        $compiler->template_code = $saved_data[1];
        $this->iniTagCode($compiler);

        $int_name = $compiler->block_nesting_info[0]['int_name'];
        unset($compiler->block_nesting_info[0]['int_name']);

        if ($compiler->isInheritanceChild && $compiler->block_nesting_level == 1) {
            if ($compiler->tag_nocache) {
                $compiler->postfix_code[] = "\$this->inheritance_blocks['$int_name']['valid'] = true;\n";
            } else {
                $this->php("\$this->inheritance_blocks['$int_name']['valid'] = true;")->newline();
            }
        } else {
            if ($compiler->tag_nocache) {
//                $compiler->postfix_code[] = "\$this->inheritance_blocks['$int_name']['valid'] = true;\n";
                $compiler->postfix_code[] = "echo \$this->_getInheritanceBlock ('{$int_name}', \$_smarty_tpl, 1);\n";
            } else {
//                $this->php("\$this->inheritance_blocks['$int_name']['valid'] = true;")->newline();
                $this->php("echo \$this->_getInheritanceBlock ('{$int_name}', \$_smarty_tpl, 0);")->newline();
            }
        }
        if ($compiler->block_nesting_level > 1) {
            $compiler->block_nesting_info[1]['subblocks'][] = $int_name;
        }
        $compiler->inheritance_blocks[$int_name] = $compiler->block_nesting_info[0];
        array_shift($compiler->block_nesting_info);
        $compiler->block_nesting_level--;

        $compiler->has_nocache_code = $compiler->has_nocache_code | $saved_data[4];

        $compiler->has_code = true;

        return $this->returnTagCode($compiler);
    }

}

/**
 * Smarty Internal Plugin Compile Block Parent Class
 *
 *
 * @package Compiler
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

        $compiler->block_nesting_info[0]['calls_parent'] = true;

        $this->iniTagCode($compiler);

        $this->raw("\$this->_getInheritanceParentBlock ('{$compiler->block_nesting_info[0]['int_name']}', \$_smarty_tpl)");

        return $this->returnTagCode($compiler);
    }

}

/**
 * Smarty Internal Plugin Compile Block Parent Class
 *
 *
 * @package Compiler
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
        $this->iniTagCode($compiler);

        $compiler->block_nesting_info[0]['calls_child'] = true;

        $this->raw("\$this->inheritance_blocks['{$compiler->block_nesting_info[0]['int_name']}']['child_content']");
// TODO  remove this
//       $this->raw("\$this->_getInheritanceChildBlock ('{$compiler->block_nesting_info[0]['int_name']}', \$_smarty_tpl, 0, \$current_tpl)");

        return $this->returnTagCode($compiler);
    }

}
