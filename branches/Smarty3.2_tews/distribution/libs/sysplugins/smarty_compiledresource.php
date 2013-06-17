<?php

/**
 * Smarty Compiled Resource Plugin
 *
 *
 * @package CompiledResources
 * @author Uwe Tews
 */

/**
 * Meta Data Container for Compiled Template Files
 *
 *
 * @property string $content compiled content
 */
class Smarty_CompiledResource extends Smarty_Internal_Magic_Error
{

    /**
     * Compiled Filepath
     * @var string
     */
    public $filepath = null;

    /**
     * Compiled Timestamp
     * @var integer
     */
    public $timestamp = null;

    /**
     * Compiled Existence
     * @var boolean
     */
    public $exists = false;

    /**
     * Template was recompiled
     * @var boolean
     */
    public $isCompiled = false;

    /**
     * Compiled template is valid
     * @var boolean
     */
    public $isValid = false;

    /**
     * file dependencies
     *
     * @var array
     */
    public $file_dependency = array();

    /**
     * instance of smarty content from compiled file
     * @var Smarty_Internal_Content
     * @internal
     */
    public $smarty_content = null;

    /**
     * Template Compile Id (Smarty::$compile_id)
     * @var string
     */
    public $compile_id = null;

    /**
     * Flag if caching enabled
     * @var boolean
     */
    public $caching = false;

    /**
     * Source Object
     * @var Smarty_Template_Source
     */
    public $source = null;

    /**
     * get rendered template output from compiled template
     *
     * @param Smarty $tpl_obj template object
     * @param Smarty_Variable_Scope $_scope template variables
     * @param int $scope_type
     * @param null|array $data
     * @param boolean $no_output_filter true if output filter shall nit run
     * @throws Exception
     * @return string
     */
    public function getRenderedTemplate($tpl_obj, $_scope = null, $scope_type = Smarty::SCOPE_LOCAL, $data = null, $no_output_filter = true)
    {
        $_scope = $tpl_obj->_buildScope($_scope, $scope_type, $data);
        $tpl_obj->cached_subtemplates = array();
        try {
            $level = ob_get_level();
            if (empty($this->smarty_content)) {
                $this->loadContent($tpl_obj);
            }
            if ($tpl_obj->debugging) {
                Smarty_Internal_Debug::start_render($this->source);
            }
            if (empty($this->smarty_content)) {
                throw new SmartyException("Invalid compiled template for '{$this->source->template_resource}'");
            }
            array_unshift($tpl_obj->_capture_stack, array());
            //
            // render compiled template
            //
            $output = $this->smarty_content->get_template_content($tpl_obj, $_scope);
            // any unclosed {capture} tags ?
            if (isset($tpl_obj->_capture_stack[0][0])) {
                $tpl_obj->_capture_error();
            }
            array_shift($tpl_obj->_capture_stack);
        } catch (Exception $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
        if ($this->source->recompiled && empty($this->file_dependency[$this->source->uid])) {
            $this->file_dependency[$this->source->uid] = array($this->source->filepath, $this->source->timestamp, $this->source->type);
        }
        if ($this->caching) {
            Smarty_CacheResource::$creator[0]->_mergeFromCompiled($this);
        }
       if (!$no_output_filter && (isset($tpl_obj->autoload_filters['output']) || isset($tpl_obj->registered_filters['output']))) {
            $output = Smarty_Internal_Filter_Handler::runFilter('output', $output, $tpl_obj);
        }

        if ($tpl_obj->debugging) {
            Smarty_Internal_Debug::end_render($this->source);
        }
        return $output;
    }


    /**
     * Load compiled template content
     *
     * @param Smarty $tpl_obj template object
     * @throws SmartyException
     */
    public function loadContent($tpl_obj)
    {
        try {
            $level = ob_get_level();
        if ($this->source->recompiled) {
                if ($tpl_obj->debugging) {
                    Smarty_Internal_Debug::start_compile($this->source);
                }

                $compiler = Smarty_Compiler::load($tpl_obj, $this->source, $this->caching);
                $compiler->compileTemplate();
                if ($tpl_obj->debugging) {
                    Smarty_Internal_Debug::end_compile($this->source);
                }
                eval('?>' . $compiler->template_code->buffer);
                unset($compiler);
                if ($tpl_obj->debugging) {
                    Smarty_Internal_Debug::end_compile($this->source);
                }
       } else {
            $this->isValid = false;
            if ($this->exists && !$tpl_obj->force_compile) {
                $this->process($tpl_obj);
                if (!empty($this->smarty_content)) {
                $this->isValid = $this->smarty_content->isValid;
                }
            }
            if (!$this->isValid) {
                if ($tpl_obj->debugging) {
                    Smarty_Internal_Debug::start_compile($this->source);
                }
                $compiler = Smarty_Compiler::load($tpl_obj, $this->source, $this->caching);
                $compiler->compileTemplateSource($this);
                unset($compiler);
                if ($tpl_obj->debugging) {
                    Smarty_Internal_Debug::end_compile($this->source);
                }
                $this->process($tpl_obj);
                if (!empty($this->smarty_content)) {
                    $this->isValid = $this->smarty_content->isValid;
                }
                if (!$this->isValid || empty($this->smarty_content)) {
                    throw new SmartyException("Unable to load compile template file '{$this->filepath}");
                }
            }

        }
        } catch (Exception $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
    }

    /**
     * Delete compiled template file
     *
     * @param Smarty $smarty smarty object
     * @param string $template_resource template name
     * @param string $compile_id    compile id
     * @param integer $exp_time      expiration time
     * @return integer number of template files deleted
     */
    public static function clearCompiledTemplate(Smarty $smarty, $template_resource, $compile_id, $exp_time)
    {
        // load cache resource and call clear
        $_compiled_resource = $smarty->_loadHandler(SMARTY::COMPILED, $smarty->compiled_type);
//        Smarty_CompiledResource::invalidLoadedCache($smarty);
        return $_compiled_resource->clear($template_resource, $compile_id, $exp_time, $smarty);

    }


}
