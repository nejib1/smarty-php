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
class Smarty_Compiled extends Smarty_Internal_Magic_Error
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
     * Source Object
     * @var Smarty_Template_Source
     */
    public $source = null;

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


    public static function getObject($tpl_obj)
    {
        // check runtime cache
        $source_key = $tpl_obj->source->unique_resource;
        $compiled_key = $tpl_obj->compile_id ? $tpl_obj->compile_id : '#undefined#';
        if ($tpl_obj->cache_objs) {
            if (isset(Smarty::$template_objects[$source_key]['compiled'][$compiled_key])) {
                return Smarty::$template_objects[$source_key]['compiled'][$compiled_key];
            } else {
                return Smarty::$template_objects[$source_key]['compiled'][$compiled_key] = new Smarty_Compiled($tpl_obj);
            }
        } else {
            return new Smarty_Compiled($tpl_obj);
        }
    }


    /**
     * create Compiled Object container
     *
     * @param Smarty $tpl_obj
     */
    public function __construct($tpl_obj)
    {
        $this->source = $tpl_obj->source;
        $this->filepath = $this->buildFilepath($tpl_obj);
        $this->timestamp = @filemtime($this->filepath);
        $this->exists = !!$this->timestamp;
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
        if ($tpl_obj->source->recompiled && empty($this->file_dependency[$this->source->uid])) {
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
                throw new SmartyException('err4', $this);
            }
        }
    }

    /**
     * populate Compiled Object with compiled filepath
     *
     * @param Smarty|Smarty_Internal_Cached $mixed_obj template or cache object object
     * @return string
     */
    public function buildFilepath($mixed_obj)
    {
        $_compile_id = isset($mixed_obj->compile_id) ? preg_replace('![^\w\|]+!', '_', $mixed_obj->compile_id) : null;
        $_filepath = $this->source->uid . '_' . $mixed_obj->compiletime_options;
        // if use_sub_dirs, break file into directories
        if ($mixed_obj->use_sub_dirs) {
            $_filepath = substr($_filepath, 0, 2) . DS
                . substr($_filepath, 2, 2) . DS
                . substr($_filepath, 4, 2) . DS
                . $_filepath;
        }
        $_compile_dir_sep = $mixed_obj->use_sub_dirs ? DS : '^';
        if (isset($_compile_id)) {
            $_filepath = $_compile_id . $_compile_dir_sep . $_filepath;
        }
        // subtype
        if ($mixed_obj->usage == Smarty::IS_CONFIG) {
            $_subtype = '.config';
        } elseif ($mixed_obj->caching) {
            $_subtype = '.cache';
        } else {
            $_subtype = '';
        }
        $_compile_dir = $mixed_obj->getCompileDir();
        // set basename if not specified
        $_basename = $this->source->handler->getBasename($this->source);
        if ($_basename === null) {
            $_basename = basename(preg_replace('![^\w\/]+!', '_', $this->source->name));
        }
        // separate (optional) basename by dot
        if ($_basename) {
            $_basename = '.' . $_basename;
        }
        return $_compile_dir . $_filepath . '.' . $this->source->type . $_basename . $_subtype . '.php';
    }


    /**
     * Delete compiled template file
     *
     * @param string $template_resource template name
     * @param string $compile_id    compile id
     * @param integer $exp_time      expiration time
     * @param Smarty $smarty        Smarty instance
     * @return integer number of template files deleted
     */
    public static function clearCompiledTemplate($template_resource, $compile_id, $exp_time, Smarty $smarty)
    {
        $_compile_dir = $smarty->getCompileDir();
        $_compile_id = isset($compile_id) ? preg_replace('![^\w\|]+!', '_', $compile_id) : null;
        $compiletime_options = 0;
        $_dir_sep = $smarty->use_sub_dirs ? DS : '^';
        if (isset($template_resource)) {
            $source = Smarty_Resource::source($smarty, $template_resource);

            if ($source->exists) {
                // set basename if not specified
                $_basename = $source->handler->getBasename($source);
                if ($_basename === null) {
                    $_basename = basename(preg_replace('![^\w\/]+!', '_', $source->name));
                }
                // separate (optional) basename by dot
                if ($_basename) {
                    $_basename = '.' . $_basename;
                }
                $_resource_part_1 = $source->uid . '_' . $compiletime_options . '.' . $source->type . $_basename . '.php';
                $_resource_part_1_length = strlen($_resource_part_1);
            } else {
                return 0;
            }

            $_resource_part_2 = str_replace('.php', '.cache.php', $_resource_part_1);
            $_resource_part_2_length = strlen($_resource_part_2);
        }
        $_dir = $_compile_dir;
        if ($smarty->use_sub_dirs && isset($_compile_id)) {
            $_dir .= $_compile_id . $_dir_sep;
        }
        if (isset($_compile_id)) {
            $_compile_id_part = $_compile_dir . $_compile_id . $_dir_sep;
        }
        $_count = 0;
        try {
            $_compileDirs = new RecursiveDirectoryIterator($_dir);
            // NOTE: UnexpectedValueException thrown for PHP >= 5.3
        } catch (Exception $e) {
            return 0;
        }
        $_compile = new RecursiveIteratorIterator($_compileDirs, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($_compile as $_file) {
            if (substr($_file->getBasename(), 0, 1) == '.' || strpos($_file, '.svn') !== false)
                continue;

            $_filepath = (string)$_file;

            if ($_file->isDir()) {
                if (!$_compile->isDot()) {
                    // delete folder if empty
                    @rmdir($_file->getPathname());
                }
            } else {
                $unlink = false;
                if ((!isset($_compile_id) || strpos($_filepath, $_compile_id_part) === 0)
                    && (!isset($template_resource)
                        || (isset($_filepath[$_resource_part_1_length])
                            && substr_compare($_filepath, $_resource_part_1, -$_resource_part_1_length, $_resource_part_1_length) == 0)
                        || (isset($_filepath[$_resource_part_2_length])
                            && substr_compare($_filepath, $_resource_part_2, -$_resource_part_2_length, $_resource_part_2_length) == 0))
                ) {
                    if (isset($exp_time)) {
                        if (time() - @filemtime($_filepath) >= $exp_time) {
                            $unlink = true;
                        }
                    } else {
                        $unlink = true;
                    }
                }

                if ($unlink && @unlink($_filepath)) {
                    $_count++;
                    // notify listeners of deleted file
                    Smarty::triggerCallback('filesystem:delete', array($smarty, $_filepath));
                }
            }
        }
        // clear compiled cache
        foreach (Smarty::$template_objects as $key => $foo) {
            unset(Smarty::$template_objects[$key]['compiled']);
        }
        return $_count;
    }

}
