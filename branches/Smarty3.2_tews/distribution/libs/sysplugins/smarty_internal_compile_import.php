<?php

/**
 * Smarty Internal Plugin Compile Import
 *
 * Compiles the {import} tag
 *
 * @package Smarty
 * @subpackage Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile Import Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Import extends Smarty_Internal_CompileBase {

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $required_attributes = array('file');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $shorttag_order = array('file');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $option_flags = array();

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
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
    public function compile($args, $compiler, $parameter) {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);

        $include_file = $_attr['file'];
        if ($compiler->has_variable_string && substr_count($include_file, "'") != 2) {
            $compiler->trigger_template_error('illegal variable template name', $compiler->lex->taglineno);
        }

        eval("\$tpl_name = $include_file;");
        $tpl = clone $compiler->template;
        unset($tpl->source, $tpl->compiled, $tpl->cached, $tpl->compiler, $tpl->mustCompile);
        $tpl->template_resource = $tpl_name;
        $tpl->parent = $compiler->template;
        $tpl->caching = $compiler->template->caching;
        $tpl->compiler->nocache = $compiler->nocache;
        // get compiled code
        $tpl->compiler->suppressHeader = true;
        $tpl->compiler->suppressTemplatePropertyHeader = true;
        $tpl->compiler->suppressPostFilter = true;
        $tpl->compiler->write_compiled_code = false;
        $tpl->compiler->indentation = $compiler->indentation;
        $compiled_code = $tpl->compiler->compileTemplate();
        // merge compiled code for {function} tags
        if (!empty($tpl->compiler->template_functions)) {
            $compiler->template_functions = array_merge($compiler->template_functions, $tpl->compiler->template_functions);
            $compiler->template_functions_code = array_merge($compiler->template_functions_code, $tpl->compiler->template_functions_code);
        }
        // merge compiled code for {block} tags
        if (!empty($tpl->compiler->block_functions)) {
            $compiler->block_functions = array_merge($compiler->block_functions, $tpl->compiler->block_functions);
            $compiler->block_functions_code = array_merge($compiler->block_functions_code, $tpl->compiler->block_functions_code);
        }
        $compiler->required_plugins['compiled'] = array_merge($compiler->required_plugins['compiled'], $tpl->compiler->required_plugins['compiled']);
        $compiler->required_plugins['nocache'] = array_merge($compiler->required_plugins['nocache'], $tpl->compiler->required_plugins['nocache']);
        // merge filedependency
        $compiler->file_dependency[$tpl->source->uid] = array($tpl->source->filepath, $tpl->source->timestamp, $tpl->source->type);
        $compiler->file_dependency = array_merge($compiler->file_dependency, $tpl->compiler->file_dependency);
        $compiler->template->has_nocache_code = $compiler->template->has_nocache_code | $tpl->has_nocache_code;
        // release compiler object to free memory
        unset($tpl->compiler);

        $save = $compiler->nocache_nolog;
        // update nocache line number trace back
//        $compiler->parser->updateNocacheLineTrace();
        $compiler->nocache_nolog = $save;
        // output compiled code

        $compiler->suppressNocacheProcessing = true;
        $this->iniTagCode($compiler);
        $this->php("/*  Imported template \"{$tpl_name}\" */")->newline();
        $this->buffer .= $compiled_code;
        $this->php("/*  End of imported template \"{$tpl_name}\" */")->newline();

        return $this->returnTagCode($compiler);
    }

}
