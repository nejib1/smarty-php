<?php

/*
 * Smarty plugin
 * -------------------------------------------------------------
 * File:       function.html_radios.php
 * Type:       function
 * Name:       html_radios	
 * Version:    1.0
 * Date:       24.Feb.2003
 * Purpose:    Prints out a list of radio input types
 * Input:      name       (optional) - string default "radio"
 *             values     (required) - array
 *             radios     (optional) - associative array
 *             checked    (optional) - array default not set
 *             separator  (optional) - ie <br> or &nbsp;
 *             output     (optional) - without this one the buttons don't have names
 * Author:     Christopher Kvarme <christopher.kvarme@flashjab.com>
 * Credits:    Monte Ohrt <monte@ispi.net>
 * Examples:   {html_radios values=$ids output=$names}
 *             {html_radios values=$ids name='box' separator='<br>' output=$names}
 *             {html_radios values=$ids checked=$checked separator='<br>' output=$names}
 * -------------------------------------------------------------
 */
function smarty_function_html_radios($params, &$smarty)
{
   require_once $smarty->_get_plugin_filepath('shared','escape_special_chars');

   $name = 'radio';
   $values = null;
   $radios = null;
   $checked = null;
   $separator = '';
   $output = null;
   $extra = '';
  
   foreach($params as $_key => $_val) {	
      switch($_key) {
      case 'name':
      case 'separator':
      case 'checked':
	 $$_key = (string)$_val;
	 break;

      case 'radios':
	 $$_key = (array)$_val;
	 break;

      case 'values':
      case 'output':
	 $$_key = array_values((array)$_val);      
	 break;

      default:
	 $extra .= ' '.$_key.'="'.smarty_function_escape_special_chars($_val).'"';
	 break;					
      }
   }

   if (!isset($radios) && !isset($values))
      return ''; /* raise error here? */

   $_html_result = '';

   if (isset($radios) && is_array($radios)) {

      foreach ((array)$radios as $_key=>$_val)
	 $_html_result .= smarty_function_html_radios_output($name, $_key, $_val, $checked, $extra, $separator);
   
   } else {  

      foreach ((array)$values as $_i=>$_key) {
	 $_val = isset($output[$_i]) ? $output[$_i] : '';
	 $_html_result .= smarty_function_html_radios_output($name, $_key, $_val, $checked, $extra, $separator);      
      }

   }

   return $_html_result;
  
}

function smarty_function_html_radios_output($name, $value, $output, $checked, $extra, $separator) {
   $_output = '<input type="radio" name="' 
      . smarty_function_escape_special_chars($name) . '" value="' 
      . smarty_function_escape_special_chars($value) . '"';

   if ($value==$checked) {
      $_output .= ' checked="checked"';
   }
   $_output .= $extra . ' />' . $output . $separator . "\n";
  
   return $_output;        
}

?>
