<?php

/**
 * Smarty Internal Plugin Compile Import
 *
 * Compiles the {import} tag
 *
 *
 * @package Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile Import Class
 *
 *
 * @package Compiler
 */
class Smarty_Internal_Compile_Import extends Smarty_Internal_CompileBase
{

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see $tpl_obj
     */
    public $required_attributes = array('file');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see $tpl_obj
     */
    public $shorttag_order = array('file');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see $tpl_obj
     */
    public $option_flags = array();

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see $tpl_obj
     */
    public $optional_attributes = array();

    /**
     * Compiles code for the {import} tag
     *
     * @param array $args array with attributes from parser
     * @param object $compiler compiler object
     * @param array $parameter array with compilation parameter
     * @return string compiled code
     */
    public function compile($args, $compiler, $parameter)
    {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);

        $include_file = $_attr['file'];
        if (!(substr_count($include_file, "'") == 2 || substr_count($include_file, '"') == 2)) {
            $compiler->trigger_template_error('illegal variable template name', $compiler->lex->taglineno);
        }

        eval("\$tpl_name = $include_file;");
        $tpl = clone $compiler->tpl_obj;
        unset($tpl->source, $tpl->compiled, $tpl->cached, $tpl->compiler, $tpl->mustCompile);
        $tpl->template_resource = $tpl_name;
        $tpl->parent = $compiler->tpl_obj;
        $tpl->caching = $compiler->caching;
        $tpl->compiler->nocache = $compiler->nocache;
        // set up parameter
        $tpl->compiler->suppressHeader = true;
        $tpl->compiler->suppressTemplatePropertyHeader = true;
        $tpl->compiler->suppressPostFilter = true;
        $tpl->compiler->write_compiled_code = false;
        $tpl->compiler->template_code->indentation = $compiler->template_code->indentation;
        $tpl->compiler->isInheritance = $compiler->isInheritance;
        $tpl->compiler->isInheritanceChild = $compiler->isInheritanceChild;
        // compile imported template
        $tpl->compiler->template_code->php("/*  Imported template \"{$tpl_name}\" */")->newline();
        $tpl->compiler->compileTemplate();
        $tpl->compiler->template_code->php("/*  End of imported template \"{$tpl_name}\" */")->newline();
        // merge compiled code for {function} tags
        if (!empty($tpl->compiler->template_functions)) {
            $compiler->template_functions = array_merge($compiler->template_functions, $tpl->compiler->template_functions);
            $compiler->template_functions_code = array_merge($compiler->template_functions_code, $tpl->compiler->template_functions_code);
        }
        // merge compiled code for {block} tags
        if (!empty($tpl->compiler->inheritance_blocks)) {
            $compiler->inheritance_blocks = array_merge($compiler->inheritance_blocks, $tpl->compiler->inheritance_blocks);
            $compiler->inheritance_blocks_code = array_merge($compiler->inheritance_blocks_code, $tpl->compiler->inheritance_blocks_code);
        }
        $compiler->required_plugins['compiled'] = array_merge($compiler->required_plugins['compiled'], $tpl->compiler->required_plugins['compiled']);
        $compiler->required_plugins['nocache'] = array_merge($compiler->required_plugins['nocache'], $tpl->compiler->required_plugins['nocache']);
        // merge filedependency
        $compiler->file_dependency[$tpl->source->uid] = array($tpl->source->filepath, $tpl->source->timestamp, $tpl->source->type);
        $compiler->file_dependency = array_merge($compiler->file_dependency, $tpl->compiler->file_dependency);
        $compiler->has_nocache_code = $compiler->has_nocache_code | $tpl->compiler->has_nocache_code;

        // merge flag that variable container must be cloned
        $compiler->must_clone_vars = $compiler->must_clone_vars || $tpl->compiler->must_clone_vars;

        $save = $compiler->nocache_nolog;
        // update nocache line number trace back
// TODO       $compiler->parser->updateNocacheLineTrace();
        $compiler->nocache_nolog = $save;
        // output compiled code

        $compiler->suppressNocacheProcessing = true;
        $this->iniTagCode($compiler);
        $this->buffer .= $tpl->compiler->template_code->buffer;
        // release compiler object to free memory
        unset($tpl->compiler);
        return $this->returnTagCode($compiler);
    }

}
