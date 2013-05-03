<?php
/**
 * Smarty PHPunit basic core function tests
 *
 * @package PHPunit
 * @author Uwe Tews
 */


/**
 * class core function tests
 */
class CoreTests extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->smarty = SmartyTests::$smarty;
        SmartyTests::init();
    }

    public static function isRunnable()
    {
        return true;
    }


    /**
     * _loadPlugin test unkown plugin
     */
    public function test_loadPluginErrorReturn()
    {
        $this->assertFalse($this->smarty->_loadPlugin('Smarty_Not_Known'));
    }

    /**
     * _loadPlugin test Smarty_Internal_Debug exists
     */
    public function test_loadPluginSmartyInternalDebug()
    {
        $this->assertTrue($this->smarty->_loadPlugin('Smarty_Internal_Debug') == true);
    }

    /**
     * _loadPlugin test loaging from plugins_dir
     */
    public function test_loadPluginSmartyPluginCounter()
    {
        $this->assertTrue($this->smarty->_loadPlugin('smarty_function_counter') == true);
    }
}

?>
