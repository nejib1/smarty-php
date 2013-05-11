<?php

/**
 * Smarty Internal Plugin Data
 *
 * This file contains the basic classes and methods for template and variable creation
 *
 *
 * @package Template
 * @author Uwe Tews
 */

/**
 * Base class with template and variable methods
 *
 *
 * @package Template
 */
class Smarty_Internal_Data extends Smarty_Internal_Magic_Error
{

    /**
     * template variables
     *
     * @var array
     */
    public $tpl_vars = null;

    /**
     * parent template (if any)
     *
     * @var Smarty
     */
    public $parent = null;

    /**
     * usage of Smarty_Internal_Data
     * @var int
     * @uses IS_SMARTY as possible value
     * @uses IS_TEMPLATE as possible value
     * @uses IS_CONFIG as possible value
     * @uses IS_DATA as possible value
     */
    public $usage = null;


    /**
     * assigns a Smarty variable
     *
     * @param array|string $tpl_var the template variable name(s)
     * @param mixed $value   the value to assign
     * @param boolean $nocache if true any output of this variable will be not cached
     * @return Smarty_Internal_Data current Smarty_Internal_Data (or Smarty) instance for chaining
     */
    public function assign($tpl_var, $value = null, $nocache = false)
    {
        if (is_array($tpl_var)) {
            foreach ($tpl_var as $varname => $value) {
                if ($varname != '') {
                    $this->tpl_vars->$varname = new Smarty_Variable($value, $nocache);
                }
            }
        } else {
            if ($tpl_var != '') {
                $this->tpl_vars->$tpl_var = new Smarty_Variable($value, $nocache);
            }
        }
        return $this;
    }

    /**
     * assigns a Smarty variable to the current object and all parent elements
     *
     * @param array|string $tpl_var the template variable name(s)
     * @param mixed $value   the value to assign
     * @param boolean $nocache if true any output of this variable will be not cached
     * @return Smarty_Internal_Data current Smarty_Internal_Data (or Smarty) instance for chaining
     */
    public function assignParents($tpl_var, $value = null, $nocache = false)
    {
        $this->assign($tpl_var, $value, $nocache);
        $node = $this->parent;

        while ($node) {
            $node->assign($tpl_var, $value, $nocache);
            $node = $node->parent;
        }
        return $this;
    }

    /**
     * assigns a global Smarty variable
     *
     * @param string $varname the global variable name
     * @param mixed $value   the value to assign
     * @param boolean $nocache if true any output of this variable will be not cached
     * @return Smarty_Internal_Data current Smarty_Internal_Data (or Smarty) instance for chaining
     */
    public function assignGlobal($varname, $value = null, $nocache = false)
    {
        if ($varname != '') {
            Smarty::$global_tpl_vars->$varname = new Smarty_Variable($value, $nocache);
        }
        // TODO check behavior
        //        $ptr = $this;
        //        while (isset($ptr->IS_TEMPLATE) && $ptr->IS_TEMPLATE) {
        //            $ptr->assign($tpl_var, $value, $nocache);
        //            $ptr = $ptr->parent;
        //        }

        return $this;
    }


