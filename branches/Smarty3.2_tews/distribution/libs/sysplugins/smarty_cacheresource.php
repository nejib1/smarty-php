<?php

/**
 * Smarty Internal Plugin
 *
 *
 * @package Cacher
 */

/**
 * Cache Handler API
 *
 *
 * @package Cacher
 * @author Rodney Rehm
 */
abstract class Smarty_CacheResource extends Smarty_Internal_Magic_Error
{

    /**
     * cache for Smarty_CacheResource instances
     * @var array
     */
    public static $resources = array();

    /**
     * resource types provided by the core
     * @var array
     */
    protected static $sysplugins = array(
        'file' => true,
    );

    /**
     * populate Cached Object with meta data from Resource
     *
     * @param Smarty_template_Cached $cached cached object
     * @param Smarty $tpl_obj template object
     * @return void
     */
    public abstract function populate(Smarty_template_Cached $cached, Smarty $tpl_obj);

    /**
     * populate Cached Object with timestamp and exists from Resource
     *
     * @param Smarty_template_Cached $cached cached object
     * @return void
     */
    public abstract function populateTimestamp(Smarty_template_Cached $cached);

    /**
     * Read the cached template and process header
     *
     * @param Smarty $tpl_obj template object
     * @param Smarty_template_Cached $cached cached object
     * @return boolean true or false if the cached content does not exist
     */
    public abstract function process(Smarty $tpl_obj, Smarty_template_Cached $cached = null);

    /**
     * Write the rendered template output to cache
     *
     * @param Smarty $tpl_obj template object
     * @param string $content content to cache
     * @return boolean success
     */
    public abstract function writeCachedContent(Smarty $tpl_obj, $content);

    /**
     * Return cached content
     *
     * @param Smarty $tpl_obj template object
     * @return string content of cache
     */
    public function getCachedContent(Smarty $tpl_obj)
    {
        if ($tpl_obj->cached->handler->process($tpl_obj)) {
            return $tpl_obj->cached->smarty_content->get_template_content($tpl_obj);
        }
        return null;
    }

    /**
     * Empty cache
     *
     * @param Smarty $smarty Smarty object
     * @param integer $exp_time expiration time (number of seconds, not timestamp)
     * @return integer number of cache files deleted
     */
    public abstract function clearAll(Smarty $smarty, $exp_time = null);

    /**
     * Empty cache for a specific template
     *
     * @param Smarty $smarty Smarty object
     * @param string $resource_name template name
     * @param string $cache_id cache id
     * @param string $compile_id compile id
     * @param integer $exp_time expiration time (number of seconds, not timestamp)
     * @return integer number of cache files deleted
     */
    public abstract function clear(Smarty $smarty, $resource_name, $cache_id, $compile_id, $exp_time);

    public function locked(Smarty $smarty, Smarty_template_Cached $cached)
    {
        // theoretically locking_timeout should be checked against time_limit (max_execution_time)
        $start = microtime(true);
        $hadLock = null;
        while ($this->hasLock($smarty, $cached)) {
            $hadLock = true;
            if (microtime(true) - $start > $smarty->locking_timeout) {
                // abort waiting for lock release
                return false;
            }
            sleep(1);
        }
        return $hadLock;
    }

    public function hasLock(Smarty $smarty, Smarty_template_Cached $cached)
    {
        // check if lock exists
        return false;
    }

    public function acquireLock(Smarty $smarty, Smarty_template_Cached $cached)
    {
        // create lock
        return true;
    }

    public function releaseLock(Smarty $smarty, Smarty_template_Cached $cached)
    {
        // release lock
        return true;
    }

