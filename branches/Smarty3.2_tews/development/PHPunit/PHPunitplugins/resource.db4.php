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
class Smarty_Resource_Db4 extends Smarty_Resource
{
    public function populate(Smarty $tpl_obj = null)
    {
        $this->filepath = 'db4:';
        $this->uid = sha1($this->name);
        $this->timestamp = 0;
        $this->exists = true;
    }

    public function getContent()
    {
        /** TODO Cofig return
        if ($this->smarty->usage == Smarty::IS_CONFIG) {
            return "foo = 'bar'\n";
        }
         */
        return '{$x="hello world"}{$x}';
    }
}

?>