    /**
     * appends values to template variables
     *
     * @param array|string $tpl_var the template variable name(s)
     * @param mixed $value   the value to append
     * @param boolean $merge   flag if array elements shall be merged
     * @param boolean $nocache if true any output of this variable will be not cached
     * @return Smarty_Internal_Data current Smarty_Internal_Data (or Smarty) instance for chaining
     */
    public function append($tpl_var, $value = null, $merge = false, $nocache = false)
    {
        if (is_array($tpl_var)) {
            // $tpl_var is an array, ignore $value
            foreach ($tpl_var as $varname => $_val) {
                if ($varname != '') {
                    if (!isset($this->tpl_vars->$varname)) {
                        $tpl_var_inst = $this->getVariable($varname, null, true, false);
                        if ($tpl_var_inst === null) {
                            $this->tpl_vars->$varname = new Smarty_Variable(null, $nocache);
                        } else {
                            $this->tpl_vars->$varname = clone $tpl_var_inst;
                        }
                    }
                    if (!(is_array($this->tpl_vars->$varname->value) || $this->tpl_vars->$varname->value instanceof ArrayAccess)) {
                        settype($this->tpl_vars->$varname->value, 'array');
                    }
                    if ($merge && is_array($_val)) {
                        foreach ($_val as $_mkey => $_mval) {
                            $this->tpl_vars->$varname->value[$_mkey] = $_mval;
                        }
                    } else {
                        $this->tpl_vars->$varname->value[] = $_val;
                    }
                }
            }
        } else {
            if ($tpl_var != '' && isset($value)) {
                if (!isset($this->tpl_vars->$tpl_var)) {
                    $tpl_var_inst = $this->getVariable($tpl_var, null, true, false);
                    if ($tpl_var_inst === null) {
                        $this->tpl_vars->$tpl_var = new Smarty_Variable(null, $nocache);
                    } else {
                        $this->tpl_vars->$tpl_var = clone $tpl_var_inst;
                    }
                }
                if (!(is_array($this->tpl_vars->$tpl_var->value) || $this->tpl_vars->$tpl_var->value instanceof ArrayAccess)) {
                    settype($this->tpl_vars->$tpl_var->value, 'array');
                }
                if ($merge && is_array($value)) {
                    foreach ($value as $_mkey => $_mval) {
                        $this->tpl_vars->$tpl_var->value[$_mkey] = $_mval;
                    }
                } else {
                    $this->tpl_vars->$tpl_var->value[] = $value;
                }
            }
        }

        return $this;
    }


    /**
     * clear the given assigned template variable.
     *
     * @param string|array $tpl_var the template variable(s) to clear
     * @return Smarty_Internal_Data current Smarty_Internal_Data (or Smarty) instance for chaining
     */
    public function clearAssign($tpl_var)
    {
        if (is_array($tpl_var)) {
            foreach ($tpl_var as $curr_var) {
                unset($this->tpl_vars->$curr_var);
            }
        } else {
            unset($this->tpl_vars->$tpl_var);
        }

        return $this;
    }

    /**
     * clear all the assigned template variables.
     * @return Smarty_Internal_Data current Smarty_Internal_Data (or Smarty) instance for chaining
     */
    public function clearAllAssign()
    {
        $old_attributes = $this->tpl_vars->___attributes;
        $this->tpl_vars = new Smarty_Variable_Scope();
        $this->tpl_vars->___attributes = $old_attributes;
        return $this;
    }

    /**
     * Returns a single or all template variables
     *
     * @param string $varname        variable name or null
     * @param string $_ptr           optional pointer to data object
     * @param boolean $search_parents include parent templates?
     * @return string variable value or or array of variables
     */
    public function getTemplateVars($varname = null, $_ptr = null, $search_parents = true)
    {
        if (isset($varname)) {
            $result = $this->getVariable($varname, $_ptr, $search_parents, false);
            if ($result === null) {
                return false;
            } else {
                return $result->value;
            }
        } else {
            $_result = array();
            if ($_ptr === null) {
                $_ptr = $this;
            }
            while ($_ptr !== null) {
                foreach ($_ptr->tpl_vars AS $varname => $data) {
                    if (strpos($varname, '___') !== 0 && !isset($_result[$varname])) {
                        $_result[$varname] = $data->value;
                    }
                }
                // not found, try at parent
                if ($search_parents) {
                    $_ptr = $_ptr->parent;
                } else {
                    $_ptr = null;
                }
            }
            if ($search_parents && isset(Smarty::$global_tpl_vars)) {
                foreach (Smarty::$global_tpl_vars AS $varname => $data) {
                    if (strpos($varname, '___') !== 0 && !isset($_result[$varname])) {
                        $_result[$varname] = $data->value;
                    }
                }
            }
            return $_result;
        }
    }

