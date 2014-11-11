<?php
/**
 * Smarty PHPunit tests cached variables ("meta storage")
 * 
 * @package PHPunit
 * @author Rodney Rehm 
 */

class CacheVariableTests extends PHPUnit_Framework_TestCase {

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
    * test simple assign
    */
    public function testAssign()
    {
        $this->smarty->registerPlugin('function', 'meta', array('CacheVariableTests', 'meta'));
        $this->smarty->caching = true;
        
        $tpl = $this->smarty->createTemplate('CacheVariableTests.tpl');
        $this->assertFalse($tpl->isCached());
        $tpl->assignCached('foo', 'Hello World');
		$this->assertEquals('Hello World', $tpl->fetch());
    }
    
    public function testFromCache()
    {
        $this->smarty->registerPlugin('function', 'meta', array('CacheVariableTests', 'meta'));
        $this->smarty->caching = true;
        
        $tpl = $this->smarty->createTemplate('CacheVariableTests.tpl');
        $this->assertTrue($tpl->isCached());
		$this->assertEquals('Hello World', $tpl->fetch());
		
		$this->smarty->clearCache('CacheVariableTests.tpl');
    }
    
    public static function meta($params, $template)
    {
        return $template->getCachedVars($params['var']);
    }
} 