    /**
     * Load Cache Resource Handler
     *
     * @param Smarty $smarty Smarty object
     * @param string $type name of the cache resource
     * @throws SmartyException
     * @return Smarty_CacheResource Cache Resource Handler
     */
    public static function load(Smarty $smarty, $type = null)
    {
        if (!isset($type)) {
            $type = $smarty->caching_type;
        }

        // try resource cache
        if (isset(self::$resources[$type])) {
            return self::$resources[$type];
        }

        // try registered resource
        if (isset($smarty->registered_cache_resources[$type])) {
            // do not cache these instances as they may vary from instance to instance
            return $smarty->registered_cache_resources[$type];
        }
        // try sysplugins dir
        if (isset(self::$sysplugins[$type])) {
            $cache_resource_class = 'Smarty_Internal_CacheResource_' . ucfirst($type);
            return self::$resources[$type] = new $cache_resource_class();
        }
        // try plugins dir
        $cache_resource_class = 'Smarty_CacheResource_' . ucfirst($type);
        if ($smarty->_loadPlugin($cache_resource_class)) {
            return self::$resources[$type] = new $cache_resource_class();
        }
        // give up
        throw new SmartyException("Unable to load cache resource '{$type}'");
    }

    /**
     * Invalid Loaded Cache Files
     *
     * @param Smarty $smarty Smarty object
     */
    public static function invalidLoadedCache(Smarty $smarty)
    {
        foreach (Smarty::$template_objects as $tpl) {
            if (isset($tpl->cached)) {
                $tpl->cached->valid = false;
            }
        }
    }


    /**
     * Empty cache for a specific template
     *
     * @internal
     * @param string $template_name template name
     * @param string $cache_id      cache id
     * @param string $compile_id    compile id
     * @param integer $exp_time      expiration time
     * @param string $type          resource type
     * @param Smarty $smarty        Smarty object
     * @return integer number of cache files deleted
     */
    static function clearCache($template_name, $cache_id, $compile_id, $exp_time, $type, $smarty)
    {
        // load cache resource and call clear
        $_cache_resource = Smarty_CacheResource::load($smarty, $type);
        Smarty_CacheResource::invalidLoadedCache($smarty);
        return $_cache_resource->clear($smarty, $template_name, $cache_id, $compile_id, $exp_time);
    }

}

/**
 * Smarty Resource Data Object
 *
 * Cache Data Container for Template Files
 *
 *
 * @package Cacher
 * @author Rodney Rehm
 */
class Smarty_template_Cached extends Smarty_Internal_Magic_Error
{

    /**
     * Source Filepath
     * @var string
     */
    public $filepath = false;

    /**
     * Source Content
     * @var string
     */
    public $content = null;

    /**
     * instance of smarty content from cached file
     * @var Smarty_Content
     * @internal
     */
    public $smarty_content = null;

    /**
     * Source Timestamp
     * @var integer
     */
    public $timestamp = false;

    /**
     * Source Existence
     * @var boolean
     */
    public $exists = false;

    /**
     * Cache Is Valid
     * @var boolean
     */
    public $isValid = false;

    /**
     * CacheResource Handler
     * @var Smarty_CacheResource
     */
    public $handler = null;

    /**
     * Template Compile Id (Smarty::$compile_id)
     * @var string
     */
    public $compile_id = null;

    /**
     * Template Cache Id (Smarty::$cache_id)
     * @var string
     */
    public $cache_id = null;

    /**
     * Id for cache locking
     * @var string
     */
    public $lock_id = null;

    /**
     * flag that cache is locked by this instance
     * @var bool
     */
    public $is_locked = false;

    /**
     * Source Object
     * @var Smarty_Template_Source
     */
    public $source = null;