    /**
     * gets the object of a template variable
     *
     * @param string $varname the name of the Smarty variable
     * @param object $_ptr     optional pointer to data object
     * @param boolean $search_parents search also in parent data
     * @param boolean $error_enable enable error handling
     * @param null $property optional requested variable property
     * @throws SmartyRunTimeException
     * @return mixed  Smarty_variable object|property of variable
     */
    public function getVariable($varname, $_ptr = null, $search_parents = true, $error_enable = true, $property = null)
    {
        if ($_ptr === null) {
            $_ptr = $this;
        }
        while ($_ptr !== null) {
            if (isset($_ptr->tpl_vars->$varname)) {
                // found it, return it
                if ($property === null) {
                    return $_ptr->tpl_vars->$varname;
                } else {
                    return isset($_ptr->tpl_vars->$varname->$property) ? $_ptr->tpl_vars->$varname->$property : null;
                }
            }
            // not found, try at parent
            if ($search_parents) {
                $_ptr = $_ptr->parent;
            } else {
                $_ptr = null;
            }
        }
        if (isset(Smarty::$global_tpl_vars->$varname)) {
            // found it, return it
            if ($property === null) {
                return Smarty::$global_tpl_vars->$varname;
            } else {
                return isset(Smarty::$global_tpl_vars->$varname->$property) ? Smarty::$global_tpl_vars->$varname->$property : null;
            }
        }
        if ($this->usage == Smarty::IS_DATA) {
            $error_unassigned = $this->smarty->error_unassigned;
        } else {
            $error_unassigned = $this->error_unassigned;
        }
        if (strpos($varname, '___config_var_') !== 0) {
            if (isset($this->default_variable_handler_func)) {
                $value = null;
                if (call_user_func_array($this->default_variable_handler_func, array($varname, &$value, $this))) {
                    if ($value instanceof Smarty_Variable) {
                        $var = $value;
                    } else {
                        $var = new Smarty_Variable($value);
                    }
                    if ($property === null) {
                        return $var;
                    } else {
                        return isset($var->$property) ? $var->$property : null;
                    }
                }
            }
            if ($error_unassigned != Smarty::UNASSIGNED_IGNORE && $error_enable) {
                $err_msg = "Unassigned template variable '{$varname}'";
                if ($error_unassigned == Smarty::UNASSIGNED_NOTICE) {
                    // force a notice
                    trigger_error($err_msg);
                } elseif ($error_unassigned == Smarty::UNASSIGNED_EXCEPTION) {
                    throw new SmartyRunTimeException($err_msg, $this);
                }
            }
            $var = new Smarty_Variable();
            if ($property === null) {
                return $var;
            } else {
                return isset($var->$property) ? $var->$property : null;
            }

        } else {
            $real_varname = substr($varname, 14);
            if (isset($this->default_config_variable_handler_func)) {
                $value = null;
                if (call_user_func_array($this->default_config_variable_handler_func, array($real_varname, &$value, $this))) {
                    return $value;
                }
            }
            if ($error_unassigned != Smarty::UNASSIGNED_IGNORE && $error_enable) {
                $err_msg = "Unassigned config variable '{$real_varname}'";
                if ($error_unassigned == Smarty::UNASSIGNED_NOTICE) {
                    // force a notice
                    trigger_error($err_msg);
                } elseif ($error_unassigned == Smarty::UNASSIGNED_EXCEPTION) {
                    throw new SmartyRunTimeException($err_msg, $this);
                }
            }
        }
        // unassigned variable which shall be ignored
        return null;
    }

    /**
     * Returns a single or all config variables
     *
     * @param string $varname variable name or null
     * @param bool $search_parents true to search also in parent templates
     * @return string variable value or or array of variables
     */
    public function getConfigVars($varname = null, $search_parents = true)
    {
        $_ptr = $this;
        if (isset($varname)) {
            $result = $this->getVariable('___config_var_' . $varname, $_ptr, $search_parents, false);
            return $result;
        } else {
            $_result = array();
            while ($_ptr !== null) {
                foreach ($_ptr->tpl_vars AS $varname => $data) {
                    $real_varname = substr($varname, 14);
                    if (strpos($varname, '___config_var_') === 0 && !isset($_result[$real_varname])) {
                        $_result[$real_varname] = $data;
                    }
                }
                // not found, try at parent
                if ($search_parents) {
                    $_ptr = $_ptr->parent;
                } else {
                    $_ptr = null;
                }
            }
            return $_result;
        }
    }

