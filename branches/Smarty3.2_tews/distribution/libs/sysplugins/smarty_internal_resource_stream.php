<?php

/**
 * Smarty Internal Plugin Resource Stream
 *
 * Implements the streams as resource for Smarty template
 *
 *
 * @package TemplateResources
 * @author Uwe Tews
 * @author Rodney Rehm
 */

/**
 * Smarty Internal Plugin Resource Stream
 *
 * Implements the streams as resource for Smarty template
 *
 * @link http://php.net/streams
 *
 * @package TemplateResources
 */
class Smarty_Internal_Resource_Stream extends Smarty_Resource_Recompiled
{

    /**
     * populate Source Object with meta data from Resource
     *
     * @param Smarty $tpl_obj template object
     * @return void
     */
    public function populate(Smarty $tpl_obj = null)
    {
        if (strpos($this->resource, '://') !== false) {
            $this->filepath = $this->resource;
        } else {
            $this->filepath = str_replace(':', '://', $this->resource);
        }
        $this->uid = false;
        $this->content = $this->getContent();
        $this->timestamp = false;
        $this->exists = !!$this->content;
    }

    /**
     * Load template's source from stream into current template object
     *
     * @return string template source
     * @throws SmartyException if source cannot be loaded
     */
    public function getContent()
    {
        $t = '';
        // the availability of the stream has already been checked in Smarty_Resource::fetch()
        $fp = fopen($this->filepath, 'r+');
        if ($fp) {
            while (!feof($fp) && ($current_line = fgets($fp)) !== false) {
                $t .= $current_line;
            }
            fclose($fp);
            return $t;
        } else {
            return false;
        }
    }

    /**
     * return unique name for this resource
     *
     * @param Smarty $smarty        Smarty instance
     * @param string $template_resource  resource_name to make unique
     * @return string unique resource name
     */
    public function buildUniqueResourceName(Smarty $smarty, $template_resource)
    {
        return get_class($this) . '#' . $template_resource;
    }

}
