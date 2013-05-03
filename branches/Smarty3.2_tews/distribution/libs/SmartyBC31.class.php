<?php

/**
 * Project:     Smarty: the PHP compiling template engine
 * File:        SmartyBC31.class.php
 * SVN:         $Id: $
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * For questions, help, comments, discussion, etc., please join the
 * Smarty mailing list. Send a blank e-mail to
 * smarty-discussion-subscribe@googlegroups.com
 *
 * @link http://www.smarty.net/
 * @copyright 2008 New Digital Group, Inc.
 * @author Monte Ohrt <monte at ohrt dot com>
 * @author Uwe Tews
 * @author Rodney Rehm
 * @package Smarty
 * @subpackage SmartyBC
 */
/**
 * @ignore
 */
require(dirname(__FILE__) . '/Smarty.class.php');

/**
 * Dummy Template class for Smarty 3.1 BC
 *
 * @package Smarty
 * @subpackage SmartyBC
 */
class Smarty_Internal_Template extends Smarty
{

}

/**
 * Smarty Backward Compatibility Wrapper Class for Smarty 3.1
 *
 * @package Smarty
 * @subpackage SmartyBC
 */
class SmartyBC31 extends Smarty_Internal_Template
{

    /**
     * <<magic>> Generic getter.
     * Get Smarty or Template property
     *
     * @param string $property_name property name
     * @throws SmartyException
     * @return $this
     */
    public function __get($property_name)
    {
        // resolve 3.1 references from template to Smarty object
        if ($property_name == 'smarty') {
            return $this;
        }
        return parent::__get($property_name);
    }

    /**
     *  DEPRECATED FUNCTION
     * assigns values to template variables by reference
     *
     * @param string $tpl_var the template variable name
     * @param mixed &$value the referenced value to assign
     * @param boolean $nocache if true any output of this variable will be not cached
     * @return Smarty_Internal_Data current Smarty_Internal_Data (or Smarty or Smarty_Internal_Template) instance for chaining
     */
    public function assignByRef($tpl_var, &$value, $nocache = false)
    {
        if ($tpl_var != '') {
            $this->tpl_vars->$tpl_var = new Smarty_Variable(null, $nocache);
            $this->tpl_vars->$tpl_var->value = & $value;
        }
        return $this;
    }

    /**
     *  DEPRECATED FUNCTION
     * appends values to template variables by reference
     *
     * @param string $tpl_var the template variable name
     * @param mixed &$value  the referenced value to append
     * @param boolean $merge  flag if array elements shall be merged
     * @return Smarty_Internal_Data current Smarty_Internal_Data (or Smarty or Smarty_Internal_Template) instance for chaining
     */
    public function appendByRef($tpl_var, &$value, $merge = false)
    {
        if ($tpl_var != '' && isset($value)) {
            if (!isset($this->tpl_vars->$tpl_var)) {
                $this->tpl_vars->$tpl_var = new Smarty_Variable(array());
            }
            if (!@is_array($this->tpl_vars->$tpl_var->value)) {
                settype($this->tpl_vars->$tpl_var->value, 'array');
            }
            if ($merge && is_array($value)) {
                foreach ($value as $_key => $_val) {
                    $this->tpl_vars->$tpl_var->value[$_key] = & $value[$_key];
                }
            } else {
                $this->tpl_vars->$tpl_var->value[] = & $value;
            }
        }
        return $this;
    }

}
