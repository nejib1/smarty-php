<?php

/**
 * Smarty Internal Plugin Smarty Template Compiler Base
 *
 * This file contains the basic classes and methods for compiling Smarty templates with lexer/parser
 *
 *
 * @package Compiler
 * @author Uwe Tews
 */
/**
 * @ignore
 */

/**
 * Class Smarty_Compiler
 *
 *
 * @package Compiler
 */
class Smarty_Compiler extends Smarty_Internal_Code
{
    public static function  load(Smarty $tpl_obj)
    {
        if ($tpl_obj->usage == Smarty::IS_TEMPLATE) {
            return new Smarty_Internal_Template_Compiler('Smarty_Internal_Template_Lexer', 'Smarty_Internal_Template_Parser', $tpl_obj);
        } else {
            return new Smarty_Internal_Config_Compiler('Smarty_Internal_ConfigFile_Lexer', 'Smarty_Internal_ConfigFile_Parser', $tpl_obj);
        }
    }
}
