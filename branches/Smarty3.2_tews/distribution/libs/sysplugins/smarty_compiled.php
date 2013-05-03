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
class Smarty_Compiled
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
     * Template was compiled
     * @var boolean
     */
    public $isCompiled = false;

    /**
     * Source Object
     * @var Smarty_Template_Source
     */
    public $source = null;

    /**
     * instance of smarty content from compiled file
     * @var Smarty_Content
     * @internal
     */
    public $smarty_content = null;

    /**
     * cache for Smarty_Compiled instances
     * @var array
     */
    public static $compileds = array();

    /**
     * create Compiled Object container
     *
     * @param Smarty $_object
     */
    public function __construct($_object)
    {
        $this->source = $_object->source;
        $this->source->handler->populateCompiledFilepath($this, $_object);
        $this->timestamp = @filemtime($this->filepath);
        $this->exists = !!$this->timestamp;
    }

    /**
     * get rendered template output from compiled template
     *
     * @param Smarty $_template template object
     * @throws Exception
     * @throws SmartyException
     * @return
     */
    public function getRenderedTemplate($_template)
    {
        $_template->cached_subtemplates = array();
        if (empty($this->smarty_content)) {
            $this->loadContent($_template);
        }
        try {
            $level = ob_get_level();
            if (empty($this->smarty_content)) {
                throw new SmartyException("Invalid compiled template for '{$_template->template_resource}'");
            }
            array_unshift($_template->_capture_stack, array());
            //
            // render compiled template
            //
            $output = $this->smarty_content->get_template_content($_template);
            // any unclosed {capture} tags ?
            if (isset($_template->_capture_stack[0][0])) {
                $_template->_capture_error();
            }
            array_shift($_template->_capture_stack);
        } catch (Exception $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
        if (!$this->source->recompiled && empty($_template->compiled->file_dependency[$this->source->uid])) {
            $_template->compiled->file_dependency[$this->source->uid] = array($this->source->filepath, $this->source->timestamp, $this->source->type);
        }
        if ($_template->caching) {
            $cached = Smarty_Internal_CacheCreate::_getCachedObject($_template);
            $cached->newcache->_mergeFromCompiled($_template);
        }
        if ($_template->caching == Smarty::CACHING_NOCACHE_CODE && isset($_template->parent)) {
            $_template->parent->has_nocache_code = $_template->parent->has_nocache_code || $_template->has_nocache_code;
        }
        if ($_template->debugging) {
            Smarty_Internal_Debug::end_render($_template);
        }
        return $output;
    }

    /**
     * Load compiled template content
     *
     * @param Smarty $_template template object
     */
    public function loadContent($_template)
    {
        if ($this->source->recompiled) {
            if ($_template->debugging) {
                Smarty_Internal_Debug::start_compile($_template);
            }
            $_template->compiler->compileTemplate();
            if ($_template->debugging) {
                Smarty_Internal_Debug::end_compile($_template);
            }
            if ($_template->debugging) {
                Smarty_Internal_Debug::start_render($_template);
            }
            eval('?>' . $_template->compiler->template_code->buffer);
            unset($_template->compiler);
        } else {
            if (!$this->exists || ($_template->force_compile && !$this->isCompiled)) {
                $_template->compiler->compileTemplateSource();
                unset($_template->compiler);
            }
            if ($_template->debugging) {
                Smarty_Internal_Debug::start_render($_template);
            }
            if (empty($this->smarty_content)) {
                include($this->filepath);
                if ($_template->mustCompile) {
                    // recompile and load again
                    $_template->compiler->compileTemplateSource();
                    unset($_template->compiler);
                    include($this->filepath);
                }
            }
        }
    }


    /**
     * Delete compiled template file
     *
     * @param string $resource_name template name
     * @param string $compile_id    compile id
     * @param integer $exp_time      expiration time
     * @param Smarty $smarty        Smarty instance
     * @return integer number of template files deleted
     */
    public static function clearCompiledTemplate($resource_name, $compile_id, $exp_time, Smarty $smarty)
    {
        $_compile_dir = $smarty->getCompileDir();
        $_compile_id = isset($compile_id) ? preg_replace('![^\w\|]+!', '_', $compile_id) : null;
        $compiletime_options = 0;
        $_dir_sep = $smarty->use_sub_dirs ? DS : '^';
        if (isset($resource_name)) {
            $source = Smarty_Resource::source(null, $smarty, $resource_name);

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
                    && (!isset($resource_name)
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
        Smarty_Compiled::$compileds = array();
        return $_count;
    }

}
