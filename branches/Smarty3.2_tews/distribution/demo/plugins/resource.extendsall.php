<?php

/**
 * Extends All Resource
 *
 * Resource Implementation modifying the extends-Resource to walk
 * through the template_dirs and inherit all templates of the same name
 *
 * @package Resource-examples
 * @author Rodney Rehm
 */
class Smarty_Resource_Extendsall extends Smarty_Internal_Resource_Extends
{

    /**
     * populate Source Object with meta data from Resource
     *
     * @param Smarty $tpl_obj template object
     * @return void
     */
    public function populate( Smarty $tpl_obj = null)
    {
        $uid = '';
        $sources = array();
        $exists = true;
        foreach ($tpl_obj->getTemplateDir() as $key => $directory) {
            try {
                $s =  $tpl_obj->_loadSource('[' . $key . ']' . $this->name);
                if (!$s->exists) {
                    continue;
                }
                $sources[$s->uid] = $s;
                $uid .= $s->filepath;
            } catch (SmartyException $e) {
            }
        }

        if (!$sources) {
            $source->exists = false;
            $source->template = $tpl_obj;
            return;
        }

        $sources = array_reverse($sources, true);
        reset($sources);
        $s = current($sources);

        $source->components = $sources;
        $source->filepath = $s->filepath;
        $source->uid = sha1($uid);
        $source->exists = $exists;
        if ($tpl_obj && $tpl_obj->compile_check) {
            $source->timestamp = $s->timestamp;
        }
        // need the template at getContent()
        $source->template = $tpl_obj;
    }
}
