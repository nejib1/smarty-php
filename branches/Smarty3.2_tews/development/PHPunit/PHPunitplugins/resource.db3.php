<?php
/*
 * Smarty plugin
 * -------------------------------------------------------------
 * File:     resource.db3.php
 * Type:     resource
 * Name:     db
 * Purpose:  Fetches templates from a database
 * -------------------------------------------------------------
 */
class Smarty_Resource_Db3 extends Smarty_Resource
{
    public function populate(Smarty $tpl_obj = null)
    {
        $this->filepath = 'db3:';
        $this->uid = sha1($this->name);
        $this->timestamp = 0;
        $this->exists = true;
    }

    public function getContent()
    {
        return '{$x="hello world"}{$x}';
    }

    public function getCompiledFilepath(Smarty $_template)
    {
        return false;
    }
}

?>
