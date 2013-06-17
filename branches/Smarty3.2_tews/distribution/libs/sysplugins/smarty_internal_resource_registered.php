<?php

/**
 * Smarty Internal Plugin Resource Registered
 *
 *
 * @package TemplateResources
 * @author Uwe Tews
 * @author Rodney Rehm
 */

/**
 * Smarty Internal Plugin Resource Registered
 *
 * Implements the registered resource for Smarty template
 *
 *
 * @package TemplateResources
 * @deprecated
 */
class Smarty_Internal_Resource_Registered extends Smarty_Resource
{

    /**
     * populate Source Object with meta data from Resource
     *
     * @param Smarty $tpl_obj template object
     * @return void
     */
    public function populate(Smarty $tpl_obj)
    {
        $this->filepath = $this->type . ':' . $this->name;
        $this->uid = sha1($this->filepath);
        if ($tpl_obj->compile_check) {
            $this->timestamp = $this->getTemplateTimestamp();
            $this->exists = !!$this->timestamp;
        }
    }

    /**
     * populate Source Object with timestamp and exists from Resource
     *
     * @return void
     */
    public function populateTimestamp()
    {
        $this->timestamp = $this->getTemplateTimestamp();
        $this->exists = !!$this->timestamp;
    }

    /**
     * Get timestamp (epoch) the template source was modified
     *
     * @todo must rethink registered resources
     * @return integer|boolean timestamp (epoch) the template was modified, false if resources has no timestamp
     */
    public function getTemplateTimestamp()
    {
        // return timestamp
        $time_stamp = false;
        call_user_func_array($this->smarty->registered_resources[Smarty::SOURCE][$this->type][0][1], array($this->name, &$time_stamp, $this->smarty));
        return is_numeric($time_stamp) ? (int)$time_stamp : $time_stamp;
    }

    /**
     * Load template's source by invoking the registered callback into current template object
     *
     * @return string template source
     * @throws SmartyException if source cannot be loaded
     */
    public function getContent()
    {
        // return template string
        $t = call_user_func_array($this->smarty->registered_resources[Smarty::SOURCE][$this->type][0][0], array($this->name, &$this->content, $this->smarty));
        if (is_bool($t) && !$t) {
            throw new SmartyException("Unable to read template {$this->type} '{$this->name}'");
        }
        return $this->content;
    }

    /**
     * Determine basename for compiled filename
     *
     * @return string resource's basename
     */
    public function getBasename()
    {
        return basename($this->name);
    }

}
