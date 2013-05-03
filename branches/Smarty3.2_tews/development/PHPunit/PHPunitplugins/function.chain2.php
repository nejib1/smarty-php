<?php
function smarty_function_chain2($params, $tpl)
{
    $tpl->_loadPlugin('smarty_function_chain3');
    return smarty_function_chain3($params, $tpl);
}

?>
