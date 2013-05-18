<?php

/**
 * Smarty Resource Plugin
 *
 *
 * @package TemplateResources
 * @author Uwe Tews
 * @author Rodney Rehm
 */

/**
 * Smarty Resource Plugin
 *
 * Base implementation for resource plugins
 *
 *
 * @package Resources
 */
abstract class Smarty_Resource extends Smarty_Internal_Magic_Error
{

    /**
     * usage of this resoure
     * @var mixed
     */
    public $usage = null;


    /**
     * Template name
     *
     * @var string
     */
    public $name = '';

    /**
     * Resource handler type
     *
     * @var string
     */
    public $type = '';

    /**
     * resource filepath
     *
     * @var string| boolean false
     */
    public $filepath = false;


    /**
     * resource UID
     *
     * @var boolean
     */
    public $uid = false;

    /**
     * array of extends components
     *
     * @var array
     */
    public $components = array();

    public $uncompiled = false;
    public $recompiled = false;


    /**
     * get rendered template output from compiled template
     *
     * @param Smarty $tpl_obj template object
     * @param boolean $no_output_filter true if output filter shall nit run
     * @return string
     */
    public function getRenderedTemplate($tpl_obj, $no_output_filter = true)
    {
        // TOdo  FIX ->HANDLER
        $output = $this->handler->getRenderedTemplate($this, $tpl_obj);
        if (!$no_output_filter && (isset($tpl_obj->autoload_filters['output']) || isset($tpl_obj->registered_filters['output']))) {
            $output = Smarty_Internal_Filter_Handler::runFilter('output', $output, $tpl_obj);
        }
        return $output;
    }


    /**
     * Load template's source into current template object
     *
     * {@internal The loaded source is assigned to $tpl_obj->source->content directly.}}
     *
     * @return string template source
     * @throws SmartyException if source cannot be loaded
     */
    public abstract function getContent();

    /**
     * populate Source Object with meta data from Resource
     *
     * @param Smarty $tpl_obj     template object
     */
    public abstract function populate(Smarty $tpl_obj);

    /**
     * populate Source Object with meta data from Resource
     *
     * @param Smarty $tpl_obj     template object
     */

    //   public abstract  function buildFilepath(Smarty $tpl_obj = null);

    /**
     * populate Source Object with timestamp and exists from Resource
     *
     */
    public function populateTimestamp()
    {
        // intentionally left blank
    }

    /**
     * modify resource_name according to resource handlers specifications
     *
     * @param Smarty $smarty        Smarty instance
     * @param string $resource_name resource_name to make unique
     * @return string unique resource name
     */
    protected function buildUniqueResourceName(Smarty $smarty, $resource_name)
    {
        return get_class($this) . '#' . ($smarty->usage == Smarty::IS_CONFIG ? $smarty->joined_config_dir : $smarty->joined_template_dir) . '#' . $resource_name;
    }


    /**
     * test is file exists and save timestamp
     *
    * @param string $file file name
     * @return bool  true if file exists
     */
    protected function fileExists($file)
    {
        $this->timestamp = @filemtime($file);
        return $this->exists = !!$this->timestamp;
    }

    /**
     * Determine basename for compiled filename
     *
     * @return string resource's basename
     */
    protected function getBasename()
    {
        return null;
    }


    /**
     * <<magic>> Generic Setter.
     *
     * @param string $property_name valid: timestamp, exists, content, template
     * @param mixed $value        new value (is not checked)
     * @throws SmartyException if $property_name is not valid
     */
    public function __set($property_name, $value)
    {
        switch ($property_name) {
            // regular attributes
            case 'timestamp':
            case 'exists':
            case 'content':
                // required for extends: only
            case 'template':
                $this->$property_name = $value;
                break;

            default:
                parent::__set($property_name, $value);
        }
    }

    /**
     * <<magic>> Generic getter.
     *
     * @param string $property_name valid: timestamp, exists, content
     * @return mixed
     * @throws SmartyException if $property_name is not valid
     */
    public function __get($property_name)
    {
        switch ($property_name) {
            case 'timestamp':
            case 'exists':
                $this->populateTimestamp($this);
                return $this->$property_name;

            case 'content':
                return $this->content = $this->getContent($this);

            default:
                return parent::__get($property_name);
        }
    }

}


