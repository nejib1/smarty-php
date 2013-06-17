<?php

/**
 * Smarty Internal Plugin Resource File
 *
 *
 * @package TemplateResources
 * @author Uwe Tews
 * @author Rodney Rehm
 */

/**
 * Smarty Internal Plugin Resource File
 *
 * Implements the file system as resource for Smarty templates
 *
 *
 * @package TemplateResources
 */
class Smarty_Internal_Resource_File extends Smarty_Resource
{

    /**
     * populate Source Object with meta data from Resource
     *
     * @param Smarty $tpl_obj template object
     */
    public function populate(Smarty $tpl_obj = null)
    {
        $this->filepath = $this->buildFilepath($tpl_obj);

        if ($this->filepath !== false) {
            if (is_object($tpl_obj->security_policy)) {
                $tpl_obj->security_policy->isTrustedResourceDir($this->filepath);
            }

            $this->uid = sha1($this->filepath);
            if ($tpl_obj->compile_check && !isset($this->timestamp)) {
                $this->timestamp = @filemtime($this->filepath);
                $this->exists = !!$this->timestamp;
            }
        }
    }

    /**
     * build template filepath by traversing the template_dir array
     *
     * @param Smarty $tpl_obj template object
     * @return string fully qualified filepath
     * @throws SmartyException if default template handler is registered but not callable
     */
    public function buildFilepath(Smarty $tpl_obj = null)
    {
        $file = $this->name;
        if ($this->usage == Smarty::IS_CONFIG) {
            $_directories = $tpl_obj->getConfigDir();
            $_default_handler = $tpl_obj->default_config_handler_func;
        } else {
            $_directories = $tpl_obj->getTemplateDir();
            $_default_handler = $tpl_obj->default_template_handler_func;
        }

        // go relative to a given template?
        $_file_is_dotted = $file[0] == '.' && ($file[1] == '.' || $file[1] == '/' || $file[1] == "\\");
        if ($tpl_obj && isset($tpl_obj->parent) && $tpl_obj->parent->usage == Smarty::IS_TEMPLATE && $_file_is_dotted) {
            if ($tpl_obj->parent->source->type != 'file' && $tpl_obj->parent->source->type != 'extends' && !$tpl_obj->parent->allow_relative_path) {
                throw new SmartyException("Template '{$file}' cannot be relative to template of resource type '{$tpl_obj->parent->source->type}'");
            }
            $file = dirname($tpl_obj->parent->source->filepath) . DS . $file;
            $_file_exact_match = true;
            if (!preg_match('/^([\/\\\\]|[a-zA-Z]:[\/\\\\])/', $file)) {
                // the path gained from the parent template is relative to the current working directory
                // as expansions (like include_path) have already been done
                $file = getcwd() . DS . $file;
            }
        }

        // resolve relative path
        if (!preg_match('/^([\/\\\\]|[a-zA-Z]:[\/\\\\])/', $file)) {
            // don't we all just love windows?
            $_path = DS . trim(str_replace('\\', '/', $file), '/');
            $_was_relative = true;
        } else {
            // don't we all just love windows?
            $_path = str_replace('\\', '/', $file);
        }
        $_path = $this->normalizePath($_path, false);

        if (DS != '/') {
            // don't we all just love windows?
            $_path = str_replace('/', '\\', $_path);
        }
        // revert to relative
        if (isset($_was_relative)) {
            $_path = substr($_path, 1);
        }

        // this is only required for directories
        $file = rtrim($_path, '/\\');

        // files relative to a template only get one shot
        if (isset($_file_exact_match)) {
            return $this->fileExists($file) ? $file : false;
        }

        // template_dir index?
        if (preg_match('#^\[(?P<key>[^\]]+)\](?P<file>.+)$#', $file, $match)) {
            $_directory = null;
            // try string indexes
            if (isset($_directories[$match['key']])) {
                $_directory = $_directories[$match['key']];
            } else if (is_numeric($match['key'])) {
                // try numeric index
                $match['key'] = (int)$match['key'];
                if (isset($_directories[$match['key']])) {
                    $_directory = $_directories[$match['key']];
                } else {
                    // try at location index
                    $keys = array_keys($_directories);
                    $_directory = $_directories[$keys[$match['key']]];
                }
            }

            if ($_directory) {
                $_file = substr($file, strpos($file, ']') + 1);
                $_filepath = $_directory . $_file;
                if ($this->fileExists($_filepath)) {
                    return $_filepath;
                }
            }
        }

        $_stream_resolve_include_path = function_exists('stream_resolve_include_path');
        // relative file name?
        if (!preg_match('/^([\/\\\\]|[a-zA-Z]:[\/\\\\])/', $file)) {
            foreach ($_directories as $_directory) {
                $_filepath = $_directory . $file;
                if ($this->fileExists($_filepath)) {
                    return $this->normalizePath($_filepath);
                }
                if ($tpl_obj->use_include_path && !preg_match('/^([\/\\\\]|[a-zA-Z]:[\/\\\\])/', $_directory)) {
                    // try PHP include_path
                    if ($_stream_resolve_include_path) {
                        $_filepath = stream_resolve_include_path($_filepath);
                    } else {
                        $_filepath = Smarty_Internal_Get_Include_Path::getIncludePath($_filepath);
                    }
                    if ($_filepath !== false) {
                        if ($this->fileExists($_filepath)) {
                            return $this->normalizePath($_filepath);
                        }
                    }
                }
            }
        }

        // try absolute filepath
        if ($this->fileExists($file)) {
            return $file;
        }

        // no tpl file found
        if ($_default_handler) {
            if (!is_callable($_default_handler)) {
                if ($tpl_obj->usage == Smarty::IS_CONFIG) {
                    throw new SmartyException("Default config handler not callable");
                } else {
                    throw new SmartyException("Default template handler not callable");
                }
            }
            $_return = call_user_func_array($_default_handler, array($source->type, $source->name, &$_content, &$_timestamp, $tpl_obj));
            if (is_string($_return)) {
                $source->timestamp = @filemtime($_return);
                $source->exists = !!$source->timestamp;
                return $_return;
            } elseif ($_return === true) {
                $source->content = $_content;
                $source->timestamp = $_timestamp;
                $source->exists = true;
                return $_filepath;
            }
        }

        // give up
        return false;
    }

