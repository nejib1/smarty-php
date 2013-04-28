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
    public function populate(Smarty_Template_Source $source, Smarty $_template = null)
    {
        $source->filepath = 'db4:';
        $source->uid = sha1($source->resource);
        $source->timestamp = 0;
        $source->exists = true;
    }

    public function getContent(Smarty_Template_Source $source)
    {
        if ($source->smarty->usage == Smarty::IS_CONFIG) {
            return "foo = 'bar'\n";
        }
        return '{$x="hello world"}{$x}';
    }
}

?>
