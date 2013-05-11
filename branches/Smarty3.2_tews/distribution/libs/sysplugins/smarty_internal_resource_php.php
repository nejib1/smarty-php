<?php

/**
 * Smarty Internal Plugin Resource PHP
 *
 * Implements the file system as resource for PHP templates
 *
 *
 * @package TemplateResources
 * @author Uwe Tews
 * @author Rodney Rehm
 */
class Smarty_Internal_Resource_PHP extends Smarty_Resource_Uncompiled
{

    /**
     * container for short_open_tag directive's value before executing PHP templates
     * @var string
     */
    protected $short_open_tag;

    /**
     * Create a new PHP Resource
     *
     */
    public function __construct()
    {
        $this->short_open_tag = ini_get('short_open_tag');
    }

    /**
     * populate Source Object with meta data from Resource
     *
     * @param Smarty_Template_Source $source source object
     * @param Smarty $tpl_obj template object
     * @return void
     */
    public function populate(Smarty_Template_Source $source, Smarty $tpl_obj = null)
    {
        $source->filepath = $this->buildFilepath($source, $tpl_obj);

        if ($source->filepath !== false) {
            if (is_object($source->smarty->security_policy)) {
                $source->smarty->security_policy->isTrustedResourceDir($source->filepath);
            }

            $source->uid = sha1($source->filepath);
            if ($source->smarty->compile_check) {
                $source->timestamp = @filemtime($source->filepath);
                $source->exists = !!$source->timestamp;
            }
        }
    }

    /**
     * populate Source Object with timestamp and exists from Resource
     *
     * @param Smarty_Template_Source $source source object
     * @return void
     */
    public function populateTimestamp(Smarty_Template_Source $source)
    {
        $source->timestamp = @filemtime($source->filepath);
        $source->exists = !!$source->timestamp;
    }

    /**
     * Load template's source from file into current template object
     *
     * @param Smarty_Template_Source $source source object
     * @return string template source
     * @throws SmartyException if source cannot be loaded
     */
    public function getContent(Smarty_Template_Source $source)
    {
        if ($source->timestamp) {
            return '';
        }
        throw new SmartyException("Unable to read template {$source->type} '{$source->name}'");
    }

    /**
     * Render and output the template (without using the compiler)
     *
     * @param Smarty_Template_Source $source source object
     * @param Smarty $tpl_obj template object
     * @return void
     * @throws SmartyException if template cannot be loaded or allow_phptpl_objs is disabled
     */
    public function renderUncompiled(Smarty_Template_Source $source, Smarty $tpl_obj)
    {
        $_smartytpl_obj = $tpl_obj;

        if (!$source->smarty->allow_phptpl_objs) {
            throw new SmartyException("PHP templates are disabled");
        }
        if (!$source->exists) {
            if ($tpl_obj->parent instanceof Smarty) {
                $parent_resource = " in '{$tpl_obj->parent->template_resource}'";
            } else {
                $parent_resource = '';
            }
            throw new SmartyException("Unable to load template {$source->type} '{$source->name}'{$parent_resource}");
        }

        // prepare variables
        extract($tpl_obj->getTemplateVars());

        // include PHP template with short open tags enabled
        ini_set('short_open_tag', '1');
        include($source->filepath);
        ini_set('short_open_tag', $this->short_open_tag);
    }

}
