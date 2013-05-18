<?php

/**
 * Smarty Resource Plugin
 *
 *
 * @package TemplateResources
 * @author Rodney Rehm
 */

/**
 * Smarty Resource Plugin
 *
 * Base implementation for resource plugins that don't use the compiler
 *
 *
 * @package TemplateResources
 */
abstract class Smarty_Resource_Uncompiled extends Smarty_Resource
{

    /**
     * Render and output the template (without using the compiler)
     *
     * @param Smarty $tpl_obj template object
     * @return
     * @internal param \Smarty_Resource $source source object
     */
    public abstract function renderUncompiled(Smarty $tpl_obj);

    /**
     * get rendered template output from compiled template
     *
     * @param Smarty_Resource $source    source object
     * @param Smarty $tpl_obj template object
     * @throws Exception
     * @return string
     */
    public function getRenderedTemplate(Smarty_Resource $source, $tpl_obj)
    {
        if ($tpl_obj->debugging) {
            Smarty_Internal_Debug::start_render($tpl_obj);
        }
        try {
            $level = ob_get_level();
            ob_start();
            $this->renderUncompiled($source, $tpl_obj);
            $output = ob_get_clean();
        } catch (Exception $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
        if ($tpl_obj->caching) {
            $cached = Smarty_Internal_CacheCreate::_getCachedObject($tpl_obj);
            $cached->newcache->file_dependency[$source->uid] = array($source->filepath, $source->timestamp, $source->type);
        }
        return $output;
    }
}
