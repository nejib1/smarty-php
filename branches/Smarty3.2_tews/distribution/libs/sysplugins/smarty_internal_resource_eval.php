<?php

/**
 * Smarty Internal Plugin Resource Eval
 *
 *
 * @package TemplateResources
 * @author Uwe Tews
 * @author Rodney Rehm
 */

/**
 * Smarty Internal Plugin Resource Eval
 *
 * Implements the strings as resource for Smarty template
 *
 * {@internal unlike string-resources the compiled state of eval-resources is NOT saved for subsequent access}}
 *
 *
 * @package TemplateResources
 */
class Smarty_Internal_Resource_Eval extends Smarty_Internal_Resource_String
{
    /*
     * set recompiled flag
     * @var boolean
     */
    public $recompiled = true;
}
