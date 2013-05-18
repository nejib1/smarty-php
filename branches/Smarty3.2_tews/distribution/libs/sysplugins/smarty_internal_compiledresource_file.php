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
class Smarty_Internal_CompiledResource_File extends Smarty_CompiledResource
{


    /**
     * populate Compiled Resource Object with meta data from Resource
     *
     * @param Smarty $tpl_obj template object
     * @return void
     */
    public function populate($tpl_obj)
    {
        $this->filepath = $this->buildFilepath($tpl_obj);
        $this->timestamp = @filemtime($this->filepath);
        $this->exists = !!$this->timestamp;
        if ($this->exists) {
            // compiled template to see if it is still valid
            include $this->filepath;
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
        $_filepath = $mixed_obj->source->uid . '_' . $mixed_obj->compiletime_options;
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
        $_basename = $mixed_obj->source->getBasename($mixed_obj->source);
        if ($_basename === null) {
            $_basename = basename(preg_replace('![^\w\/]+!', '_', $mixed_obj->source->name));
        }
        // separate (optional) basename by dot
        if ($_basename) {
            $_basename = '.' . $_basename;
        }
        return $_compile_dir . $_filepath . '.' . $mixed_obj->source->type . $_basename . $_subtype . '.php';
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
    public function clear($template_resource, $compile_id, $exp_time, Smarty $smarty)
    {
        $_compile_dir = $smarty->getCompileDir();
        $_compile_id = isset($compile_id) ? preg_replace('![^\w\|]+!', '_', $compile_id) : null;
        $compiletime_options = 0;
        $_dir_sep = $smarty->use_sub_dirs ? DS : '^';
        if (isset($template_resource)) {
            $source = $smarty->_resourceLoader(Smarty::SOURCE, $template_resource);

            if ($source->exists) {
                // set basename if not specified
                $_basename = $source->getBasename($source);
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
