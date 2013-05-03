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
abstract class Smarty_CacheResource
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
     * @param Smarty_Template_Cached $cached cached object
     * @param Smarty $_template template object
     * @return void
     */
    public abstract function populate(Smarty_Template_Cached $cached, Smarty $_template);

    /**
     * populate Cached Object with timestamp and exists from Resource
     *
     * @param Smarty_Template_Cached $cached cached object
     * @return void
     */
    public abstract function populateTimestamp(Smarty_Template_Cached $cached);

    /**
     * Read the cached template and process header
     *
     * @param Smarty $_template template object
     * @param Smarty_Template_Cached $cached cached object
     * @return boolean true or false if the cached content does not exist
     */
    public abstract function process(Smarty $_template, Smarty_Template_Cached $cached = null);

    /**
     * Write the rendered template output to cache
     *
     * @param Smarty $_template template object
     * @param string $content content to cache
     * @return boolean success
     */
    public abstract function writeCachedContent(Smarty $_template, $content);

    /**
     * Return cached content
     *
     * @param Smarty $_template template object
     * @return string content of cache
     */
    public function getCachedContent(Smarty $_template)
    {
        if ($_template->cached->handler->process($_template)) {
            return $_template->cached->smarty_content->get_template_content($_template);
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

    public function locked(Smarty $smarty, Smarty_Template_Cached $cached)
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

    public function hasLock(Smarty $smarty, Smarty_Template_Cached $cached)
    {
        // check if lock exists
        return false;
    }

    public function acquireLock(Smarty $smarty, Smarty_Template_Cached $cached)
    {
        // create lock
        return true;
    }

    public function releaseLock(Smarty $smarty, Smarty_Template_Cached $cached)
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
class Smarty_Template_Cached
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
     * Source Existance
     * @var boolean
     */
    public $exists = false;

    /**
     * Cache Is Valid
     * @var boolean
     */
    public $valid = false;

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
     * @param Smarty $_template template object
     */
    public function __construct(Smarty $_template)
    {
        $this->compile_id = $_template->compile_id;
        $this->cache_id = $_template->cache_id;
        $this->source = $_template->source;
        $_template->cached = $this;

        //
        // load resource handler
        //
        $this->handler = $handler = Smarty_CacheResource::load($_template); // Note: prone to circular references
        //
        //    check if cache is valid
        //
        if (!($_template->caching == Smarty::CACHING_LIFETIME_CURRENT || $_template->caching == Smarty::CACHING_LIFETIME_SAVED) || $_template->source->recompiled) {
            $handler->populate($this, $_template);
            return;
        }
        while (true) {
            while (true) {
                $handler->populate($this, $_template);
                if ($this->timestamp === false || $_template->force_compile || $_template->force_cache) {
                    $this->valid = false;
                } else {
                    $this->valid = true;
                }
                if ($this->valid && $_template->caching == Smarty::CACHING_LIFETIME_CURRENT && $_template->cache_lifetime >= 0 && time() > ($this->timestamp + $_template->cache_lifetime)) {
                    // lifetime expired
                    $this->valid = false;
                }
                if ($this->valid || !$_template->cache_locking) {
                    break;
                }
                if (!$this->handler->locked($_template, $this)) {
                    $this->handler->acquireLock($_template, $this);
                    break 2;
                }
            }
            if ($this->valid) {
                if (!$_template->cache_locking || $this->handler->locked($_template, $this) === null) {
                    // load cache file for the following checks
                    if ($_template->debugging) {
                        Smarty_Internal_Debug::start_cache($_template);
                    }
                    if ($handler->process($_template, $this) === false) {
                        $this->valid = false;
                    }
                    if ($_template->debugging) {
                        Smarty_Internal_Debug::end_cache($_template);
                    }
                } else {
                    continue;
                }
            } else {
                return;
            }
            if ($this->valid && $_template->caching === Smarty::CACHING_LIFETIME_SAVED && $_template->cached->smarty_content->cache_lifetime >= 0 && (time() > ($_template->cached->timestamp + $_template->cached->smarty_content->cache_lifetime))) {
                $this->valid = false;
            }
            if (!$this->valid && $_template->cache_locking) {
                $this->handler->acquireLock($_template, $this);
                return;
            } else {
                return;
            }
        }
    }

    /**
     * get rendered template output from cached template
     *
     * @param Smarty $_template template object
     * @param bool $no_output_filter flag that output filter shall be ignored
     * @throws Exception
     * @return
     */
    public function getRenderedTemplate($_template, $no_output_filter)
    {
        if (!$this->valid) {
            if ($_template->source->uncompiled) {
                $output = $_template->source->getRenderedTemplate($_template);
            } else {
                $output = $_template->compiled->getRenderedTemplate($_template);
            }
            // write to cache when nessecary
            if (!$_template->source->recompiled) {
                $output = $this->newcache->_createCacheFile($_template, $output, $no_output_filter);
            }
        } else {
            if ($_template->debugging) {
                Smarty_Internal_Debug::start_cache($_template);
            }
            $_template->is_nocache = true;
            try {
                $level = ob_get_level();
                array_unshift($_template->_capture_stack, array());
                //
                // render cached template
                //
                $output = $this->smarty_content->get_template_content($_template);
                // any unclosed {capture} tags ?
                if (isset($_template->_capture_stack[0][0])) {
                    $_template->_capture_error();
                }
                array_shift($_template->_capture_stack);
            } catch (Exception $e) {
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }
                throw $e;
            }
            $_template->is_nocache = false;
            if ($_template->debugging) {
                Smarty_Internal_Debug::end_cache($_template);
            }
        }
        return $output;
    }

    /**
     * Write this cache object to handler
     *
     * @param Smarty_Internal_Template $_template template object
     * @param string $content content to cache
     * @return boolean success
     */
    public function write(Smarty $_template, $content)
    {
        if (!$_template->source->recompiled) {
            if ($this->handler->writeCachedContent($_template, $content)) {
                $this->timestamp = time();
                $this->exists = true;
                $this->valid = true;
                if ($_template->cache_locking) {
                    $this->handler->releaseLock($_template->smarty, $this);
                }
                return true;
            }
        }
        return false;
    }


    /**
     * <<magic>> Generic getter.
     * Get Smarty_Template_Cache property
     *
     * @param string $property_name property name
     * @throws SmartyException
     * @return $this|bool|\Smarty_Compiled|\Smarty_Template_Cached|\Smarty_Template_Source
     */
    public function __get($property_name)
    {
        switch ($property_name) {
            case 'newcache':
                $this->newcache = new Smarty_Internal_CacheCreate();
                return $this->newcache;
        }
        throw new SmartyException("Undefined property '$property_name'.");
    }

    /**
     * <<magic>> Generic setter.
     * Set Smarty_Template_Cache property
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
        throw new SmartyException("Undefined property '$property_name'.");
    }
}
