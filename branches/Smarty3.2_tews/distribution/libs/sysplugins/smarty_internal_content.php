<?php

/**
 * Smarty Internal Plugin Smarty Internal Content
 *
 * This file contains the basic shared methodes for precessing content of compiled and cached templates
 *
 * @package Smarty
 * @subpackage Template
 * @author Uwe Tews
 */

/**
 * Class with shared content processing methodes
 *
 * @package Smarty
 * @subpackage Template
 */
class Smarty_Internal_Content {

    /**
     * flag if class is valid
     * @var boolean
     * @internal
     */
    public $is_valid = false;

    /**
     * flag if class is from cache file
     * @var boolean
     * @internal
     */
    public $is_cache = false;

    /**
     * flag if content does contain nocache code
     * @var boolean
     * @internal
     */
    public $has_nocache_code = false;

    /**
     * saved cache lifetime
     * @var int
     * @internal
     */
    public $cache_lifetime = false;

    /**
     * names of cached subtemplates
     * @var array
     * @internal
     */
    public $cached_subtemplates = array();

    /**
     * required plugins
     * @var array
     * @internal
     */
    public $required_plugins = array();

    /**
     * required plugins of nocache code
     * @var array
     * @internal
     */
    public $required_plugins_nocache = array();

    /**
     * template function properties
     *
     * @var array
     */
    public $template_functions = array();

    /**
     * template functions called nocache
     * @var array
     */
    public $called_nocache_template_functions = array();

    /**
     * file dependencies
     *
     * @var array
     */
    public $file_dependency = array();

    /**
     * Smarty version class was compiled with
     * @var string
     * @internal
     */
    public $version = '';

    /**
     * constructor
     *
     */
    public function __construct(Smarty_Internal_Template $template) {
        // check if class is still valid
        $this->is_valid = true;
        if ($this->version != Smarty::SMARTY_VERSION) {
            $this->is_valid = false;
        } else if ((!$this->is_cache && $template->compile_check) || ($this->is_cache && ($template->compile_check === true || $template->compile_check === Smarty::COMPILECHECK_ON)) && !empty($this->file_dependency)) {
            foreach ($this->file_dependency as $_file_to_check) {
                if ($_file_to_check[2] == 'file' || $_file_to_check[2] == 'php') {
                    if ($template->source->filepath == $_file_to_check[0] && isset($template->source->timestamp)) {
                        // do not recheck current template
                        $mtime = $template->source->timestamp;
                    } else {
                        // file and php types can be checked without loading the respective resource handlers
                        $mtime = @filemtime($_file_to_check[0]);
                    }
                } elseif ($_file_to_check[2] == 'string') {
                    continue;
                } else {
                    $source = Smarty_Resource::source(null, $this->smarty, $_file_to_check[0]);
                    $mtime = $source->timestamp;
                }
                if (!$mtime || $mtime > $_file_to_check[1]) {
                    $this->is_valid = false;
                    break;
                }
            }
        }
        if ($this->is_valid) {
            foreach ($this->required_plugins as $file => $call) {
                if (!is_callable($call)) {
                    include $file;
                }
            }
        }
        if ($this->is_cache) {
            $template->cached->valid = $this->is_valid;
        } else {
            $template->mustCompile = !$this->is_valid;
            if (!empty($this->template_functions) && isset($template->parent) && $template->parent->is_template) {
                $template->parent->template_function_chain = $template;
            }
        }
    }

    /**
     * Template runtime function to call a template function
     *
     * @param string  $name           name of template function
     * @param object  $_smarty_tpl    calling template object
     * @param array   $params         array with calling parameter
     * @param string  $assign         optional template variable for result
     */
    public function _callTemplateFunction($name, $_smarty_tpl, $params, $assign) {
        if ($this->is_cache && isset($_smarty_tpl->cached->smarty_content->template_functions[$name])) {
            $content_ptr = $_smarty_tpl->cached->smarty_content;
        } else {
            $ptr = $tpl = $_smarty_tpl;
            while ($ptr != null && !isset($ptr->compiled->smarty_content->template_functions[$name])) {
                $ptr = $ptr->template_function_chain;
                if ($ptr == null && $tpl->parent->is_template) {
                    $ptr = $tpl = $tpl->parent;
                }
            }
            if (isset($ptr->compiled->smarty_content->template_functions[$name])) {
                $content_ptr = $ptr->compiled->smarty_content;
            }
        }
        if (isset($content_ptr)) {
            if (!empty($assign)) {
                ob_start();
            }
            $func_name = "smarty_template_function_{$name}";
            $content_ptr->$func_name($_smarty_tpl, $params);
            if (!empty($assign)) {
                $_smarty_tpl->assign($assign, ob_get_clean());
            }
            return true;
        }
        throw new SmartyRuntimeException("Call to undefined template function '{$name}'", $_smarty_tpl);
    }