    /**
     * Deassigns a single or all config variables
     *
     * @param string $varname variable name or null
     * @return Smarty_Internal_Data current Smarty_Internal_Data (or Smarty) instance for chaining
     */
    public function clearConfig($varname = null)
    {
        if (isset($varname)) {
            unset($this->tpl_vars->{'___config_var_' . $varname});
        } else {
            foreach ($this->tpl_vars as $key => $var) {
                if (strpos($key, '___config_var_') === 0) {
                    unset($this->tpl_vars->$key);
                }
            }
        }
        return $this;
    }

    /**
     * load a config file, optionally load just selected sections
     *
     * @param string $config_file filename
     * @param mixed $sections    array of section names, single section or null
     * @param string $scope_type template scope into which config file shall be loaded
     * @return Smarty_Internal_Data current Smarty_Internal_Data (or Smarty) instance for chaining
     */
    public function configLoad($config_file, $sections = null, $scope_type = 'local')
    {
        $ptr = $this->usage == Smarty::IS_DATA ? $this->smarty : $this;
        $tpl = $ptr->_getTemplateObj($config_file, null, null, $this, true);
        $tpl->tpl_vars->___config_sections = $sections;
        $tpl->tpl_vars->___config_scope = $scope_type;
        $tpl->compiled->getRenderedTemplate($tpl);
        return $this;
    }

    /**
     * gets  a stream variable
     *
     * @param string $variable the stream of the variable
     * @throws SmartyException
     * @return mixed the value of the stream variable
     */
    public function getStreamVariable($variable)
    {
        $_result = '';
        $fp = fopen($variable, 'r+');
        if ($fp) {
            while (!feof($fp) && ($current_line = fgets($fp)) !== false) {
                $_result .= $current_line;
            }
            fclose($fp);
            return $_result;
        }

        if ($this->smarty->error_unassigned) {
            throw new SmartyException('Undefined stream variable "' . $variable . '"');
        } else {
            return null;
        }
    }

    /**
     *
     *  runtime routine to create a new variable scope
     *
     * @param Smarty $parent
     * @param int $scope_type
     * @param null $data
     * @return array|null|\Smarty_Variable_Scope|\stdClass
     */
    public function _buildScope($parent, $scope_type = Smarty::SCOPE_LOCAL, $data = null)
    {
        if (!isset($parent)) {
            $parent = $this->tpl_vars;
        } elseif (!($parent instanceof Smarty_Variable_Scope)) {
            $parent = $parent->tpl_vars;
        }
        switch ($scope_type) {
            case Smarty::SCOPE_LOCAL:
                if ($parent->___attributes->usage == Smarty::IS_SMARTY || $parent->___attributes->usage == Smarty::IS_TEMPLATE) {
                    // we can just  clone it
                    $scope = clone $parent;
                } else {
                    $scope = $this->_mergeScopes($parent);
                }
                $scope->___attributes->parent_scope = $parent;
                $scope->___attributes->usage = Smarty::IS_TEMPLATE;
                $scope->___attributes->name = $this->template_resource;
                break;
            case Smarty::SCOPE_PARENT:
                $scope = $parent;
                break;
            case Smarty::SCOPE_GLOBAL:
                $scope = Smarty::$global_tpl_vars;
                break;
            case Smarty::SCOPE_ROOT:
                $scope = $parent;
                while (isset($scope->___attributes->parent_scope)) {
                    $scope = $scope->___attributes->parent_scope;
                }
                break;
        }

        // create special smarty variable
        if (!isset($scope->smarty)) {
            $scope->smarty = new Smarty_Variable();
        }
        // fill data if present
        if ($data != null) {
            // set up variable values
            foreach ($data as $varname => $value) {
                $scope->$varname = new Smarty_Variable($value);
            }
        }
        return $scope;
    }


    /**
     *
     *  merge tpl vars
     *
     * @param Smarty_Variable_Scope $ptr current scope
     * @return Smarty_Variable_Scope  merged tpl vars
     */
    public function _mergeScopes($ptr)
    {
        // Smarty::triggerCallback('trace', ' merge tpl ');

        if (isset($ptr->___attributes->parent_scope)) {
            $scope = $this->_mergeScopes($ptr->___attributes->parent_scope);
            foreach ($ptr as $var => $data) {
                $scope->$var = $data;
            }
            return $scope;
        } else {
            return clone $ptr;
        }
    }
}


