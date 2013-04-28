<?php

/**
 * Smarty Internal Plugin
 *
 * @package Smarty
 * @subpackage Cacher
 */

/**
 * Cache Support Routines To Creacte Cache
 *
 * @package Smarty
 * @subpackage Cacher
 * @author Uwe Tews
 */
class Smarty_Internal_CacheCreate
{

    /**
     * Code Object
     * @var Smarty_Internal_Code
     */
    public $code_obj = null;

    /**
     * required plugins
     * @var array
     * @internal
     */
    public $required_plugins = array();

    /**
     * template function properties
     *
     * @var array
     */
    public $template_functions = array();

    /**
     * template function properties
     *
     * @var array
     */
    public $template_functions_code = array();

    /**
     * block function properties
     *
     * @var array
     */
    public $inheritance_blocks = array();

    /**
     * block function compiled code
     *
     * @var array
     */
    public $inheritance_blocks_code = array();

    /**
     * file dependencies
     *
     * @var array
     */
    public $file_dependency = array();

    /**
     * Find template object of cache file and return Smarty_Template_Cached
     *
     * @param Smarty $_template     current template
     * @return Smarty_Template_Cached
     */
    static function findCachedObject($_template)
    {
        $_tpl = $_template;
        while ($_tpl->usage == Smarty::IS_TEMPLATE) {
            if (isset($_tpl->cached)) {
                break;
            }
            $_tpl = $_tpl->parent;
        }
        return $_tpl->cached;
    }