    /**
     * Template runtime function to setup inheritance template chain
     *
     * @param string  $resource       the resource handle of the template file
     * @param mixed   $cache_id       cache id to be used with this template
     * @param mixed   $compile_id     compile id to be used with this template
     * @param integer $caching        cache mode
     * @param object  $parent         parent template object
     * @param bool    $is_child       is inheritance child template
     * @returns string template content
     */
    public function _createInheritanceTemplate($resource, $cache_id, $compile_id, $caching, $parent, $is_child = false) {
        // already in template cache?
        if ($parent->allow_ambiguous_resources) {
            $_templateId = Smarty_Resource::getUniqueTemplateName($parent, $resource) . $cache_id . $compile_id;
        } else {
            $_templateId = $parent->joined_template_dir . '#' . $resource . $cache_id . $compile_id;
        }

        if (isset($_templateId[150])) {
            $_templateId = sha1($_templateId);
        }
        if (isset(Smarty::$template_objects[$_templateId])) {
            $tpl = Smarty::$template_objects[$_templateId];
        } else {
            // clone new template object
            Smarty::$template_objects[$_templateId] = $tpl = clone $parent;
            unset($tpl->source, $tpl->compiled, $tpl->cached, $tpl->compiler, $tpl->mustCompile);
            $tpl->template_resource = $resource;
            $tpl->cache_id = $cache_id;
            $tpl->compile_id = $compile_id;
        }
        $tpl->inheritanceParentTemplate = null;
        $tpl->parent = $parent;
        $tpl->caching = $caching;
        $tpl->tpl_vars = $parent->tpl_vars;
        $tpl->is_child = $is_child;
        // set pointer in child template
        $ptr = $parent;
        while ($ptr->inheritanceParentTemplate !== null) {
            $ptr = $ptr->inheritanceParentTemplate;
        }
        $ptr->inheritanceParentTemplate = $tpl;
        $ptr->is_child = true;
        $tpl->parent = $ptr;
        return $tpl;
    }

    /**
     * Fetch output of extended child {block} or {$smarty.block.child}
     *
     * @param object   $template   tempate object
     * @param string   $name       name of block
     */
    public function _fetch_block_child_template($template, $name) {
        $output = '';
        $result_ptr = null;
        $parent_ptr = null;
        $ptr = $template;
        while (isset($ptr->is_template) && $ptr->is_template) {
            if (isset($ptr->compiled->smarty_content->block_functions[$name]['valid'])) {
                if (isset($ptr->compiled->smarty_content->block_functions[$name])) {
                    if (isset($ptr->compiled->smarty_content->block_functions[$name]['hide'])) {
                        break;
                    }
                    $result_ptr = $ptr;
                }
                if (isset($ptr->compiled->smarty_content->block_functions[$name]['child'])) {
                    $parent_ptr = $ptr;
                }
                if (isset($ptr->compiled->smarty_content->block_functions[$name]['overwrite'])) {
                    $parent_ptr = null;
                }
            }
            $ptr = $ptr->parent;
        }
        if (isset($parent_ptr)) {
            $result_ptr = $parent_ptr;
        }
        if ($result_ptr !== null) {
            $function = $result_ptr->compiled->smarty_content->block_functions[$name]['function'];
            $output = $result_ptr->compiled->smarty_content->$function($result_ptr);
            if (isset($result_ptr->compiled->smarty_content->block_functions[$name]['prepend'])) {
                $output .= $result_ptr->compiled->smarty_content->_fetch_block_parent_template($result_ptr, $name);
            } elseif (isset($result_ptr->compiled->smarty_content->block_functions[$name]['append'])) {
                $output = $result_ptr->compiled->smarty_content->_fetch_block_parent_template($result_ptr, $name) . $output;
            }
        }
        return $output;
    }

    /**
     * Fetch output of {$smarty.block.parent}
     *
     * @param object   $template   tempate object
     * @param string   $name        name of block
     */
    public function _fetch_block_parent_template($template, $name) {
        $ptr = $template->inheritanceParentTemplate;
        while (isset($ptr->is_template) && $ptr->is_template && !isset($ptr->compiled->smarty_content->block_functions[$name]['valid'])) {
            $ptr = $ptr->inheritanceParentTemplate;
        }
        if (isset($ptr->compiled->smarty_content->block_functions[$name])) {
            $function = $ptr->compiled->smarty_content->block_functions[$name]['function'];
            $output = $ptr->compiled->smarty_content->$function($ptr);
        } else {
            $output = '';
        }
        return $output;
    }

