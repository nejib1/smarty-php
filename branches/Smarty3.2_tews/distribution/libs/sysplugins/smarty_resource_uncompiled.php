<?php

/**
 * Smarty Resource Plugin
 *
 * @package Smarty
 * @subpackage TemplateResources
 * @author Rodney Rehm
 */

/**
 * Smarty Resource Plugin
 *
 * Base implementation for resource plugins that don't use the compiler
 *
 * @package Smarty
 * @subpackage TemplateResources
 */
abstract class Smarty_Resource_Uncompiled extends Smarty_Resource
{

    /**
     * Render and output the template (without using the compiler)
     *
     * @param Smarty_Template_Source $source    source object
     * @param Smarty $_template template object
     * @throws SmartyException on failure
     */
    public abstract function renderUncompiled(Smarty_Template_Source $source, Smarty $_template);

    /**
     * get rendered template output from compiled template
     *
     * @param Smarty_Template_Source $source    source object
     * @param Smarty $_template template object
     * @throws Exception
     * @return string
     */
    public function getRenderedTemplate(Smarty_Template_Source $source, $_template)
    {
        if ($_template->debugging) {
            Smarty_Internal_Debug::start_render($_template);
        }
        try {
            $level = ob_get_level();
            ob_start();
            $this->renderUncompiled($source, $_template);
            $output = ob_get_clean();
        } catch (Exception $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
        if ($_template->caching) {
            $cached = Smarty_Internal_CacheCreate::findCachedObject($_template);
            $cached->newcache->file_dependency[$source->uid] = array($source->filepath, $source->timestamp, $source->type);
        }
        return $output;
    }

    /**
     * populate Compiled Object with compiled filepath
     *
     * @param Smarty_Compiled $compiled compiled object
     * @param Smarty|Smarty_Internal_Cached $_object template or cache object object
     * @return void
     */
    public function populateCompiledFilepath(Smarty_Compiled $compiled, $_object)
    {
        $compiled->filepath = false;
        $compiled->timestamp = false;
        $compiled->exists = false;
    }

}