/**
 * class for the Smarty data object
 *
 * The Smarty data object will hold Smarty variables in the current scope
 *
 *
 * @package Template
 */
class Smarty_Data extends Smarty_Internal_Data
{

    public $tpl_vars = null;

    /**
     * create Smarty data object
     *
     * @param Smarty $smarty  object of Smarty instance
     * @param Smarty_Internal_Data|array $parent  parent object or variable array
     * @param string $scope_name name of variable scope
     * @throws SmartyException
     */
    public function __construct(Smarty $smarty, $parent = null, $scope_name = 'Data unnamed')
    {
        $this->usage = Smarty::IS_DATA;

        // variables passed as array?
        if (is_array($parent)) {
            $data = $parent;
            $parent = null;
        } else {
            $data = null;
        }

        // create variabale container
        $this->tpl_vars = new Smarty_Variable_Scope($smarty, $parent, Smarty::IS_DATA, $scope_name);

        //load optional variable array
        if (isset($data)) {
            foreach ($data as $_key => $_val) {
                $this->tpl_vars->$_key = new Smarty_Variable($_val);
            }
        }
    }

}

/**
 * class for a variable scope
 *
 * This class holds all assigned variables
 * The special property ___attributes is used to store control information
 *
 */
class Smarty_Variable_Scope
{

    /**
     * constructor to create backlink to Smarty|Smarty_Data
     *
     * @param  Smarty $tpl_obj  object this instance belongs to
     * @param  null|Smarty|Smarty_Data|Smarty_Variable_Scope $parent parent scope of tpl vars
     * @param int $scope_type type of scope
     * @param  string $name  name of scope
     * @throws SmartyException
     */
    public function __construct($tpl_obj = null, $parent = null, $scope_type = Smarty::IS_TEMPLATE, $name = '')
    {
        // Smarty::triggerCallback('trace', ' construct varcontainer');

        // determine type of parent object
        if ($parent == null || $parent instanceof Smarty_Variable_Scope) {
            $parent_ptr = $parent;
        } elseif ($parent instanceof Smarty || $parent instanceof Smarty_Data) {
            $parent_ptr = $parent->tpl_vars;
        } else {
            throw new SmartyException("Wrong type of parent parameter at Smarty_Variable_Scope");
        }

        $this->___attributes = new stdClass();
        $this->___attributes->tpl_ptr = $tpl_obj;
        $this->___attributes->parent_scope = $parent_ptr;
        $this->___attributes->usage = $scope_type;
        $this->___attributes->name = $name;
    }

    /**
     * magic __get function called at access of unknown variable
     *
     * @param string $varname  name of variable
     * @return mixed Smarty_Variable object | null
     */
    public function __get($varname)
    {
        return $this->$varname = $this->___attributes->tpl_ptr->getVariable($varname, $this->___attributes->tpl_ptr->parent);
    }


    public function __clone()
    {
        //Smarty::triggerCallback('trace', ' clone varcontainer');
        $this->___attributes = clone $this->___attributes;
    }

    /**
    public function __destruct()
    {
    //Smarty::triggerCallback('trace', ' destruct varcontainer');
    }
     */

}

/**
 * class for the Smarty variable object
 *
 * This class defines the Smarty variable object
 *
 *
 * @package Template
 */
class Smarty_Variable
{

    /**
     * variable value
     *
     * @var mixed
     */
    public $value = null;

    /**
     * if true any output of this variable will be not cached
     *
     * @var boolean
     */
    public $nocache = false;


    /**
     * create Smarty variable object
     *
     * @param mixed $value   the value to assign
     * @param boolean $nocache if true any output of this variable will be not cached
     */
    public function __construct($value = null, $nocache = false)
    {
        $this->value = $value;
        $this->nocache = $nocache;
    }

    /**
     * <<magic>> String conversion
     *
     * @return string
     */
    /**
    public function __toString()
    {
    return (string)$this->value;
    }
     */

}


