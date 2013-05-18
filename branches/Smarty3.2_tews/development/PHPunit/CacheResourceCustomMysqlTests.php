<?php
/**
 * Smarty PHPunit tests for cache resource file
 *
 * @package PHPunit
 * @author Uwe Tews
 */

/**
 * class for cache resource file tests
 */
class CacheResourceCustomMysqlTests extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->smarty = SmartyTests::$smarty;
        SmartyTests::init();
        $this->smarty->caching_type = 'mysqltest';
        $this->smarty->addPluginsDir(dirname(__FILE__) . "/PHPunitplugins/");
        $this->truncateTable();
    }


    public static function isRunnable()
    {
        return true;
    }

    /**
     * Truncates the output_cache table
     *
     * @throws SmartyException
     */
    public function truncateTable() {
        try {
            $db = new PDO("mysql:dbname=test;host=localhost", "smarty");
        } catch (PDOException $e) {
            throw new SmartyException('Mysql Resource failed: ' . $e->getMessage());
        }
        $db->exec('TRUNCATE TABLE output_cache');
    }
    protected function doClearCacheAssertion($a, $b)
    {
        $this->assertEquals($a, $b);
    }

    /**
     * Return cached content
     *
     * @param Smarty $tpl_obj template object
     * @return string content of cache
     */
    public function getCachedContent(Smarty $tpl_obj)
    {
        if ($tpl_obj->cached->process($tpl_obj)) {
            return $tpl_obj->cached->smarty_content->get_template_content($tpl_obj, new Smarty_Variable_Scope());
        }
        return null;
    }

    /**
     * test getCachedFilepath with use_sub_dirs enabled
     */
    public function testGetCachedFilepathSubDirs()
    {
        $this->smarty->caching = true;
        $this->smarty->cache_lifetime = 1000;
        $tpl = $this->smarty->createTemplate('helloworld.tpl');
        $sha1 = sha1($tpl->source->filepath);
        $this->assertEquals($sha1, $tpl->cached->filepath);
    }

    /**
     * test getCachedFilepath with cache_id
     */
    public function testGetCachedFilepathCacheId()
    {
        $this->smarty->caching = true;
        $this->smarty->cache_lifetime = 1000;
        $tpl = $this->smarty->createTemplate('helloworld.tpl', 'foo|bar');
        $sha1 = sha1($tpl->source->filepath . 'foo|bar' . null);
        $this->assertEquals($sha1, $tpl->cached->filepath);
    }

    /**
     * test getCachedFilepath with compile_id
     */
    public function testGetCachedFilepathCompileId()
    {
        $this->smarty->caching = true;
        $this->smarty->cache_lifetime = 1000;
        $tpl = $this->smarty->createTemplate('helloworld.tpl', null, 'blar');
        $sha1 = sha1($tpl->source->filepath . null . 'blar');
        $this->assertEquals($sha1, $tpl->cached->filepath);
    }

    /**
     * test getCachedFilepath with cache_id and compile_id
     */
    public function testGetCachedFilepathCacheIdCompileId()
    {
        $this->smarty->caching = true;
        $this->smarty->cache_lifetime = 1000;
        $tpl = $this->smarty->createTemplate('helloworld.tpl', 'foo|bar', 'blar');
        $sha1 = sha1($tpl->source->filepath . 'foo|bar' . 'blar');
        $this->assertEquals($sha1, $tpl->cached->filepath);
    }

    /**
     * test cache->clear_all with cache_id and compile_id
     */
    public function testClearCacheAllCacheIdCompileId()
    {
        $this->smarty->clearAllCache();
        $this->smarty->caching = true;
        $this->smarty->cache_lifetime = 1000;
        $tpl = $this->smarty->createTemplate('helloworld.tpl', 'foo|bar', 'blar');
        $tpl->fetch();
        $this->assertEquals('hello world', $this->getCachedContent($tpl));
        // Custom CacheResources may return -1 if they can't tell the number of deleted elements
        $this->assertEquals(-1, $this->smarty->clearAllCache());
    }

    /**
     * test cache->clear with cache_id and compile_id
     */
    public function testClearCacheCacheIdCompileId()
    {
        $this->smarty->caching = true;
        $this->smarty->cache_lifetime = 1000;
        $this->smarty->clearAllCache();
        // create and cache templates
        $tpl = $this->smarty->createTemplate('helloworld_1.tpl', 'foo|bar', 'blar');
        $tpl->fetch();
         $tpl2 = $this->smarty->createTemplate('helloworld_2.tpl', 'foo|bar2', 'blar');
        $tpl2->fetch();
        $tpl3 = $this->smarty->createTemplate('helloworld_3.tpl', 'foo|bar', 'blar');
        $tpl3->fetch();
       // test cached content
        $this->assertEquals('hello world 1', $this->getCachedContent($tpl));
        $this->assertEquals('hello world 2', $this->getCachedContent($tpl2));
        $this->assertEquals('hello world 3', $this->getCachedContent($tpl3));
        // test number of deleted caches
        $this->doClearCacheAssertion(2, $this->smarty->clearCache(null, 'foo|bar'));
        // test that caches are deleted properly
        $this->assertNull($this->getCachedContent($tpl));
        $this->assertEquals('hello world 2', $this->getCachedContent($tpl2));
        $this->assertNull($this->getCachedContent($tpl3));
    }

    public function testClearCacheCacheIdCompileId2()
    {
        $this->smarty->caching = true;
        $this->smarty->cache_lifetime = 1000;
        $this->smarty->clearAllCache();
        // create and cache templates
        $tpl = $this->smarty->createTemplate('helloworld.tpl', 'foo|bar', 'blar');
        $tpl->fetch();
        $tpl2 = $this->smarty->createTemplate('helloworld.tpl', 'foo|bar2', 'blar');
        $tpl2->fetch();
         $tpl3 = $this->smarty->createTemplate('helloworld2.tpl', 'foo|bar', 'blar');
        $tpl3->fetch();
       // test cached content
        $this->assertEquals('hello world', $this->getCachedContent($tpl));
        $this->assertEquals('hello world', $this->getCachedContent($tpl2));
        $this->assertEquals('hello world', $this->getCachedContent($tpl3));
        // test number of deleted caches
        $this->doClearCacheAssertion(2, $this->smarty->clearCache('helloworld.tpl'));
        // test that caches are deleted properly
        $this->assertNull($this->getCachedContent($tpl));
        $this->assertNull($this->getCachedContent($tpl2));
        $this->assertEquals('hello world', $this->getCachedContent($tpl3));
    }

    public function testClearCacheCacheIdCompileId2Sub()
    {
        $this->smarty->caching = true;
        $this->smarty->cache_lifetime = 1000;
        $this->smarty->clearAllCache();
        // create and cache templates
        $tpl = $this->smarty->createTemplate('helloworld.tpl', 'foo|bar', 'blar');
        $tpl->fetch();
        $tpl2 = $this->smarty->createTemplate('helloworld.tpl', 'foo|bar2', 'blar');
        $tpl2->fetch();
         $tpl3 = $this->smarty->createTemplate('helloworld2.tpl', 'foo|bar', 'blar');
        $tpl3->fetch();
         // test cached content
        $this->assertEquals('hello world', $this->getCachedContent($tpl));
        $this->assertEquals('hello world', $this->getCachedContent($tpl2));
        $this->assertEquals('hello world', $this->getCachedContent($tpl3));
        // test number of deleted caches
        $this->doClearCacheAssertion(2, $this->smarty->clearCache('helloworld.tpl'));
        // test that caches are deleted properly
        $this->assertNull($this->getCachedContent($tpl));
        $this->assertNull($this->getCachedContent($tpl2));
        $this->assertEquals('hello world', $this->getCachedContent($tpl3));
    }

    public function testClearCacheCacheIdCompileId3()
    {
        $this->smarty->caching = true;
        $this->smarty->cache_lifetime = 1000;
        $this->smarty->clearAllCache();
        // create and cache templates
        $tpl = $this->smarty->createTemplate('helloworld.tpl', 'foo|bar', 'blar');
        $tpl->fetch();
        $tpl2 = $this->smarty->createTemplate('helloworld.tpl', 'foo|bar', 'blar2');
        $tpl2->fetch();
        $tpl3 = $this->smarty->createTemplate('helloworld2.tpl', 'foo|bar', 'blar');
        $tpl3->fetch();
         // test cached content
        $this->assertEquals('hello world', $this->getCachedContent($tpl));
        $this->assertEquals('hello world', $this->getCachedContent($tpl2));
        $this->assertEquals('hello world', $this->getCachedContent($tpl3));
        // test number of deleted caches
        $this->doClearCacheAssertion(1, $this->smarty->clearCache('helloworld.tpl', null, 'blar2'));
        // test that caches are deleted properly
        $this->assertEquals('hello world', $this->getCachedContent($tpl));
        $this->assertNull($this->getCachedContent($tpl2));
        $this->assertEquals('hello world', $this->getCachedContent($tpl3));
    }

    public function testClearCacheCacheIdCompileId3Sub()
    {
        $this->smarty->caching = true;
        $this->smarty->cache_lifetime = 1000;
        $this->smarty->clearAllCache();
        // create and cache templates
        $tpl = $this->smarty->createTemplate('helloworld.tpl', 'foo|bar', 'blar');
        $tpl->fetch();
        $tpl2 = $this->smarty->createTemplate('helloworld.tpl', 'foo|bar', 'blar2');
        $tpl2->fetch();
         $tpl3 = $this->smarty->createTemplate('helloworld2.tpl', 'foo|bar', 'blar');
        $tpl3->fetch();
        // test cached content
        $this->assertEquals('hello world', $this->getCachedContent($tpl));
        $this->assertEquals('hello world', $this->getCachedContent($tpl2));
        $this->assertEquals('hello world', $this->getCachedContent($tpl3));
        // test number of deleted caches
        $this->doClearCacheAssertion(1, $this->smarty->clearCache('helloworld.tpl', null, 'blar2'));
        // test that caches are deleted properly
        $this->assertEquals('hello world', $this->getCachedContent($tpl));
        $this->assertNull($this->getCachedContent($tpl2));
        $this->assertEquals('hello world', $this->getCachedContent($tpl3));
    }

    public function testClearCacheCacheIdCompileId4()
    {
        $this->smarty->caching = true;
        $this->smarty->cache_lifetime = 1000;
        $this->smarty->clearAllCache();
        // create and cache templates
        $tpl = $this->smarty->createTemplate('helloworld.tpl', 'foo|bar', 'blar');
        $tpl->fetch();
        $tpl2 = $this->smarty->createTemplate('helloworld.tpl', 'foo|bar', 'blar2');
        $tpl2->fetch();
        $tpl3 = $this->smarty->createTemplate('helloworld2.tpl', 'foo|bar', 'blar');
        $tpl3->fetch();
       // test cached content
        $this->assertEquals('hello world', $this->getCachedContent($tpl));
        $this->assertEquals('hello world', $this->getCachedContent($tpl2));
        $this->assertEquals('hello world', $this->getCachedContent($tpl3));
        // test number of deleted caches
        $this->doClearCacheAssertion(1, $this->smarty->clearCache('helloworld.tpl', null, 'blar2'));
        // test that caches are deleted properly
        $this->assertEquals('hello world', $this->getCachedContent($tpl));
        $this->assertNull($this->getCachedContent($tpl2));
        $this->assertEquals('hello world', $this->getCachedContent($tpl3));
    }

    public function testClearCacheCacheIdCompileId4Sub()
    {
        $this->smarty->caching = true;
        $this->smarty->cache_lifetime = 1000;
        $this->smarty->clearAllCache();
        // create and cache templates
        $tpl = $this->smarty->createTemplate('helloworld.tpl', 'foo|bar', 'blar');
        $tpl->fetch();
         $tpl2 = $this->smarty->createTemplate('helloworld.tpl', 'foo|bar', 'blar2');
        $tpl2->fetch();
       $tpl3 = $this->smarty->createTemplate('helloworld2.tpl', 'foo|bar', 'blar');
        $tpl3->fetch();
       // test cached content
        $this->assertEquals('hello world', $this->getCachedContent($tpl));
        $this->assertEquals('hello world', $this->getCachedContent($tpl2));
        $this->assertEquals('hello world', $this->getCachedContent($tpl3));
        // test number of deleted caches
        $this->doClearCacheAssertion(1, $this->smarty->clearCache('helloworld.tpl', null, 'blar2'));
        // test that caches are deleted properly
        $this->assertEquals('hello world', $this->getCachedContent($tpl));
        $this->assertNull($this->getCachedContent($tpl2));
        $this->assertEquals('hello world', $this->getCachedContent($tpl3));
    }

    public function testClearCacheCacheIdCompileId5()
    {
        $this->smarty->caching = true;
        $this->smarty->cache_lifetime = 1000;
        $this->smarty->clearAllCache();
        // create and cache templates
        $tpl = $this->smarty->createTemplate('helloworld.tpl', 'foo|bar', 'blar');
        $tpl->fetch();
        $tpl2 = $this->smarty->createTemplate('helloworld.tpl', 'foo|bar', 'blar2');
        $tpl2->fetch();
       $tpl3 = $this->smarty->createTemplate('helloworld2.tpl', 'foo|bar', 'blar');
        $tpl3->fetch();
         // test cached content
        $this->assertEquals('hello world', $this->getCachedContent($tpl));
        $this->assertEquals('hello world', $this->getCachedContent($tpl2));
        $this->assertEquals('hello world', $this->getCachedContent($tpl3));
        // test number of deleted caches
        $this->doClearCacheAssertion(2, $this->smarty->clearCache(null, null, 'blar'));
        // test that caches are deleted properly
        $this->assertNull($this->getCachedContent($tpl));
        $this->assertEquals('hello world', $this->getCachedContent($tpl2));
        $this->assertNull($this->getCachedContent($tpl3));
    }

    public function testClearCacheCacheIdCompileId5Sub()
    {
        $this->smarty->caching = true;
        $this->smarty->cache_lifetime = 1000;
        $this->smarty->clearAllCache();
        // create and cache templates
        $tpl = $this->smarty->createTemplate('helloworld.tpl', 'foo|bar', 'blar');
        $tpl->fetch();
        $tpl2 = $this->smarty->createTemplate('helloworld.tpl', 'foo|bar', 'blar2');
        $tpl2->fetch();
         $tpl3 = $this->smarty->createTemplate('helloworld2.tpl', 'foo|bar', 'blar');
        $tpl3->fetch();
       // test cached content
        $this->assertEquals('hello world', $this->getCachedContent($tpl));
        $this->assertEquals('hello world', $this->getCachedContent($tpl2));
        $this->assertEquals('hello world', $this->getCachedContent($tpl3));
        // test number of deleted caches
        $this->doClearCacheAssertion(2, $this->smarty->clearCache(null, null, 'blar'));
        // test that caches are deleted properly
        $this->assertNull($this->getCachedContent($tpl));
        $this->assertEquals('hello world', $this->getCachedContent($tpl2));
        $this->assertNull($this->getCachedContent($tpl3));
    }

    public function testClearCacheCacheFile()
    {
        $this->smarty->caching = true;
        $this->smarty->cache_lifetime = 1000;
        $this->smarty->clearAllCache();
        // create and cache templates
        $tpl = $this->smarty->createTemplate('helloworld.tpl');
        $tpl->fetch();
        $tpl2 = $this->smarty->createTemplate('helloworld.tpl', null, 'bar');
        $tpl2->fetch();
         $tpl3 = $this->smarty->createTemplate('helloworld.tpl', 'buh|blar');
        $tpl3->fetch();
        $tpl4 = $this->smarty->createTemplate('helloworld2.tpl');
        $tpl4->fetch();
       // test cached content
        $this->assertEquals('hello world', $this->getCachedContent($tpl));
        $this->assertEquals('hello world', $this->getCachedContent($tpl2));
        $this->assertEquals('hello world', $this->getCachedContent($tpl3));
        $this->assertEquals('hello world', $this->getCachedContent($tpl4));
        // test number of deleted caches
        $this->doClearCacheAssertion(3, $this->smarty->clearCache('helloworld.tpl'));
        // test that caches are deleted properly
        $this->assertNull($this->getCachedContent($tpl));
        $this->assertNull($this->getCachedContent($tpl2));
        $this->assertNull($this->getCachedContent($tpl3));
        $this->assertEquals('hello world', $this->getCachedContent($tpl4));
    }

    public function testClearCacheCacheFileSub()
    {
        $this->smarty->caching = true;
        $this->smarty->cache_lifetime = 1000;
        $this->smarty->clearAllCache();
        // create and cache templates
        $tpl = $this->smarty->createTemplate('helloworld.tpl');
        $tpl->fetch();
        $tpl2 = $this->smarty->createTemplate('helloworld.tpl', null, 'bar');
        $tpl2->fetch();
        $tpl3 = $this->smarty->createTemplate('helloworld.tpl', 'buh|blar');
        $tpl3->fetch();
        $tpl4 = $this->smarty->createTemplate('helloworld2.tpl');
        $tpl4->fetch();
         // test cached content
        $this->assertEquals('hello world', $this->getCachedContent($tpl));
        $this->assertEquals('hello world', $this->getCachedContent($tpl2));
        $this->assertEquals('hello world', $this->getCachedContent($tpl3));
        $this->assertEquals('hello world', $this->getCachedContent($tpl4));
        // test number of deleted caches
        $this->doClearCacheAssertion(3, $this->smarty->clearCache('helloworld.tpl'));
        // test that caches are deleted properly
        $this->assertNull($this->getCachedContent($tpl));
        $this->assertNull($this->getCachedContent($tpl2));
        $this->assertNull($this->getCachedContent($tpl3));
        $this->assertEquals('hello world', $this->getCachedContent($tpl4));
    }

    /**
     * final cleanup
     */
    public function testFinalCleanup2()
    {
        $this->smarty->clearCompiledTemplate();
        $this->smarty->clearAllCache();
    }
}

?>