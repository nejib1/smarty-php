<?php

/**
 * Smarty Internal Plugin Resource Extends
 *
 *
 * @package TemplateResources
 * @author Uwe Tews
 * @author Rodney Rehm
 */

/**
 * Smarty Internal Plugin Resource Extends
 *
 * Implements the file system as resource for Smarty which {extend}s a chain of template files templates
 *
 *
 * @package TemplateResources
 */
class Smarty_Internal_Resource_Extends extends Smarty_Resource
{

    /**
     * mbstring.overload flag
     *
     * @var int
     */
    public $mbstring_overload = 0;

    /**
     * populate Source Object with meta data from Resource
     *
     * @param Smarty_Template_Source $source    source object
     * @param Smarty $tpl_obj template object
     * @throws SmartyException
     */
    public function populate(Smarty_Template_Source $source, Smarty $tpl_obj = null)
    {
        $uid = '';
        $sources = array();
        $components = explode('|', $source->name);
        $exists = true;
        foreach ($components as $component) {
            $s = Smarty_Resource::source(null, $tpl_obj, $component);
            if ($s->type == 'php') {
                throw new SmartyException("Resource type {$s->type} cannot be used with the extends resource type");
            }
            $sources[$s->uid] = $s;
            $uid .= $s->filepath;
            if ($tpl_obj && $tpl_obj->compile_check) {
                $exists = $exists && $s->exists;
            }
        }
        $source->components = $sources;
        $source->filepath = $s->filepath;
        $source->uid = sha1($uid);
        $source->filepath = 'extends_resource_' . $source->uid . '.tpl';
        if ($tpl_obj && $tpl_obj->compile_check) {
            $source->timestamp = 1;
            $source->exists = $exists;
        }
        // need the template at getContent()
        $source->template = $tpl_obj;
    }

    /**
     * populate Source Object with timestamp and exists from Resource
     *
     * @param Smarty_Template_Source $source source object
     */
    public function populateTimestamp(Smarty_Template_Source $source)
    {
        $source->exists = true;
        $source->timestamp = 1;
    }

    /**
     * Load template's source from files into current template object
     *
     * @param Smarty_Template_Source $source source object
     * @return string template source
     * @throws SmartyException if source cannot be loaded
     */
    public function getContent(Smarty_Template_Source $source)
    {
        $source_code = '';
        $_components = array_reverse($source->components);
        $_last = end($_components);

        foreach ($_components as $_component) {
            if ($_component != $_last) {
                $source_code .= "{$source->tpl_obj->left_delimiter}private_inheritancetpl_obj file='$_component->filepath' child--{$source->tpl_obj->right_delimiter}\n";
            } else {
                $source_code .= "{$source->tpl_obj->left_delimiter}private_inheritancetpl_obj file='$_component->filepath'--{$source->tpl_obj->right_delimiter}\n";
            }
        }
        return $source_code;
    }

    /**
     * Determine basename for compiled filename
     *
     * @param Smarty_Template_Source $source source object
     * @return string resource's basename
     */
    public function getBasename(Smarty_Template_Source $source)
    {
        return str_replace(':', '.', basename($source->filepath));
    }

}
