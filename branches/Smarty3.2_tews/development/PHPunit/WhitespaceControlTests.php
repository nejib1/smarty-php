<?php
/**
* Smarty PHPunit tests tag whitespace control
*
* @package PHPunit
* @author Uwe Tews
*/


/**
* class for whitespace control tests
*/
class WhitespaceControlTests extends PHPUnit_Framework_TestCase {
    public function setUp()
    {
        $this->smarty = SmartyTests::$smarty;
        SmartyTests::init();
        $this->smarty->assign('foo',1);
    }

    public static function isRunnable()
    {
        return true;
    }

    /**
    * test {-tag}  remove white space infront of tag up to the previous non-whitespace
    *  character or beginning of the line
    */
    public function testFrontWhitespace1()
    {
         $this->assertEquals("text \n\n1", $this->smarty->fetch("eval:text \n\n\t {-\$foo}"));
         $this->assertEquals("text \n\n\t text1", $this->smarty->fetch("eval:text \n\n\t text\t {-\$foo}"));
    }

    /**
    * test {--tag} remove white space infront of tag up to the previous non-whitespace character
    */
    public function testInfrontWhitespace2()
    {
         $this->assertEquals("text1", $this->smarty->fetch("eval:text \n\n\t {--\$foo}"));
         $this->assertEquals("text \n\n\t text1", $this->smarty->fetch("eval:text \n\n\t text\t {--\$foo}"));
    }

    /**
    * test {tag-}  remove white space after tag up to the next non-whitespace character or end of the line
    */
    public function testAfterWhitespace1()
    {
         $this->assertEquals("1\n\n\t text", $this->smarty->fetch("eval:{\$foo -} \n\n\t text"));
         $this->assertEquals("1text \n\n\t text", $this->smarty->fetch("eval:{\$foo -} text \n\n\t text"));
    }

    /**
    * test {tag--} remove white space after tag up to the next non-whitespace character
    */
    public function testAfterWhitespace2()
    {
         $this->assertEquals("1text", $this->smarty->fetch("eval:{\$foo --} \n\n\t text"));
         $this->assertEquals("1text \n\n\t text", $this->smarty->fetch("eval:{\$foo --} text \n\n\t text"));
    }
}
