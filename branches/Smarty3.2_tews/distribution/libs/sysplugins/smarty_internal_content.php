<?php

/**
 * Smarty Internal Plugin Smarty Internal Content
 *
 * This file contains the basic shared methods for precessing content of compiled and cached templates
 *
 *
 * @package Template
 * @author Uwe Tews
 */

/**
 * Class with shared content processing methods
 *
 *
 * @package Template
 */
class Smarty_Internal_Content extends Smarty_Internal_Magic_Error
{

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
    public $tpl_obj_functions = array();

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
     * flag if content is inheritance child
     *
     * @var bool
     */
    public $is_inheritance_child = false;

    /**
     *  Template variable of this scope
     *
     * @var Smarty_Variable_Scope
     */
    public $scope = null;

    /**
     * constructor
     *
     */
    public function __construct($tpl_obj, $mixed_obj)
    {
        // check if class is still valid
        $this->is_valid = true;
        if ($this->version != Smarty::SMARTY_VERSION) {
            $this->is_valid = false;
        } else if ((!$this->is_cache && $tpl_obj->compile_check) || ($this->is_cache && ($tpl_obj->compile_check === true || $tpl_obj->compile_check === Smarty::COMPILECHECK_ON)) && !empty($this->file_dependency)) {
            foreach ($this->file_dependency as $_file_to_check) {
                if ($_file_to_check[2] == 'file' || $_file_to_check[2] == 'php') {
                    if ($tpl_obj->source->filepath == $_file_to_check[0] && isset($tpl_obj->source->timestamp)) {
                        // do not recheck current template
                        $mtime = $tpl_obj->source->timestamp;
                    } else {
                        // file and php types can be checked without loading the respective resource handlers
                        $mtime = @filemtime($_file_to_check[0]);
                    }
                } elseif ($_file_to_check[2] == 'string') {
                    continue;
                } else {
                    $source = $tpl_obj->_loadHandler(Smarty::SOURCE, $_file_to_check[0]);
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

        $mixed_obj->isValid = $this->is_valid;

        if (!$this->is_cache) {
            if (!empty($this->template_functions) && isset($tpl_obj->parent) && $tpl_obj->parent->usage == Smarty::IS_TEMPLATE) {
                $tpl_obj->parent->template_function_chain = $tpl_obj;
            }
        }
    }

    /**
     * Template runtime function to call a template function
     *
     * @param string $name           name of template function
     * @param Smarty $tpl_obj       calling template object
     * @param Smarty_Variable_Scope $_scope
     * @param array $params         array with calling parameter
     * @param string $assign         optional template variable for result
     * @throws SmartyRuntimeException
     * @return bool
     */
    public function _callTemplateFunction($name, $tpl_obj, $_scope, $params, $assign)
    {
        if ($this->is_cache && isset($tpl_obj->cached->smarty_content->template_functions[$name])) {
            $content_ptr = $tpl_obj->cached->smarty_content;
        } else {
            $ptr = $tpl = $tpl_obj;
            while ($ptr != null && !isset($ptr->compiled->smarty_content->template_functions[$name])) {
                $ptr = $ptr->template_function_chain;
                if ($ptr == null && $tpl->parent->usage == Smarty::IS_TEMPLATE) {
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
            $content_ptr->$func_name($tpl_obj, $_scope, $params);
            if (!empty($assign)) {
                $tpl_obj->assign($assign, ob_get_clean());
            }
            return true;
        }
        throw new SmartyRuntimeException("Call to undefined template function '{$name}'", $tpl_obj);
    }

    /**
     * [util function] counts an array, arrayaccess/traversable or PDOStatement object
     *
     * @param mixed $value
     * @return int the count for arrays and objects that implement countable, 1 for other objects that don't, and 0 for empty elements
     */
    public function _count($value)
    {
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
     * @param Smarty_Variable_Scope $_scope  variable scope
     * @param bool $nocache   cache mode of variable
     */
    public function _createLocalArrayVariable($tpl_var, $_scope, $nocache = false)
    {
        if (isset($_scope->{$tpl_var})) {
            $_scope->{$tpl_var} = clone $_scope->{$tpl_var};
        } elseif ($result = $_scope->___attributes->tpl_ptr->getVariable($tpl_var, $_scope->___attributes->tpl_ptr->parent, true, false)) {
            $_scope->{$tpl_var} = clone $result;
        } else {
            $_scope->{$tpl_var} = new Smarty_Variable(array(), $nocache);
            return;
        }
        $_scope->{$tpl_var}->nocache = $nocache;
        if (!(is_array($_scope->{$tpl_var}->value) || $_scope->{$tpl_var}->value instanceof ArrayAccess)) {
            settype($_scope->{$tpl_var}->value, 'array');
        }
    }


    /**
     * Template code runtime function to get subtemplate content
     *
     * @param string $tpl_obj_resource       the resource handle of the template file
     * @param Smarty $parent_tpl_obj       calling template object
     * @param mixed $cache_id       cache id to be used with this template
     * @param mixed $compile_id     compile id to be used with this template
     * @param integer $caching        cache mode
     * @param integer $cache_lifetime life time of cache data
     * @param array $data array with parameter template variables
     * @param int $scope_type   scope in which {include} should execute
     * @param Smarty_Variable_Scope $_scope
     * @param string $content_class  optional name of inline content class
     * @return string template content
     */
    public function _getSubTemplate($tpl_obj_resource, $parent_tpl_obj, $cache_id, $compile_id, $caching, $cache_lifetime, $data, $scope_type, $_scope, $content_class = null)
    {
        if (isset($content_class)) {
            // clone new template object
            $tpl_obj = clone $parent_tpl_obj;
            $tpl_obj->template_resource = $tpl_obj_resource;
            $tpl_obj->cache_id = $cache_id;
            $tpl_obj->compile_id = $compile_id;
            $tpl_obj->parent = $parent_tpl_obj;

            // instance content class
            $tpl_obj->compiled = new stdclass;
            $tpl_obj->compiled->smarty_content = new $content_class($tpl);
            $result = $tpl_obj->compiled->getRenderedTemplate($tpl_obj, $_scope, $scope_type, $data, $no_output_filter);
//            $result = $tpl->compiled->smarty_content->get_template_content($tpl);
            unset($tpl_obj->tpl_vars, $tpl_obj);
            return $result;
        } else {
            if ($parent_tpl_obj->caching && $caching && $caching != Smarty::CACHING_NOCACHE_CODE) {
                $parent_tpl_obj->cached_subtemplates[$tpl_obj_resource] = array($tpl_obj_resource, $cache_id, $compile_id, $caching, $cache_lifetime);
            }
            return $parent_tpl_obj->fetch($tpl_obj_resource, $cache_id, $compile_id, $parent_tpl_obj, false, true, $data, $scope_type, $caching, $cache_lifetime, $_scope);
        }

    }

    /**
     * [util function] to use either var_export or unserialize/serialize to generate code for the
     * cachevalue optionflag of {assign} tag
     *
     * @param mixed $var Smarty variable value
     * @throws SmartyException
     * @return string PHP inline code
     */
    public function _exportCacheValue($var)
    {
        if (is_int($var) || is_float($var) || is_bool($var) || is_string($var) || (is_array($var) && !is_object($var) && !array_reduce($var, array($this, '_checkAarrayCallback')))) {
            return var_export($var, true);
        }
        if (is_resource($var)) {
            throw new SmartyException('Cannot serialize resource');
        }
        return 'unserialize(\'' . serialize($var) . '\')';
    }

    /**
     * callback used by _export_cache_value to check arrays recursively
     *
     * @param bool $flag status of previous elements
     * @param mixed $element array element to check
     * @throws SmartyException
     * @return bool status
     */
    private function _checkAarrayCallback($flag, $element)
    {
        if (is_resource($element)) {
            throw new SmartyException('Cannot serialize resource');
        }
        $flag = $flag || is_object($element) || (!is_int($element) && !is_float($element) && !is_bool($element) && !is_string($element) && (is_array($element) && array_reduce($element, array($this, '_checkAarrayCallback'))));
        return $flag;
    }

    /**
     * Template code runtime function to load config varibales
     *
     * @param object $tpl_obj       calling template object
     */
    public function _loadConfigVars($tpl_obj)
    {
        $ptr = $tpl_obj->parent;
        $this->_loadConfigValuesInScope($tpl_obj, $ptr->tpl_vars);
        $ptr = $ptr->parent;
        if ($tpl_obj->tpl_vars->___config_scope == 'parent' && $ptr != null) {
            $this->_loadConfigValuesInScope($tpl_obj, $ptr->tpl_vars);
        }
        if ($tpl_obj->tpl_vars->___config_scope == 'root' || $tpl_obj->tpl_vars->___config_scope == 'global') {
            while ($ptr != null && $ptr->usage == Smarty::IS_TEMPLATE) {
                $this->_loadConfigValuesInScope($tpl_obj, $ptr->tpl_vars);
                $ptr = $ptr->parent;
            }
        }
        if ($tpl_obj->tpl_vars->___config_scope == 'root') {
            while ($ptr != null) {
                $this->_loadConfigValuesInScope($tpl_obj, $ptr->tpl_vars);
                $ptr = $ptr->parent;
            }
        }
        if ($tpl_obj->tpl_vars->___config_scope == 'global') {
            $this->_loadConfigValuesInScope($tpl_obj, Smarty::$global_tpl_vars);
        }
    }

    /**
     * Template code runtime function to load config varibales into a single scope
     *
     * @param object $tpl_obj       calling template object
     * @param object $tpl_vars       variable container of scope
     */
    public function _loadConfigValuesInScope($tpl_obj, $tpl_vars)
    {
        foreach ($this->config_data['vars'] as $var => $value) {
            if ($tpl_obj->config_overwrite || !isset($tpl_vars->$var)) {
                $tpl_vars->$var = $value;
            } else {
                $tpl_vars->$var = array_merge((array)$tpl_vars->{$var}, (array)$value);
            }
        }
        if (isset($this->config_data['sections'][$tpl_obj->tpl_vars->___config_sections])) {
            foreach ($this->config_data['sections'][$tpl_obj->tpl_vars->___config_sections]['vars'] as $var => $value) {
                if ($tpl_obj->config_overwrite || !isset($tpl_vars->$var)) {
                    $tpl_vars->$var = $value;
                } else {
                    $tpl_vars->$var = array_merge((array)$tpl_vars->{$var}, (array)$value);
                }
            }
        }
    }

}