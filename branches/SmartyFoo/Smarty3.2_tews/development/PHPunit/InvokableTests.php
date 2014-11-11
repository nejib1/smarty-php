<?php
/**
* Smarty PHPunit tests invokable plugins
* 
* @package PHPunit
* @author Rodney Rehm 
*/

class InvokableTests extends PHPUnit_Framework_TestCase {

    public function setUp()
    {
        $this->smarty = SmartyTests::$smarty;
        SmartyTests::init();
    } 

    public static function isRunnable()
    {
        return true;
    } 

    public function testPluginFunction()
    {
        $this->smarty->registerPlugin('function', 'closure_function', new InvokableTests_PluginFunction());
        $this->assertEquals("[closure-function]", $this->smarty->fetch('eval:{closure_function}'));
    }
    
    public function testPluginBlock()
    {
        $this->smarty->registerPlugin('block', 'closure_block', new InvokableTests_PluginBlock());
        $this->assertEquals("[closure-block]", $this->smarty->fetch('eval:{closure_block}{/closure_block}'));
    }
    
    public function testPluginModifier()
    {
        $this->smarty->registerPlugin('modifier', 'closure_modifier', new InvokableTests_PluginModifier());
        $this->assertEquals("[closure-modifier]", $this->smarty->fetch('eval:{""|closure_modifier}'));
    }
    
    public function testPluginModifierCompiler()
    {
        $this->smarty->registerPlugin('modifiercompiler', 'closure_modifiercompiler', new InvokableTests_PluginModifierCompiler());
        $this->assertEquals("[closure-modifiercompiler]", $this->smarty->fetch('eval:{""|closure_modifiercompiler}'));
    }
    
    public function testFilterPre()
    {
        $this->smarty->registerFilter('pre', new InvokableTests_FilterPre());
        $this->assertEquals("foo[closure-filter-pre]", $this->smarty->fetch('eval:foo'));
    }
    
    public function testFilterPost()
    {
        $this->smarty->registerFilter('pre', new InvokableTests_FilterPost());
        $this->assertEquals("foo[closure-filter-pre]", $this->smarty->fetch('eval:foo'));
    }
    
    public function testFilterOutput()
    {
        $this->smarty->registerFilter('post', new InvokableTests_FilterOutput());
        $this->assertEquals("foo[closure-filter-post]", $this->smarty->fetch('eval:foo'));
    }
    
    public function testFilterVariable()
    {
        $this->smarty->registerFilter('variable', new InvokableTests_FilterVariable());
        $this->smarty->assign('foo', 'foo');
        $this->assertEquals("foo[closure-filter-variable]", $this->smarty->fetch('eval:{$foo}'));
    }
}

class InvokableTests_PluginFunction {
    public function __invoke($params, $template) {
        return "[closure-function]";
    }
}
class InvokableTests_PluginBlock {
    public function __invoke($params, $content, $template, &$repeat) {
        $t = $repeat ? "[closure-block]" : '';
        $repeat = false;
        return $t;
    }
}
class InvokableTests_PluginModifier {
    public function __invoke($text) {
        return "[closure-modifier]";
    }
}
class InvokableTests_PluginModifierCompiler {
    public function __invoke($params) {
        return '"[closure-modifiercompiler]"';
    }
}

class InvokableTests_FilterPre {
    public function __invoke($tpl_source, $template){
        return $tpl_source . "[closure-filter-pre]";
    }
}
class InvokableTests_FilterPost {
    public function __invoke($tpl_source, $template){
        return $tpl_source . "[closure-filter-pre]";
    }
}
class InvokableTests_FilterOutput {
    public function __invoke($compiled, $template){
        return $compiled . '<?php echo "[closure-filter-post]"; ?>';
    }
}
class InvokableTests_FilterVariable {
    public function __invoke($variable, $template){
        return $variable . "[closure-filter-variable]";
    }
}