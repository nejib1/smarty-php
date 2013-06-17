<?php
/*
 * Smarty plugin
 * -------------------------------------------------------------
 * File:     resource.db2.php
 * Type:     resource
 * Name:     db
 * Purpose:  Fetches templates from a database
 * -------------------------------------------------------------
 */
class Smarty_Resource_Db2 extends Smarty_Resource_Recompiled
{
    public function populate(Smarty $tpl_obj = null)
    {
        $this->filepath = 'db2:';
        $this->uid = sha1($this->name);
        $this->timestamp = 0;
        $this->exists = true;
    }

    public function getContent()
    {
        return '{$x="hello world"}{$x}';
    }
}

?>