    /**
     * Normalize Paths "foo/../bar" to "bar"
     *
     * @param string $_path path to normalize
     * @param boolean $ds respect windows directory separator
     * @return string normalized path
     */
    protected function normalizePath($_path, $ds = true)
    {
        if ($ds) {
            // don't we all just love windows?
            $_path = str_replace('\\', '/', $_path);
        }

        $offset = 0;
        // resolve simples
        $_path = preg_replace('#(/\./(\./)*)|/{2,}#', '/', $_path);
        // resolve parents
        while (true) {
            $_parent = strpos($_path, '/../', $offset);
            if (!$_parent) {
                break;
            } else if ($_path[$_parent - 1] === '.') {
                $offset = $_parent + 3;
                continue;
            }

            $_pos = strrpos($_path, '/', $_parent - strlen($_path) - 1);
            if ($_pos === false) {
                // don't we all just love windows?
                $_pos = $_parent;
            }

            $_path = substr_replace($_path, '', $_pos, $_parent + 3 - $_pos);
        }

        if ($ds && DS != '/') {
            // don't we all just love windows?
            $_path = str_replace('/', '\\', $_path);
        }

        return $_path;
    }

    /**
     * populate Resource with timestamp and exists
     *
     */
    public function populateTimestamp()
    {
        $this->filepath = $this->buildFilepath($tpl_obj);
        $this->timestamp = @filemtime($this->filepath);
        $this->exists = !!$this->timestamp;
    }

    /**
     * read file
     *
     * @return boolean false|string
     */
    public function getContent()
    {
        if ($this->exists) {
            return file_get_contents($this->filepath);
        }
        return false;
    }

    /**
     * Determine basename for compiled filename
     *
     * @return string resource's basename
     */
    public function getBasename()
    {
        $_file = $this->name;
        if (($_pos = strpos($_file, ']')) !== false) {
            $_file = substr($_file, $_pos + 1);
        }
        return basename($_file);
    }

}
