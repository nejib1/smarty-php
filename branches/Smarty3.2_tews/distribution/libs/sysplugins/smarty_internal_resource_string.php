<?php

/**
 * Smarty Internal Plugin Resource String
 *
 *
 * @package TemplateResources
 * @author Uwe Tews
 * @author Rodney Rehm
 */

/**
 * Smarty Internal Plugin Resource String
 *
 * Implements the strings as resource for Smarty template
 *
 * {@internal unlike eval-resources the compiled state of string-resources is saved for subsequent access}}
 *
 *
 * @package TemplateResources
 */
class Smarty_Internal_Resource_String extends Smarty_Resource
{

    /**
     * populate Source Object filepath
     *
     * @param Smarty $tpl_obj template object
     * @return void
     */
    public function buildFilepath(Smarty $tpl_obj = null)
    {
        $this->populate($tpl_obj);
    }

    /**
     * populate Source Object with meta data from Resource
     *
     * @param Smarty $tpl_obj template object
     * @return void
     */
    public function populate(Smarty $tpl_obj = null)
    {
        $this->uid = $this->filepath = sha1($this->name);
        $this->timestamp = 0;
        $this->exists = true;
    }


    /**
     * Load template's source from $resource_name into current template object
     *
     * @uses decode() to decode base64 and urlencoded template_resources
     * @return string template source
     */
    public function getContent()
    {
        return $this->decode($this->name);
    }

    /**
     * decode base64 and urlencode
     *
     * @param string $string template_resource to decode
     * @return string decoded template_resource
     */
    protected function decode($string)
    {
        // decode if specified
        if (($pos = strpos($string, ':')) !== false) {
            if (strpos($string, 'base64') === 0) {
                return base64_decode(substr($string, 7));
            } elseif (strpos($string, 'urlencode') === 0) {
                return urldecode(substr($string, 10));
            }
        }

        return $string;
    }

    /**
     * Determine basename for compiled filename
     *
     * Always returns an empty string.
     *
     * @return string resource's basename
     */
    public function getBasename()
    {
        return '';
    }

}
