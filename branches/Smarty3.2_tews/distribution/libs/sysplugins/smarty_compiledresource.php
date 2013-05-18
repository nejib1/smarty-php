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
     * Compiled Existance
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
     * Source Object
     * @var Smarty_Template_Source
     */
    public $source = null;

    /**
     * create Compiled Object container
     *
     * @param Smarty $tpl_obj
     */
    public function __construct($tpl_obj)
    {
        if ($tpl_obj->usage != Smarty::IS_SMARTY) {
            $tpl_obj->compiled = $this;
        }
        $this->compile_id = $tpl_obj->compile_id;
        if (isset($tpl_obj->source)) {
            $this->source = $tpl_obj->source;
        } else {
            $this->source = null;
        }
    }


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
        if (empty($this->smarty_content)) {
            $this->loadContent($tpl_obj);
        }
        try {
            $level = ob_get_level();
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
        if ($tpl_obj->source->recompiled && empty($this->file_dependency[$tpl_obj->source->uid])) {
            $this->file_dependency[$tpl_obj->source->uid] = array($tpl_obj->source->filepath, $tpl_obj->source->timestamp, $tpl_obj->source->type);
        }
        if ($tpl_obj->caching) {
            $cached = Smarty_Internal_CacheCreate::_getCachedObject($tpl_obj);
            $cached->newcache->_mergeFromCompiled($tpl_obj);
        }
        if ($tpl_obj->caching == Smarty::CACHING_NOCACHE_CODE && isset($tpl_obj->parent)) {
            $tpl_obj->parent->has_nocache_code = $tpl_obj->parent->has_nocache_code || $tpl_obj->has_nocache_code;
        }
        if (!$no_output_filter && (isset($tpl_obj->autoload_filters['output']) || isset($tpl_obj->registered_filters['output']))) {
            $output = Smarty_Internal_Filter_Handler::runFilter('output', $output, $tpl_obj);
        }

        if ($tpl_obj->debugging) {
            Smarty_Internal_Debug::end_render($tpl_obj);
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
        if ($tpl_obj->source->recompiled) {
            if ($tpl_obj->debugging) {
                Smarty_Internal_Debug::start_compile($tpl_obj);
            }

            $tpl_obj->compiler->compileTemplate();
            if ($tpl_obj->debugging) {
                Smarty_Internal_Debug::end_compile($tpl_obj);
                Smarty_Internal_Debug::start_render($tpl_obj);
            }
            eval('?>' . $tpl_obj->compiler->template_code->buffer);
            unset($tpl_obj->compiler);
        } else {
            $this->isValid = true;
            if (!$this->exists || ($tpl_obj->force_compile && !$this->isCompiled)) {
                $this->isValid = false;
            } else {
                include($this->filepath);
            }
            if ($this->isValid && !empty($this->smarty_content)) {
                return;
            } else {
                $tpl_obj->compiler->compileTemplateSource();
                unset($tpl_obj->compiler);
            }
            include($this->filepath);
            if (!$this->isValid || empty($this->smarty_content)) {
                throw new SmartyException("Unable to load compile template file '{$this->filepath}");
            }
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
