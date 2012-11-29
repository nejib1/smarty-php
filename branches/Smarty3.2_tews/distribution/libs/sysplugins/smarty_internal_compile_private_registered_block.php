<?php

/**
 * Smarty Internal Plugin Compile Registered Block
 *
 * Compiles code for the execution of a registered block function
 *
 * @package Smarty
 * @subpackage Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile Registered Block Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Private_Registered_Block extends Smarty_Internal_CompileBase {

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $optional_attributes = array('_any');

    /**
     * Compiles code for the execution of a block function
     *
     * @param array  $args      array with attributes from parser
     * @param object $compiler  compiler object
     * @param array  $parameter array with compilation parameter
     * @param string $tag       name of block function
     * @return string compiled code
     */
    public function compile($args, $compiler, $parameter, $tag) {
        if (!isset($tag[5]) || substr($tag, -5) != 'close') {
            // opening tag of block plugin
            // check and get attributes
            $_attr = $this->getAttributes($compiler, $args);
            if ($_attr['nocache']) {
                $compiler->tag_nocache = true;
            }
            unset($_attr['nocache']);
            if (isset($compiler->template->registered_plugins[Smarty::PLUGIN_BLOCK][$tag])) {
                $tag_info = $compiler->template->registered_plugins[Smarty::PLUGIN_BLOCK][$tag];
            } else {
                $tag_info = $compiler->default_handler_plugins[Smarty::PLUGIN_BLOCK][$tag];
            }
            $function = $tag_info[0];
            // convert attributes into parameter string
            $par_string = $this->getPluginParameterString($function, $_attr, $compiler, true, $tag_info[2]);

            $this->openTag($compiler, $tag, array($par_string, $compiler->nocache));
            // maybe nocache because of nocache variables or nocache plugin
            $compiler->nocache = !$tag_info[1] | $compiler->nocache | $compiler->tag_nocache;
            // compile code
            $this->iniTagCode($compiler);

            if (is_array($par_string)) {
                $this->php("\$_smarty_tpl->_tag_stack[] = array('{$tag}', {$par_string['par']});")->newline();
                $this->php("\$_block_repeat=true;")->newline();
                // old style with params array
                if ($function instanceof Closure) {
                    $this->php("echo \$_smarty_tpl->registered_plugins['block']['{$tag}'][0]({$par_string['par']}, null, {$par_string['obj']}, \$_block_repeat);")->newline();
                } else if (!is_array($function)) {
                    $this->php("echo {$function}({$par_string['par']}, null, {$par_string['obj']}, \$_block_repeat);")->newline();
                } else if (is_object($function[0])) {
                    $this->php("echo \$_smarty_tpl->registered_plugins['block']['{$tag}'][0][0]->{$function[1]}({$par_string['par']}, null, {$par_string['obj']}, \$_block_repeat);")->newline();
                } else {
                    $this->php("echo {$function[0]}::{$function[1]}({$par_string['par']}, null, {$par_string['obj']}, \$_block_repeat);")->newline();
                }
                $this->php("while (\$_block_repeat) {")->newline()->indent();
                $this->php("ob_start();")->newline();
            } else {
                // new style with real parameter
                $par_string = str_replace('__content__', 'null', $par_string);
                $this->php("\$_smarty_tpl->_tag_stack[] = array('{$tag}', {$par_string});")->newline();
                $this->php("\$_block_repeat=true;")->newline();
                if ($function instanceof Closure) {
                    $this->php("echo \$_smarty_tpl->registered_plugins['block']['{$tag}'][0]({$par_string});")->newline();
                } else if (!is_array($function)) {
                    $this->php("echo {$function}({$par_string});")->newline();
                } else if (is_object($function[0])) {
                    $this->php("echo \$_smarty_tpl->registered_plugins['block']['{$tag}'][0][0]->{$function[1]}({$par_string});")->newline();
                } else {
                    $this->php("echo {$function[0]}::{$function[1]}({$par_string});")->newline();
                }
            }
        } else {
            // must endblock be nocache?
            if ($compiler->nocache) {
                $compiler->tag_nocache = true;
            }
            $base_tag = substr($tag, 0, -5);
            // closing tag of block plugin, restore nocache
            list($par_string, $compiler->nocache) = $this->closeTag($compiler, $base_tag);
            // This tag does create output
            $compiler->has_output = true;
            if (isset($compiler->template->registered_plugins[Smarty::PLUGIN_BLOCK][$base_tag])) {
                $function = $compiler->template->registered_plugins[Smarty::PLUGIN_BLOCK][$base_tag][0];
            } else {
                $function = $compiler->default_handler_plugins[Smarty::PLUGIN_BLOCK][$base_tag][0];
            }
            // compile code
            $this->iniTagCode($compiler);

            $this->php("\$_block_content = ob_get_clean();")->newline();
            $this->php("\$_block_repeat=false;")->newline();
            if (isset($parameter['modifier_list'])) {
                $this->php("ob_start();")->newline();
            }
            if (is_array($par_string)) {
                // old style with params array
                if ($function instanceof Closure) {
                    $this->php("echo \$_smarty_tpl->registered_plugins['block']['{$base_tag}'][0]({$par_string['par']}, \$_block_content, {$par_string['obj']}, \$_block_repeat);")->newline();
                } else if (!is_array($function)) {
                    $this->php("echo {$function}({$par_string['par']}, \$_block_content, {$par_string['obj']}, \$_block_repeat);")->newline();
                } else if (is_object($function[0])) {
                    $this->php("echo \$_smarty_tpl->registered_plugins['block']['{$base_tag}'][0][0]->{$function[1]}({$par_string['par']}, \$_block_content, {$par_string['obj']}, \$_block_repeat);")->newline();
                } else {
                    $this->php("echo {$function[0]}::{$function[1]}({$par_string['par']}, \$_block_content, {$par_string['obj']}, \$_block_repeat);")->newline();
                }
            } else {
                // new style witn real parameter
                $par_string = str_replace('__content__', '$_block_content', $par_string);
                if ($function instanceof Closure) {
                    $this->php("echo \$_smarty_tpl->registered_plugins['block']['{$base_tag}'][0]({$par_string});")->newline();
                } else if (!is_array($function)) {
                    $this->php("echo {$function}({$par_string});")->newline();
                } else if (is_object($function[0])) {
                    $this->php("echo \$_smarty_tpl->registered_plugins['block']['{$base_tag}'][0][0]->{$function[1]}({$par_string});")->newline();
                } else {
                    $this->php("echo {$function[0]}::{$function[1]}({$par_string});")->newline();
                }
            }
            if (isset($parameter['modifier_list'])) {
                $this->php('echo ' . $compiler->compileTag('private_modifier', array(), array('modifierlist' => $parameter['modifier_list'], 'value' => 'ob_get_clean()')) . ';')->newline();
            }
            $this->outdent()->php("}")->newline();
            $this->php("array_pop(\$_smarty_tpl->_tag_stack);")->newline();
        }

        return $this->returnTagCode($compiler);
    }

}
