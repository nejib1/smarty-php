<?php

    /**
    * Smarty Internal Plugin Smarty Internal Inheritance
    *
    * This file contains the methods for precessing inheritance
    *
    * @package Smarty
    * @subpackage Template
    * @author Uwe Tews
    */

    /**
    * Class with inheritance processing methods
    *
    * @package Smarty
    * @subpackage Template
    */
    class Smarty_Internal_Content_Inheritance extends Smarty_Internal_Content
    {


        /**
        * Template runtime function to fetch inheritance template
        *
        * @param string $resource       the resource handle of the template file
        * @param mixed $cache_id       cache id to be used with this template
        * @param mixed $compile_id     compile id to be used with this template
        * @param integer $caching        cache mode
        * @param object $parent         parent template object
        * @param bool $is_child       is inheritance child template
        * @return object
        */
        public function _getInheritanceTemplate($resource, $cache_id, $compile_id, $caching, $parent, $is_child = false)
        {
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
            $tpl->is_inheritance_child = $is_child;
            $tpl->parent = $parent;
            if (empty($tpl->compiled->smarty_content)) {
                $tpl->compiled->loadContent($tpl);
            }
            if ($parent != null) {
                $tpl->tpl_vars = clone $parent->tpl_vars;
            }
            $tpl->caching = $caching;
            return $tpl;
        }

        /**
        * resolve inheritance for block an return content
        *
        * @param object $current_tpl   calling template
        * @param string $name          name of block
        * @param object $scope_tpl     blocks must be processed in this variable scope
        * @param int    $mode          mode of this call
        * @return string
        */
        public function _getBlock($current_tpl, $name, $scope_tpl, $mode = 0) {
             switch ($mode) {
                case 0:
                    return $this->_fetchBlockChildTemplate($current_tpl, $name, $scope_tpl);     
                case 1:
                    $tmp = Smarty_Internal_CacheCreate::findCachedObject($current_tpl);
                    return $tmp->newcache->_createNocacheBlockChild ($current_tpl, $name, $scope_tpl);
                case 2:
               if (isset($this->inheritance_blocks[$name])) {
                    $function = $this->inheritance_blocks[$name]['function'];
                    return $this->$function($current_tpl, array(), $current_tpl);  
                } 
            }
        }

        /**
        * Fetch output of extended child {block} or {$smarty.block.child}
        *
        * @param object $current_tpl   calling template
        * @param string $name          name of block
        * @param object $scope_tpl     blocks must be processed in this variable scope
        * @return string
        */
        public function _fetchBlockChildTemplate($current_tpl, $name, $scope_tpl)
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
                $function = $child_tpl->compiled->smarty_content->inheritance_blocks[$name]['function'];
                $output = $child_tpl->compiled->smarty_content->$function($scope_tpl, $template_stack, $child_tpl);
                if (isset($child_tpl->compiled->smarty_content->inheritance_blocks[$name]['prepend'])) {
                    $output .= $child_tpl->compiled->smarty_content->_fetch_block_parent_template($name, $template_stack, $scope_tpl);
                } elseif (isset($child_tpl->compiled->smarty_content->inheritance_blocks[$name]['append'])) {
                    $output = $child_tpl->compiled->smarty_content->_fetch_block_parent_template($name, $template_stack, $scope_tpl) . $output;
                }
            }
            return $output;
        }

        /**
        * Find inheritance child block template
        *
        * @param object $current_tpl   calling template
        * @param string $name          name of block
        * @return string
        */
        public function _find_block_child_template($current_tpl, $name)
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
                $function = $child_tpl->compiled->smarty_content->inheritance_blocks[$name]['function'];
                $output = $child_tpl->compiled->smarty_content->$function($scope_tpl, $template_stack, $child_tpl);
                if (isset($child_tpl->compiled->smarty_content->inheritance_blocks[$name]['prepend'])) {
                    $output .= $child_tpl->compiled->smarty_content->_fetch_block_parent_template($name, $template_stack, $scope_tpl);
                } elseif (isset($child_tpl->compiled->smarty_content->inheritance_blocks[$name]['append'])) {
                    $output = $child_tpl->compiled->smarty_content->_fetch_block_parent_template($name, $template_stack, $scope_tpl) . $output;
                }
            }
            return $output;
        }

        /**
        * Fetch output of {$smarty.block.parent}
        *
        * @param string $name        name of block
        * @param array $template_stack   backtrack array of inheritance template
        * @param object $scope_tpl     blocks must be processed in this variable scope
        * @return string
        */
        public
        function _fetch_block_parent_template($name, $template_stack, $scope_tpl)
        {
            array_pop($template_stack);
            while (count($template_stack)) {
                $tpl = array_pop($template_stack);
                if (isset($tpl->compiled->smarty_content->inheritance_blocks[$name]['valid'])) {
                    $function = $tpl->compiled->smarty_content->inheritance_blocks[$name]['function'];
                    return $tpl->compiled->smarty_content->$function($scope_tpl, $template_stack, $tpl);
                }
            }
            return '';
        }

}