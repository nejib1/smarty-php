<?php

/**
 * Smarty Internal Plugin Compile Include
 *
 * Compiles the {include} tag
 *
 * @package Smarty
 * @subpackage Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile Include Class
 *
 * @package Smarty
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Include extends Smarty_Internal_CompileBase {

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
    public $option_flags = array('nocache', 'inline', 'caching');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $optional_attributes = array('_any');

    /**
     * Compiles code for the {include} tag
     *
     * @param array $args array with attributes from parser
     * @param object $compiler compiler object
     * @param array $parameter array with compilation parameter
     * @return string compiled code
     */
    public function compile($args, $compiler, $parameter) {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        // save posible attributes
        $include_file = $_attr['file'];

        if (isset($_attr['assign'])) {
            // output will be stored in a smarty variable instead of beind displayed
            $_assign = trim($_attr['assign'], "'\"");
        }

        $_parent_scope = Smarty::SCOPE_LOCAL;
        if (isset($_attr['scope'])) {
            $_attr['scope'] = trim($_attr['scope'], "'\"");
            if ($_attr['scope'] == 'parent') {
                $_parent_scope = Smarty::SCOPE_PARENT;
            } elseif ($_attr['scope'] == 'root') {
                $_parent_scope = Smarty::SCOPE_ROOT;
            } elseif ($_attr['scope'] == 'global') {
                $_parent_scope = Smarty::SCOPE_GLOBAL;
            }
        }
        $_caching = Smarty::CACHING_OFF;
//        $_caching = 'null';
//        if ($compiler->nocache || $compiler->tag_nocache) {
//            $_caching = Smarty::CACHING_OFF;
//        }
        // default for included templates
        if ($compiler->template->caching && !$compiler->nocache && !$compiler->tag_nocache) {
            $_caching = Smarty::CACHING_NOCACHE_CODE;
        }
        /*
         * if the {include} tag provides individual parameter for caching
         * it will not be included into the common cache file and treated like
         * a nocache section
         */
        if (isset($_attr['cache_lifetime'])) {
            $_cache_lifetime = $_attr['cache_lifetime'];
            $compiler->nocache_nolog = true;
            $_caching = Smarty::CACHING_LIFETIME_CURRENT;
        } else {
            $_cache_lifetime = 'null';
        }
        if (isset($_attr['cache_id'])) {
            $_cache_id = $_attr['cache_id'];
            $compiler->nocache_nolog = true;
            $_caching = Smarty::CACHING_LIFETIME_CURRENT;
        } else {
            $_cache_id = '$_smarty_tpl->cache_id';
        }
        if (isset($_attr['compile_id'])) {
            $_compile_id = $_attr['compile_id'];
        } else {
            $_compile_id = '$_smarty_tpl->compile_id';
        }
        if ($_attr['caching'] === true) {
            $compiler->nocache_nolog = true;
            $_caching = Smarty::CACHING_LIFETIME_CURRENT;
        }
        if ($_attr['nocache'] === true) {
            $compiler->tag_nocache = true;
            $_caching = Smarty::CACHING_OFF;
        }

        $this->iniTagCode($compiler);

        $has_compiled_template = false;
        if (($compiler->template->merge_compiled_includes || $_attr['inline'] === true) && !$compiler->template->source->recompiled
                && !($compiler->template->caching && ($compiler->tag_nocache || $compiler->nocache || $compiler->nocache_nolog)) && $_caching != Smarty::CACHING_LIFETIME_CURRENT) {
            // check if compiled code can be merged (contains no variable part)
            if (!$compiler->has_variable_string && (substr_count($include_file, '"') == 2 or substr_count($include_file, "'") == 2)
                    and substr_count($include_file, '(') == 0 and substr_count($include_file, '$_smarty_tpl->') == 0) {
                $tpl_name = null;
                eval("\$tpl_name = $include_file;");
                if (!isset(Smarty_Internal_TemplateCompilerBase::$merged_inline_templates[$tpl_name])) {
                    $tpl = clone $compiler->template;
                    unset($tpl->source, $tpl->compiled, $tpl->cached, $tpl->compiler, $tpl->mustCompile);
                    $tpl->template_resource = $tpl_name;
                    $tpl->parent = $compiler->template;
                    if ($compiler->template->caching) {
                        // needs code for cached page but no cache file
                        $tpl->caching = Smarty::CACHING_NOCACHE_CODE;
                    }
                    // make sure whole chain gest compiled
                    $tpl->mustCompile = true;
                    if (!($tpl->source->uncompiled) && $tpl->source->exists) {
                        // get compiled code
                        $tpl->compiler->suppressHeader = true;
                        $tpl->compiler->suppressTemplatePropertyHeader = true;
                        $tpl->compiler->write_compiled_code = false;
                        $tpl->compiler->content_class = Smarty_Internal_TemplateCompilerBase::$merged_inline_templates[$tpl_name]['class'] = '__Smarty_Content_' . str_replace('.', '_', uniqid('', true));
                        $code = $tpl->compiler->compileTemplate();
                        $compiler->required_plugins['compiled'] = array_merge($compiler->required_plugins['compiled'], $tpl->compiler->required_plugins['compiled']);
                        $compiler->required_plugins['nocache'] = array_merge($compiler->required_plugins['nocache'], $tpl->compiler->required_plugins['nocache']);
                        $tpl->compiler->required_plugins = array();
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
                        // save merged template
                        $tpl->compiler->buffer = '';
                        $tpl->compiler->indentation = 0;
                        $tpl->compiler->newline()->php("/* Inline subtemplate compiled from \"{$tpl->source->filepath}\" */")->newline();
                        Smarty_Internal_TemplateCompilerBase::$merged_inline_templates[$tpl_name]['code'] = $tpl->compiler->createTemplateCodeFrame($tpl, $code, false, true);
                        unset($code);
                        $tpl->compiler->buffer = '';
                        // merge file dependency
                        $compiler->file_dependency[$tpl->source->uid] = array($tpl->source->filepath, $tpl->source->timestamp, $tpl->source->type);
                        $compiler->file_dependency = array_merge($compiler->file_dependency, $tpl->compiler->file_dependency);
                        $compiler->template->has_nocache_code = $compiler->template->has_nocache_code | $tpl->has_nocache_code;
                        $has_compiled_template = true;
                        // release compiler object to free memory
                        unset($tpl->compiler, $tpl);
                    }
                } else {
                    $has_compiled_template = true;
                }
            }
        }
        // delete {include} standard attributes
        unset($_attr['file'], $_attr['assign'], $_attr['cache_id'], $_attr['compile_id'], $_attr['cache_lifetime'], $_attr['nocache'], $_attr['caching'], $_attr['scope'], $_attr['inline']);
        // remaining attributes must be assigned as smarty variable
        if (!empty($_attr)) {
            if ($_parent_scope == Smarty::SCOPE_LOCAL) {
                // create variables
                foreach ($_attr as $key => $value) {
                    $_pairs[] = "'$key'=>$value";
                }
                $_vars = 'array(' . join(',', $_pairs) . ')';
                $_has_vars = true;
            } else {
                $compiler->trigger_template_error('variable passing not allowed in parent/global scope', $compiler->lex->taglineno);
            }
        } else {
            $_vars = 'array()';
            $_has_vars = false;
        }
        $save = $compiler->nocache_nolog;
        // update nocache line number trace back
        $compiler->parser->updateNocacheLineTrace();
        $compiler->nocache_nolog = $save;
        // output compiled code
        if ($has_compiled_template) {
            $_class = '\'' . Smarty_Internal_TemplateCompilerBase::$merged_inline_templates[$tpl_name]['class'] . '\'';
        } else {
            $_class = 'null';
        }
        // was there an assign attribute
        if (isset($_assign)) {
            $this->php("\$_smarty_tpl->tpl_vars->{$_assign} = array('value' => \$this->_getSubTemplate ($include_file, \$_smarty_tpl, $_cache_id, $_compile_id, $_caching, $_cache_lifetime, $_vars, $_parent_scope , $_class));")->newline();
        } else {
            $this->php("echo \$this->_getSubTemplate ($include_file, \$_smarty_tpl, $_cache_id, $_compile_id, $_caching, $_cache_lifetime, $_vars, $_parent_scope, $_class);")->newline();
        }


        return $this->returnTagCode($compiler);
    }

}
