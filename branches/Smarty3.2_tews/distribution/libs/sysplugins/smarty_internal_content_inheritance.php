<?php

/**
 * Smarty Internal Plugin Smarty Internal Inheritance
 *
 * This file contains the methods for precessing inheritance
 *
 *
 * @package Template
 * @author Uwe Tews
 */

/**
 * Class with inheritance processing methods
 *
 *
 * @package Template
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
            $tpl->tpl_vars = $parent->tpl_vars;
        }
        $tpl->caching = $caching;
        return $tpl;
    }

    /**
     * resolve inheritance for block an return content
     *
     * @param string $name          name of block
     * @param object $scope_tpl     blocks must be processed in this variable scope
     * @param int    $mode          mode of this call
     * @param object $current_tpl   calling template  (optional)
     * @param boolean $in_child_chain   flag when inside child template chaim
     * @return string | false
     */
    public function _getInheritanceBlock($name, $scope_tpl, $mode = 0, $current_tpl = null, $in_child_chain = false)
    {
        //            if ($this->is_cache) {
        //                $mode = 2;
        //            }

        if ($current_tpl === null) {
            $current_tpl = $scope_tpl;
        }
        switch ($mode) {
            case 0:
                if (!$this->inheritance_blocks[$name]['calls_child']) {
                    if (($result = $this->_getInheritanceChildBlock($name, $scope_tpl, $mode, $current_tpl, $in_child_chain)) != false) {
                        return $result;
                    }
                }
                return $this->_getInheritanceRenderedBlock($name, $scope_tpl, $current_tpl);

            case 1:
                $tmp = Smarty_Internal_CacheCreate::_getCachedObject($current_tpl);
                return $tmp->newcache->_createNocacheInheritanceBlock($current_tpl, $name, $scope_tpl);
            case 2:
                if (isset($this->inheritance_blocks[$name])) {
                    $function = $this->inheritance_blocks[$name]['function'];
                    return $this->$function($current_tpl, array(), $current_tpl);
                }
        }
    }

    /**
     * resolve inheritance for block in child  {$smarty.block.child}
     *
     * @param string $name          name of block
     * @param object $scope_tpl     blocks must be processed in this variable scope
     * @param int    $mode          mode of this call
     * @param object $current_tpl   calling template  (optional)
     * @param boolean $in_child_chain   flag when inside child template chaim
     * @return string | false
     */
    public function _getInheritanceChildBlock($name, $scope_tpl, $mode, $current_tpl = null, $in_child_chain = false, $parent_block = null)
    {
        if ($current_tpl == null) {
            $current_tpl = $scope_tpl;
        }
        if ($parent_block == null) {
            $parent_block = array($this, $current_tpl);
        }
        $ptr = $current_tpl->parent;
        while ($ptr !== null && $ptr->usage == Smarty::IS_TEMPLATE) {
            $content_ptr = $ptr->compiled->smarty_content;
            if (isset($content_ptr->inheritance_blocks)) {
                $in_child_chain = true;
            } elseif ($in_child_chain) {
                // we did reach start of current inhertance chain
                return false;
            }
            if (isset($content_ptr->inheritance_blocks[$name])) {
                if ($content_ptr->inheritance_blocks[$name]['hide'] || !$content_ptr->inheritance_blocks[$name]['valid']) {
                    break;
                }
                $content_ptr->inheritance_blocks[$name]['parent_block'] = $parent_block;
                unset($parent_block[0]->inheritance_blocks[$name]['parent_block']);
                if ($content_ptr->inheritance_blocks[$name]['calls_child']) {
                    return $content_ptr->_getInheritanceBlock($name, $scope_tpl, $mode, $ptr, $in_child_chain);
                }
                if (($result = $content_ptr->_getInheritanceChildBlock($name, $scope_tpl, $mode, $ptr, $in_child_chain)) != false) {
                    return $result;
                } else {
                    if (isset($content_ptr->inheritance_blocks[$name]['parent_block'])) {
                        $parent_content_ptr = $content_ptr->inheritance_blocks[$name]['parent_block'][0];
                        if ($content_ptr->inheritance_blocks[$name]['prepend']) {
                            return $content_ptr->_getInheritanceRenderedBlock($name, $scope_tpl, $ptr) . $parent_content_ptr->_getInheritanceRenderedBlock($name, $scope_tpl, $ptr);
                        } elseif ($content_ptr->inheritance_blocks[$name]['append']) {
                            return $parent_content_ptr->_getInheritanceRenderedBlock($name, $scope_tpl, $ptr) . $content_ptr->_getInheritanceRenderedBlock($name, $scope_tpl, $ptr);
                        }
                        unset($parent_block[0]->inheritance_blocks[$name]['parent_block']);
                    }
                    return $content_ptr->_getInheritanceRenderedBlock($name, $scope_tpl, $ptr);

                }

                /** TODO  what is the fuction of overwrite
                if (isset($current_tpl->compiled->smarty_content->inheritance_blocks[$name]['overwrite'])) {
                $parent_tpl = null;
                }
                 */
            }
            $ptr = $ptr->parent;
        }
        return false;
    }

    /**
     * Fetch output of {$smarty.block.parent}
     *
     * @param string $name        name of block
     * @param object $scope_tpl     blocks must be processed in this variable scope
     * @return string
     */
    public function _getInheritanceParentBlock($name, $scope_tpl)
    {
        if (isset($this->inheritance_blocks[$name]['parent_block'])) {
            $parent_block = $this->inheritance_blocks[$name]['parent_block'];
            return $parent_block[0]->{$parent_block[0]->inheritance_blocks[$name]['function']} ($scope_tpl, $parent_block[1]);
        }
        return '';
    }

    /**
     * Fetch output of single block  by name
     *
     * @param string $name        name of block
     * @param object $scope_tpl     blocks must be processed in this variable scope
     * @return string
     */
    public function _getInheritanceRenderedBlock($name, $scope_tpl, $current_tpl)
    {
        if (isset($this->inheritance_blocks[$name])) {
            return $this->{$this->inheritance_blocks[$name]['function']} ($scope_tpl, $current_tpl);
        } else {
            throw new SmartyRuntimeException ("Inheritance: Method for block '{$name}' not found", $scope_tpl);
        }
    }

}