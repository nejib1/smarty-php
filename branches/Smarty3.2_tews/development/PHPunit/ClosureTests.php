<?php
/**
* Smarty PHPunit tests closure plugins
*
* @package PHPunit
* @author Rodney Rehm
*/

class ClosureTests extends PHPUnit_Framework_TestCase {

    public function setUp()
    {
        $this->smarty = SmartyTests::$smarty;
        SmartyTests::init();
    }

    public static function isRunnable()
    {
        return false;
    }

    public function testPluginFunction()
    {
        $this->smarty->registerPlugin('function', 'closure_function', function($params, $template) {
            return "[closure-function]";
        });
        $this->assertEquals("[closure-function]", $this->smarty->fetch('eval:{closure_function}'));
    }

    public function testPluginBlock()
    {
        $this->smarty->registerPlugin('block', 'closure_block', function($params, $content, $template, &$repeat) {
            $t = $repeat ? "[closure-block]" : '';
            $repeat = false;
            return $t;
        });
        $this->assertEquals("[closure-block]", $this->smarty->fetch('eval:{closure_block}{/closure_block}'));
    }

    public function testPluginModifier()
    {
        $this->smarty->registerPlugin('modifier', 'closure_modifier', function($text) {
            return "[closure-modifier]";
        });
        $this->assertEquals("[closure-modifier]", $this->smarty->fetch('eval:{""|closure_modifier}'));
    }

    public function testPluginModifierCompiler()
    {
        $this->smarty->registerPlugin('modifiercompiler', 'closure_modifiercompiler', function($params) {
            return '"[closure-modifiercompiler]"';
        });
        $this->assertEquals("[closure-modifiercompiler]", $this->smarty->fetch('eval:{""|closure_modifiercompiler}'));
    }

    public function testFilterPre()
    {
        $this->smarty->registerFilter('pre', function($tpl_source, $template){
            return $tpl_source . "[closure-filter-pre]";
        });
        $this->assertEquals("foo[closure-filter-pre]", $this->smarty->fetch('eval:foo'));
    }

    public function testFilterPost()
    {
        $this->smarty->registerFilter('pre', function($tpl_source, $template){
            return $tpl_source . "[closure-filter-pre]";
        });
        $this->assertEquals("foo[closure-filter-pre]", $this->smarty->fetch('eval:foo'));
    }

    public function testFilterOutput()
    {
        $this->smarty->registerFilter('post', function($compiled, $template){
            return $compiled . '<?php echo "[closure-filter-post]"; ?>';
        });
        $this->assertEquals("foo[closure-filter-post]", $this->smarty->fetch('eval:foo'));
    }

    public function testFilterVariable()
    {
        $this->smarty->registerFilter('variable', function($variable, $template){
            return $variable . "[closure-filter-variable]";
        });
        $this->smarty->assign('foo', 'foo');
        $this->assertEquals("foo[closure-filter-variable]", $this->smarty->fetch('eval:{$foo}'));
    }
}