    /**
     * create Cached Object container
     *
     * @param Smarty $tpl_obj template object
     */
    public function __construct(Smarty $tpl_obj)
    {
        $this->compile_id = $tpl_obj->compile_id;
        $this->cache_id = $tpl_obj->cache_id;
        $this->source = $tpl_obj->source;
        $tpl_obj->cached = $this;

        //
        // load resource handler
        //
        $this->handler = $handler = Smarty_CacheResource::load($tpl_obj); // Note: prone to circular references
        //
        //    check if cache is valid
        //
        if (!($tpl_obj->caching == Smarty::CACHING_LIFETIME_CURRENT || $tpl_obj->caching == Smarty::CACHING_LIFETIME_SAVED) || $tpl_obj->source->recompiled) {
            $handler->populate($this, $tpl_obj);
            return;
        }
        while (true) {
            while (true) {
                $handler->populate($this, $tpl_obj);
                if ($this->timestamp === false || $tpl_obj->force_compile || $tpl_obj->force_cache) {
                    $this->isValid = false;
                } else {
                    $this->isValid = true;
                }
                if ($this->isValid && $tpl_obj->caching == Smarty::CACHING_LIFETIME_CURRENT && $tpl_obj->cache_lifetime >= 0 && time() > ($this->timestamp + $tpl_obj->cache_lifetime)) {
                    // lifetime expired
                    $this->isValid = false;
                }
                if ($this->isValid || !$tpl_obj->cache_locking) {
                    break;
                }
                if (!$this->handler->locked($tpl_obj, $this)) {
                    $this->handler->acquireLock($tpl_obj, $this);
                    break 2;
                }
            }
            if ($this->isValid) {
                if (!$tpl_obj->cache_locking || $this->handler->locked($tpl_obj, $this) === null) {
                    // load cache file for the following checks
                    if ($tpl_obj->debugging) {
                        Smarty_Internal_Debug::start_cache($tpl_obj);
                    }
                    if ($handler->process($tpl_obj, $this) === false) {
                        $this->isValid = false;
                    }
                    if ($tpl_obj->debugging) {
                        Smarty_Internal_Debug::end_cache($tpl_obj);
                    }
                } else {
                    continue;
                }
            } else {
                return;
            }
            if ($this->isValid && $tpl_obj->caching === Smarty::CACHING_LIFETIME_SAVED && $tpl_obj->cached->smarty_content->cache_lifetime >= 0 && (time() > ($tpl_obj->cached->timestamp + $tpl_obj->cached->smarty_content->cache_lifetime))) {
                $this->isValid = false;
            }
            if (!$this->isValid && $tpl_obj->cache_locking) {
                $this->handler->acquireLock($tpl_obj, $this);
                return;
            } else {
                return;
            }
        }
    }

    /**
     * get rendered template output from cached template
     *
     * @param Smarty $tpl_obj template object
     * @param Smarty_Variable_Scope $_scope
     * @param int $scope_type
     * @param array $data   array with variable names and values which must be assigned
     * @param bool $no_output_filter flag that output filter shall be ignored
     * @param bool $display
     * @throws Exception
     * @return bool|string
     */
    public function getRenderedTemplate($tpl_obj, $_scope, $scope_type, $data, $no_output_filter, $display)
    {
        $_scope = $tpl_obj->_buildScope($_scope, $scope_type, $data);
        $browser_cache_valid = false;
        if ($display && $tpl_obj->cache_modified_check && $tpl_obj->cached->isValid && !$tpl_obj->has_nocache_code) {
            $_last_modified_date = @substr($_SERVER['HTTP_IF_MODIFIED_SINCE'], 0, strpos($_SERVER['HTTP_IF_MODIFIED_SINCE'], 'GMT') + 3);
            if ($_last_modified_date !== false && $tpl_obj->cached->timestamp <= ($_last_modified_timestamp = strtotime($_last_modified_date)) &&
                $this->checkSubtemplateCache($tpl_obj, $_last_modified_timestamp)
            ) {
                $browser_cache_valid = true;
                switch (PHP_SAPI) {
                    case 'cgi': // php-cgi < 5.3
                    case 'cgi-fcgi': // php-cgi >= 5.3
                    case 'fpm-fcgi': // php-fpm >= 5.3.3
                        header('Status: 304 Not Modified');
                        break;

                    case 'cli':
                        if ( /* ^phpunit */
                            !empty($_SERVER['SMARTY_PHPUNIT_DISABLE_HEADERS']) /* phpunit$ */
                        ) {
                            $_SERVER['SMARTY_PHPUNIT_HEADERS'][] = '304 Not Modified';
                        }
                        break;

                    default:
                        header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
                        break;
                }
            }
        }
        if (!$browser_cache_valid) {
            if (!$this->isValid) {
                if ($tpl_obj->source->uncompiled) {
                    $output = $tpl_obj->source->getRenderedTemplate($tpl_obj, $_scope);
                } else {
                    $output = $tpl_obj->compiled->getRenderedTemplate($tpl_obj, $_scope);
                }
                // write to cache when necessary
                if (!$tpl_obj->source->recompiled) {
                    $output = $this->newcache->_createCacheFile($tpl_obj, $output, $_scope, $no_output_filter);
                }
            } else {
                if ($tpl_obj->debugging) {
                    Smarty_Internal_Debug::start_cache($tpl_obj);
                }
                $tpl_obj->is_nocache = true;
                try {
                    $level = ob_get_level();
                    array_unshift($tpl_obj->_capture_stack, array());
                    //
                    // render cached template
                    //
                    $output = $this->smarty_content->get_template_content($tpl_obj, $_scope);
                    // any unclosed {capture} tags ?
                    if (isset($tpl_obj->_capture_stack[0][0])) {
                        $tpl_obj->_capture_error();
                    }
                    array_shift($tpl_obj->_capture_stack);
                } catch (Exception $e) {
                    while (ob_get_level() > $level) {
                        ob_end_clean();
                    }
                    throw $e;
                }
                $tpl_obj->is_nocache = false;
                if ($tpl_obj->debugging) {
                    Smarty_Internal_Debug::end_cache($tpl_obj);
                }
            }
            if ($tpl_obj->has_nocache_code && !$no_output_filter && (isset($tpl_obj->autoload_filters['output']) || isset($tpl_obj->registered_filters['output']))) {
                $output = Smarty_Internal_Filter_Handler::runFilter('output', $output, $tpl_obj);
            }
            return $output;
        } else {
            // browser cache was valid
            return true;
        }
    }