    /**
     * [util function] counts an array, arrayaccess/traversable or PDOStatement object
     *
     * @param mixed $value
     * @return int the count for arrays and objects that implement countable, 1 for other objects that don't, and 0 for empty elements
     */
    public function _count($value) {
        if (is_array($value) === true || $value instanceof Countable) {
            return count($value);
        } elseif ($value instanceof IteratorAggregate) {
            // Note: getIterator() returns a Traversable, not an Iterator
            // thus rewind() and valid() methods may not be present
            return iterator_count($value->getIterator());
        } elseif ($value instanceof Iterator) {
            return iterator_count($value);
        } elseif ($value instanceof PDOStatement) {
            return $value->rowCount();
        } elseif ($value instanceof Traversable) {
            return iterator_count($value);
        } elseif ($value instanceof ArrayAccess) {
            if ($value->offsetExists(0)) {
                return 1;
            }
        } elseif (is_object($value)) {
            return count($value);
        }
        return 0;
    }

    /**
     * Template code runtime function to create a local Smarty variable for array assignments
     *
     * @param string $tpl_var   tempate variable name
     * @param object $template  tempate object
     * @param bool   $nocache   cache mode of variable
     */
    public function _createLocalArrayVariable($tpl_var, $template, $nocache = false) {
        $result = $template->getVariable($tpl_var, null, true, false);
        if ($result === null) {
            $template->tpl_vars->{$tpl_var} = array('value' => array());
        } else {
            $template->tpl_vars->$tpl_var = $result;
        }
        $template->tpl_vars->{$tpl_var}['nocache'] = $nocache;
        if (!(is_array($template->tpl_vars->{$tpl_var}['value']) || $template->tpl_vars->{$tpl_var}['value'] instanceof ArrayAccess)) {
            settype($template->tpl_vars->{$tpl_var}['value'], 'array');
        }
    }

    /**
     * Template code runtime function to get subtemplate content
     *
     * @param string  $resource       the resource handle of the template file
     * @param object  $template       calling template object
     * @param mixed   $cache_id       cache id to be used with this template
     * @param mixed   $compile_id     compile id to be used with this template
     * @param integer $caching        cache mode
     * @param integer $cache_lifetime life time of cache data
     * @param array   $vars optional  variables to assign
     * @param int     $parent_scope   scope in which {include} should execute
     * @param string  $content_class  optional name of inline content class
     * @returns string template content
     */
    public function _getSubTemplate($resource, $template, $cache_id, $compile_id, $caching, $cache_lifetime, $data, $parent_scope, $content_class = null) {
        $cloned = false;
        // already in template cache?
        if ($template->allow_ambiguous_resources) {
            $_templateId = Smarty_Resource::getUniqueTemplateName($template, $resource) . $cache_id . $compile_id;
        } else {
            $_templateId = $template->joined_template_dir . '#' . $resource . $cache_id . $compile_id;
        }

        if (isset($_templateId[150])) {
            $_templateId = sha1($_templateId);
        }
        if ($template->caching && $caching && $caching != Smarty::CACHING_NOCACHE_CODE) {
            $template->cached_subtemplates[$_templateId] = array($resource, $cache_id, $compile_id, $caching, $cache_lifetime);
        }
        if (isset(Smarty::$template_objects[$_templateId])) {
            // clone cached template object because of possible recursive call
            $tpl = clone Smarty::$template_objects[$_templateId];
            $cloned = true;
        } else {
            // clone new template object
            $tpl = clone $template;
            unset($tpl->source, $tpl->compiled, $tpl->cached, $tpl->compiler, $tpl->mustCompile);
            $tpl->template_resource = $resource;
            $tpl->cache_id = $cache_id;
            $tpl->compile_id = $compile_id;
        }
        if (isset($content_class)) {
            // instance content class
            $tpl->compiled = new stdclass;
            $tpl->compiled->smarty_content = new $content_class($tpl);
        }
        $tpl->parent = $template;
        $tpl->caching = $caching;
        $tpl->cache_lifetime = $cache_lifetime;
        if ($parent_scope == Smarty::SCOPE_LOCAL) {
            $tpl->tpl_vars = clone $template->tpl_vars;
            $tpl->tpl_vars->___smarty__data = $tpl;
        } elseif ($parent_scope == Smarty::SCOPE_PARENT) {
            $tpl->tpl_vars = $template->tpl_vars;
        } elseif ($parent_scope == Smarty::SCOPE_GLOBAL) {
            $tpl->tpl_vars = Smarty::$global_tpl_vars;
        } elseif ($parent_scope == Smarty::SCOPE_ROOT) {
            if (($scope_ptr = $tpl->_getScopePointer($parent_scope)) != null) {
                $tpl->tpl_vars = $scope_ptr->tpl_vars;
            } else {
                $tpl->tpl_vars = new Smarty_Variable_Container($tpl);
            }
        }
        if (!empty($data)) {
            // set up variable values
            foreach ($data as $_key => $_val) {
                $tpl->tpl_vars->$_key = array('value' => $_val);
            }
        }
        if (isset($content_class)) {
            $result = $tpl->compiled->smarty_content->get_template_content($tpl);
            unset($tpl->tpl_vars, $tpl);
        } else {
            $result = $tpl->fetch(null, null, null, null, false, true, false);
            if ($cloned) {
                unset($tpl->tpl_vars, $tpl);
            }
        }
        return $result;
    }

}