    /**
     * Create new cache file
     *
     * @param Smarty $_template     current template
     * @param string $output        cache file content
     * @param boolean $no_output_filter  flag that output shall not run throug filter
     * @throws Exception
     * @return
     */
    public function createCacheFile($_template, $output, $no_output_filter)
    {
        if ($_template->debugging) {
            Smarty_Internal_Debug::start_cache($_template);
        }
        $this->code_obj = new Smarty_Internal_Code(3);
        $_template->has_nocache_code = false;
        // get text between non-cached items
        $cache_split = preg_split("!/\*%%SmartyNocache%%\*/(.+?)/\*/%%SmartyNocache%%\*/!s", $output);
        // get non-cached items
        preg_match_all("!/\*%%SmartyNocache%%\*/(.+?)/\*/%%SmartyNocache%%\*/!s", $output, $cache_parts);
        unset($output);
        // loop over items, stitch back together
        foreach ($cache_split as $curr_idx => $curr_split) {
            if (!empty($curr_split)) {
                $this->code_obj->php("echo ")->string($curr_split)->raw(";\n");
            }
            if (isset($cache_parts[0][$curr_idx])) {
                $_template->has_nocache_code = true;
                // format and add nocache PHP code
                $this->code_obj->formatPHP($cache_parts[1][$curr_idx]);
            }
        }
        if (!$no_output_filter && !$_template->has_nocache_code && (isset($_template->autoload_filters['output']) || isset($_template->registered_filters['output']))) {
            $this->code_obj->buffer = Smarty_Internal_Filter_Handler::runFilter('output', $this->code_obj->buffer, $_template);
        }
        // write cache file content
        if (!$_template->source->recompiled && ($_template->caching == Smarty::CACHING_LIFETIME_CURRENT || $_template->caching == Smarty::CACHING_LIFETIME_SAVED)) {
            $this->code_obj->buffer = $this->createSmartyContentClass($_template);
            try {
                $level = ob_get_level();
                $_template->compile_check = false; // no need to check again
                eval('?>' . $this->code_obj->buffer);
                $_template->cached->write($_template, $this->code_obj->buffer);
                $this->code_obj = null;
                $_template->cached->valid = true;
                $output = $_template->cached->smarty_content->get_template_content($_template);
            } catch (Exception $e) {
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }
                throw $e;
            }
        }
        if ($_template->debugging) {
            Smarty_Internal_Debug::end_cache($_template);
        }
        return $output;
    }


    /**
     * Create Smarty content class for cache files
     *
     * @param Smarty $_template   template object
     * @return string
     */
    public function createSmartyContentClass(Smarty $_template)
    {
        $content = $this->code_obj->buffer;
        $this->code_obj->buffer = '';
        $this->code_obj->indentation = 0;
        // content class name
        $class = '__Smarty_Content_' . str_replace('.', '_', uniqid('', true));
        $this->code_obj->raw("<?php")->newline();
        $this->code_obj->php("if (!class_exists('{$class}',false)) {")->newline()->indent()->php("class {$class} extends Smarty_Internal_Content" . (!empty($this->inheritance_blocks_code) ? "_Inheritance" : '') . " {")->newline()->indent();
        $this->code_obj->php("public \$version = '" . Smarty::SMARTY_VERSION . "';")->newline();
        $this->code_obj->php("public \$has_nocache_code = " . ($_template->has_nocache_code ? 'true' : 'false') . ";")->newline();
        if (!empty($_template->cached_subtemplates)) {
            $this->code_obj->php("public \$cached_subtemplates = ")->repr($_template->cached_subtemplates)->raw(";")->newline();
        }
        $this->code_obj->php("public \$is_cache = true;")->newline();
        $this->code_obj->php("public \$cache_lifetime = {$_template->cache_lifetime};")->newline();
        $this->code_obj->php("public \$file_dependency = ")->repr($this->file_dependency)->raw(";")->newline();
        if (!empty($this->required_plugins)) {
            $this->code_obj->php("public \$required_plugins = ")->repr($this->required_plugins)->raw(";")->newline();
        }
        if (!empty($this->template_functions)) {
            $this->code_obj->php("public \$template_functions = ")->repr($this->template_functions)->raw(";")->newline();
        }
        $this->template_functions = array();
        if (!empty($this->inheritance_blocks)) {
            $this->code_obj->php("public \$inheritance_blocks = ")->repr($this->inheritance_blocks)->raw(';')->newline();
        }
        $this->code_obj->newline()->php("function get_template_content (\$_smarty_tpl) {")->newline()->indent();
        $this->code_obj->php("ob_start();")->newline();
        $this->code_obj->raw($content);
        $content = '';
        $this->code_obj->php('return ob_get_clean();')->newline();
        $this->code_obj->outdent()->php('}')->newline();
        foreach ($this->template_functions_code as $code) {
            $this->code_obj->newline()->raw($code);
        }
        $this->template_functions_code = array();
        foreach ($this->inheritance_blocks_code as $code) {
            $this->code_obj->newline()->raw($code);
        }
        $this->code_obj->outdent()->php('}')->newline()->outdent()->php('}')->newline();
        $this->code_obj->php("\$_template->cached->smarty_content = new {$class}(\$_template);\n\n");
        return $this->code_obj->buffer;
    }


    /**
     * Merge plugin info, dependencies and nocache template functions into cache
     *
     * @param Smarty $_template     current template
     */
    public function mergeFromCompiled($_template)
    {
        $this->required_plugins = array_merge($this->required_plugins, $_template->compiled->smarty_content->required_plugins_nocache);
        $this->file_dependency = array_merge($this->file_dependency, $_template->compiled->smarty_content->file_dependency);
        if (!empty($_template->compiled->smarty_content->called_nocache_template_functions)) {
            foreach ($_template->compiled->smarty_content->called_nocache_template_functions as $name => $dummy) {
                self::mergeNocacheTemplateFunction($_template, $name);
            }
        }

    }

    /**
     * Merge plugin info, dependencies and nocache template functions into cache
     *
     * @param Smarty $template     current template
     * @param string $name         name of template function
     */
    public function mergeNocacheTemplateFunction($template, $name)
    {
        if (isset($this->template_functions[$name])) {
            return;
        }
        $ptr = $tpl = $template;
        while ($ptr != null && !isset($ptr->compiled->smarty_content->template_functions[$name])) {
            $ptr = $ptr->template_function_chain;
            if ($ptr == null && ($tpl->parent->usage == Smarty::IS_TEMPLATE || $tpl->parent->usage == Smarty::IS_CONFIG)) {
                $ptr = $tpl = $tpl->parent;
            }
        }
        if (isset($ptr->compiled->smarty_content->template_functions[$name])) {
            if (isset($ptr->compiled->smarty_content->template_functions[$name]['used_plugins'])) {
                foreach ($ptr->compiled->smarty_content->template_functions[$name]['used_plugins'] as $key => $function) {
                    $this->required_plugins[$key] = $function;
                }
            }
            $this->code_obj = new Smarty_Internal_Code(3);
            $this->template_functions[$name] = $ptr->compiled->smarty_content->template_functions[$name];
            $obj = new ReflectionObject($ptr->compiled->smarty_content);
            $refFunc = $obj->getMethod("smarty_template_function_{$name}");
            $file = $refFunc->getFileName();
            $start = $refFunc->getStartLine() - 1;
            $end = $refFunc->getEndLine();
            $source = file($file);
            for ($i = $start; $i < $end; $i++) {
                if (preg_match("!/\*%%SmartyNocache%%\*/!", $source[$i])) {
                    $this->code_obj->formatPHP(stripcslashes(preg_replace("!echo '/\*%%SmartyNocache%%\*/|/\*/%%SmartyNocache%%\*/';!", '', $source[$i])));
                } else {
                    $this->code_obj->buffer .= $source[$i];
                }
            }
            $this->template_functions_code[$name] = $this->code_obj->buffer;
            $this->code_obj = null;
            if (isset($ptr->compiled->smarty_content->template_functions[$name]['called_functions'])) {
                foreach ($ptr->compiled->smarty_content->template_functions[$name]['called_functions'] as $name => $dummy) {
                    $this->mergeNocacheTemplateFunction($template, $name);
                }
            }
        }
    }

    /**
     * Creates an inheritance block in cache file
     *
     * @param object $current_tpl   calling template
     * @param string $name          name of block
     * @param object $scope_tpl     blocks must be processed in this variable scope
     * @return string
     */
    public function _createNocacheBlockChild($current_tpl, $name, $scope_tpl)
    {
        $output = '';
        $status = 0;
        $child_tpl = null;
        $parent_tpl = null;
        $template_stack = array();
        while ($current_tpl !== null && $current_tpl->usage == Smarty::IS_TEMPLATE) {
            if (isset($current_tpl->compiled->smarty_content->inheritance_blocks[$name]['valid'])) {
                if (isset($current_tpl->compiled->smarty_content->inheritance_blocks[$name]['hide'])) {
                    break;
                }
                $child_tpl = $current_tpl;
                if (isset($current_tpl->compiled->smarty_content->inheritance_blocks[$name]['child'])) {
                    $parent_tpl = $current_tpl;
                }
                if (isset($current_tpl->compiled->smarty_content->inheritance_blocks[$name]['overwrite'])) {
                    $parent_tpl = null;
                }
                // back link pointers to inheritance parent template
                $template_stack[] = $current_tpl;
            }
            if ($status == 0 && ($current_tpl->is_inheritance_child || $current_tpl->compiled->smarty_content->is_inheritance_child)) {
                $status = 1;
            }
            $current_tpl = $current_tpl->parent;
            if ($current_tpl === null || $current_tpl->usage != Smarty::IS_TEMPLATE || ($status == 1 && !$current_tpl->is_inheritance_child && !$current_tpl->compiled->smarty_content->is_inheritance_child)) {
                // quit at first child of current inheritance chain
                break;
            }
        }

        if ($parent_tpl != null) {
            $child_tpl = $parent_tpl;
        }
        if ($child_tpl !== null) {
            $smarty_content = $child_tpl->compiled->smarty_content;

            if (isset($smarty_content->inheritance_blocks[$name]['subblock'])) {
                foreach ($smarty_content->inheritance_blocks[$name]['subblock'] as $subblock) {
                    $function = $smarty_content->inheritance_blocks[$subblock]['function'];
                    $this->inheritance_blocks_code[$function] = $this->_getBlockMethodSource($smarty_content, $function);
                    $this->inheritance_blocks[$subblock]['function'] = $function;
                }
            }

            $function = $smarty_content->inheritance_blocks[$name]['function'];
            $this->inheritance_blocks_code[$function] = $this->_getBlockMethodSource($smarty_content, $function);
            $this->inheritance_blocks[$name]['function'] = $function;
            $output = "/*%%SmartyNocache%%*/echo \$this->_getBlock(\$_smarty_tpl, '{$name}', \$_smarty_tpl, 2);/*/%%SmartyNocache%%*/";
            if (isset($child_tpl->compiled->smarty_content->inheritance_blocks[$name]['prepend'])) {
                $output .= $child_tpl->compiled->smarty_content->_fetch_block_parent_template($name, $template_stack, $scope_tpl);
            } elseif (isset($child_tpl->compiled->smarty_content->inheritance_blocks[$name]['append'])) {
                $output = $child_tpl->compiled->smarty_content->_fetch_block_parent_template($name, $template_stack, $scope_tpl) . $output;
            }
        }
        return $output;
    }

    /**
     * Get block method source
     *
     * @param object $smarty_content   Smarty content obbject
     * @param string $function          method name of block
     * @return string                  source code
     */
    public function _getBlockMethodSource($smarty_content, $function)
    {
        $code_obj = new Smarty_Internal_Code(3);
        $obj = new ReflectionObject($smarty_content);
        $refFunc = $obj->getMethod($function);
        $file = $refFunc->getFileName();
        $start = $refFunc->getStartLine() - 1;
        $end = $refFunc->getEndLine();
        $source = file($file);
        for ($i = $start; $i < $end; $i++) {
            if (preg_match("!/\*%%SmartyNocache%%\*/!", $source[$i])) {
                $code_obj->formatPHP(stripcslashes(preg_replace("!echo '/\*%%SmartyNocache%%\*/|/\*/%%SmartyNocache%%\*/';!", '', $source[$i])));
            } else {
                $code_obj->buffer .= $source[$i];
            }
        }
        return $code_obj->buffer;
    }


}
