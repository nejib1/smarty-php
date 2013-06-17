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
     * @param Smarty $tpl_obj template object
     * @throws SmartyException
     */
    public function populate(Smarty $tpl_obj = null)
    {
        $uid = '';
        $sources = array();
        $components = explode('|', $this->name);
        $exists = true;
        foreach ($components as $component) {
            $s = $tpl_obj->_loadSource($component);
            if ($s->type == 'php') {
                throw new SmartyException("Resource type {$s->type} cannot be used with the extends resource type");
            }
            $sources[$s->uid] = $s;
            $uid .= $s->filepath;
            if ($tpl_obj && $tpl_obj->compile_check) {
                $exists = $exists && $s->exists;
            }
        }
        $this->components = $sources;
        $this->filepath = $s->filepath;
        $this->uid = sha1($uid);
        $this->filepath = 'extends_resource_' . $this->uid . '.tpl';
        if ($tpl_obj && $tpl_obj->compile_check) {
            $this->timestamp = 1;
            $this->exists = $exists;
        }
        // need the template at getContent()
        $this->template = $tpl_obj;
    }

    /**
     * populate Source Object with timestamp and exists from Resource
     *
     */
    public function populateTimestamp()
    {
        $this->exists = true;
        $this->timestamp = 1;
    }

    /**
     * Load template's source from files into current template object
     *
     * @return string template source
     * @throws SmartyException if source cannot be loaded
     */
    public function getContent()
    {
        $source_code = '';
        $_components = array_reverse($this->components);
        $_last = end($_components);

        foreach ($_components as $_component) {
            if ($_component != $_last) {
                $source_code .= "{$this->tpl_obj->left_delimiter}private_inheritancetpl_obj file='$_component->filepath' child--{$this->tpl_obj->right_delimiter}\n";
            } else {
                $source_code .= "{$this->tpl_obj->left_delimiter}private_inheritancetpl_obj file='$_component->filepath'--{$this->tpl_obj->right_delimiter}\n";
            }
        }
        return $source_code;
    }

    /**
     * Determine basename for compiled filename
     *
     * @return string resource's basename
     */
    public function getBasename()
    {
        return str_replace(':', '.', basename($this->filepath));
    }

    /**
     * populate Source Object with meta data from Resource
     *
     * @param Smarty $tpl_obj     template object
     */

    public function buildFilepath(Smarty $tpl_obj = null)
    {

    }

}
