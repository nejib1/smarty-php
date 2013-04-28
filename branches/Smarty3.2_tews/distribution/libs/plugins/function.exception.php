<?php

/**
 * Smarty plugin
 *
 * @package Smarty
 * @subpackage PluginsFunction
 */

/**
 * Smarty {exception} plugin
 *
 * Type:     function<br>
 * Name:     exception<br>
 * Purpose:  throw a SnartyRunTimeException
 *
 * @link http://www.smarty.net/docs/en/language.function.exception.tpl {exception}
 *       (Smarty online manual)
 * @author Uwe Tews
 * @param Smarty $template template object
 * @param string $message   exception messsage
 * @throws SmartyRunTimeException
 */
function smarty_function_exception(Smarty $template, $message = 'User Exception')
{
    throw new SmartyRunTimeException($message, $template);
}
