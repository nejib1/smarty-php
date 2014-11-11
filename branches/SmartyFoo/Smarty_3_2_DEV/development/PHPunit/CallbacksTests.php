<?php
/**
* Smarty PHPunit tests event callbacks
* 
* @package PHPunit
* @author Rodney Rehm
*/

class CallbacksTests extends PHPUnit_Framework_TestCase {

    public function setUp()
    {
        $this->smarty = SmartyTests::$smarty;
        SmartyTests::init();
    } 

    public static function isRunnable()
    {
        return true;
    } 
    
    public static $written = array();
    public static $deleted = array();
    
    public function testFilesystem()
    {
        $this->smarty->clearCompiledTemplate();
        $this->smarty->clearAllCache();
        
        self::$written = array();
        self::$deleted = array();
        
        Smarty::registerCallback('filesystem:write', array('CallbacksTests', '__write_callback'));
        Smarty::registerCallback('filesystem:delete', array('CallbacksTests', '__delete_callback'));
        
        $this->smarty->caching = true;
        $this->smarty->fetch('helloworld.tpl');

        $this->assertEquals("./templates_c/d09893dcb99d006e5dc24c072259b7fbe0daf635_0.file.helloworld.tpl.cache.php", self::$written[0]);
        $this->assertEquals("./cache/^^d09893dcb99d006e5dc24c072259b7fbe0daf635.helloworld.tpl.php", self::$written[1]);
        $this->assertEquals(array(), self::$deleted);
        
        $this->smarty->clearCache('helloworld.tpl');
        $this->assertEquals("./cache/^^d09893dcb99d006e5dc24c072259b7fbe0daf635.helloworld.tpl.php", self::$deleted[0]);
        $this->smarty->clearCompiledTemplate('helloworld.tpl');
        $this->assertEquals("./templates_c/d09893dcb99d006e5dc24c072259b7fbe0daf635_0.file.helloworld.tpl.cache.php", self::$deleted[1]);
    }
    
    
    public static function __write_callback($smarty, $filepath) {
        self::$written[] = $filepath;
    }
    
    public static function __delete_callback($smarty, $filepath) {
        self::$deleted[] = $filepath;
    }
}