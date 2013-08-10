# Documentation ToDo #

these changes must reflect on the documentation at some point…

## Undocumented ##

* $error_unassigned
* $security_class
* $security_policy
* $plugin_search_order
* $_file_perms
* $_dir_perms
* $default_plugin_handler_func
* Smarty_Internal_Templatebase::registerDefaultTemplateHandler()
* Smarty_Internal_Templatebase::registerDefaultConfigHandler()
* Smarty_Internal_Template::fetch() display() isCached() clear() into Smarty::createTemplate() docs?
* $disable_core_plugins
* outputfilter.trimwhitespace.php (don't forget @link in file!)
* modifiercompiler.noprint.php (don't forget @link in file!)
* How does registerObject() handle __call()ed functions and __get()ed attributes?
* {$x = $k cachevalue} to make the value of $k available as nocache variable $x (Forum Topic 20123)
* Smarty_Internal_Template::assignCached(), Smarty_Internal_Template::getCachedVars(), Smarty_Internal_Template::findRootTemplate()
* $template->is_nocache and {$smarty.is_nocache}
* Smarty::registerCallback()
* Smarty_Internal_Data::assignParents()


## Properties ##

* Smarty::$default_variable_handler_func_ - Smarty::registerDefaultVariableHandler()
    * (boolean) function($name, &$value, Smarty|Smarty_Internal_Template $context)
* Smarty::$default_config_variable_handler_func - Smarty::registerDefaultConfigVariableHandler()
    * (boolean) function($name, &$value, Smarty|Smarty_Internal_Template $context)


## Syntax ##

* {$x = $k cachevalue} 
* {\some\namespaced\ClassName::CONST} (Needs UnitTests?!)
* {import file= ....} tag
* {-tag} whitespace control
* {block 'foo' overwrite} to overwrite {$smarty.block.child}


## Plugins ##

* smarty_modifier_foobar(Smarty $smarty, $string, …) vs. smarty_modifier_foobar($string, …)
* smarty_function_foobar(Smarty $smarty, $param1, $param2) vs, smarty_modifier_foobar($params, Smarty_Internal_Template $template)
* smarty_block_foobar(Smarty $smarty, $content, $$repeat, $param1, $param2) vs, smarty_modifier_foobar($params, $content, Smarty_Internal_Template $template, &$repeat)
* {exception} tag

## Functions ##


## Modifiers ##