    /**
     * Check timestamp of browser cache against timestamp of individually cached subtemplates
     *
     * @api
     * @param Smarty $tpl_obj template object
     * @param integer $_last_modified_timestamp browser cache timestamp
     * @return bool true if browser cache is valid
     */
    private function checkSubtemplateCache($tpl_obj, $_last_modified_timestamp)
    {
        $subtpl = reset($tpl_obj->cached_subtemplates);
        while ($subtpl) {
            $tpl = clone $this;
            unset($tpl->source, $tpl->compiled, $tpl->cached, $tpl->compiler, $tpl->mustCompile);
            $tpl->usage = self::IS_TEMPLATE;
            $tpl->template_resource = $subtpl[0];
            $tpl->cache_id = $subtpl[1];
            $tpl->compile_id = $subtpl[2];
            $tpl->caching = $subtpl[3];
            $tpl->cache_lifetime = $subtpl[4];
            if (!$tpl->cached->valid || $tpl->has_nocache_code || $tpl->cached->timestamp > $_last_modified_timestamp ||
                !$this->checkSubtemplateCache($tpl, $_last_modified_timestamp)
            ) {
                // browser cache invalid
                return false;
            }
            $subtpl = next($tpl_obj->cached_subtemplates);
        }
        // browser cache valid
        return true;
    }


    /**
     * Write this cache object to handler
     *
     * @param Smarty $tpl_obj template object
     * @param string $content content to cache
     * @return boolean success
     */
    public function write(Smarty $tpl_obj, $content)
    {
        if (!$tpl_obj->source->recompiled) {
            if ($this->handler->writeCachedContent($tpl_obj, $content)) {
                $this->timestamp = time();
                $this->exists = true;
                $this->isValid = true;
                if ($tpl_obj->cache_locking) {
                    $this->handler->releaseLock($tpl_obj->smarty, $this);
                }
                return true;
            }
        }
        return false;
    }


    /**
     * <<magic>> Generic getter.
     * Get Smarty_template_Cache property
     *
     * @param string $property_name property name
     * @throws SmartyException
     * @return $this|bool|\Smarty_Compiled|\Smarty_template_Cached|\Smarty_Template_Source
     */
    public function __get($property_name)
    {
        switch ($property_name) {
            case 'newcache':
                $this->newcache = new Smarty_Internal_CacheCreate();
                return $this->newcache;
        }
        parent::__get($property_name);
    }

    /**
     * <<magic>> Generic setter.
     * Set Smarty_template_Cache property
     *
     * @param string $property_name property name
     * @param mixed $value value
     * @throws SmartyException
     */
    public function __set($property_name, $value)
    {
        switch ($property_name) {
            case 'newcache':
                $this->$property_name = $value;
                return;
        }
        parent::__set($property_name, $value);
    }
}
