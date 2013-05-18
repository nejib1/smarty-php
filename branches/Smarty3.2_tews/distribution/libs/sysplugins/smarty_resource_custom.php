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
 * Wrapper Implementation for custom resource plugins
 *
 *
 * @package TemplateResources
 */
abstract class Smarty_Resource_Custom extends Smarty_Resource
{

    /**
     * fetch template and its modification time from data source
     *
     * @param string $name    template name
     * @param string &$source template source
     * @param integer &$mtime  template modification timestamp (epoch)
     */
    protected abstract function fetch($name, &$source, &$mtime);

    /**
     * Fetch template's modification timestamp from data source
     *
     * {@internal implementing this method is optional.
     *  Only implement it if modification times can be accessed faster than loading the complete template source.}}
     *
     * @param string $name template name
     * @return integer|boolean timestamp (epoch) the template was modified, or false if not found
     */
    protected function fetchTimestamp($name)
    {
        return null;
    }

    /**
     * populate Source Object with meta data from Resource
     *
    * @param Smarty $tpl_obj template object
     */
    public function populate( Smarty $tpl_obj = null)
    {
        $this->filepath = strtolower($this->type . ':' . $this->name);
        $this->uid = sha1($this->type . ':' . $this->name);

        $mtime = $this->fetchTimestamp($this->name);
        if ($mtime !== null) {
            $this->timestamp = $mtime;
        } else {
            $this->fetch($this->name, $content, $timestamp);
            $this->timestamp = isset($timestamp) ? $timestamp : false;
            if (isset($content))
                $this->content = $content;
        }
        $this->exists = !!$this->timestamp;
    }

    /**
     * Load template's source into current template object
     *
     * @return string template source
     * @throws SmartyException if source cannot be loaded
     */
    public function getContent()
    {
        $this->fetch($this->name, $content, $timestamp);
        if (isset($content)) {
            return $content;
        }

        throw new SmartyException("Unable to read template {$this->type} '{$this->name}'");
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
