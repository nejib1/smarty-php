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
     * resource filepath
     *
     * @var string| boolean false
     */
    public $filepath = false;


    /**
     * resource time stamp
     *
     * @var int| boolean false
     */
    public $timestamp = false;

    /**
     * resource does exists
     *
     * @var boolean
     */
    public $exists = false;

    /**
     * instance of smarty content from cached file
     * @var Smarty_Content
     * @internal
     */
    public $smarty_content = null;


    /**
     * Cache Is Valid
     * @var boolean
     */
    public $isValid = false;

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
     * Read the cached template and process header
     *
     * @param Smarty $tpl_obj template object
     * @return boolean true or false if the cached content does not exist
     */
    public abstract function process(Smarty $tpl_obj);

    /**
     * Write the rendered template output to cache
     *
     * @param Smarty $tpl_obj template object
     * @param string $content content to cache
     * @return boolean success
     */
    public abstract function writeCachedContent(Smarty $tpl_obj, $content);

    /**
     *
     * construtor for cache resource
     *
     * @ param  Smarty $tpl_obj template object
     */
    public function __construct(Smarty $tpl_obj)
    {
        if ($tpl_obj->usage != Smarty::IS_SMARTY) {
            $tpl_obj->cached = $this;
        }
        $this->compile_id = $tpl_obj->compile_id;
        $this->cache_id = $tpl_obj->cache_id;
        if (isset($tpl_obj->source)) {
            $this->source = $tpl_obj->source;
        } else {
            $this->source = null;
        }
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

    public function locked(Smarty $tpl_obj)
    {
        // theoretically locking_timeout should be checked against time_limit (max_execution_time)
        $start = microtime(true);
        $hadLock = null;
        while ($this->hasLock($tpl_obj)) {
            $hadLock = true;
            if (microtime(true) - $start > $tpl_obj->locking_timeout) {
                // abort waiting for lock release
                return false;
            }
            sleep(1);
        }
        return $hadLock;
    }

    public function hasLock(Smarty $tpl_obj)
    {
        // check if lock exists
        return false;
    }

    public function acquireLock(Smarty $tpl_obj)
    {
        // create lock
        return true;
    }

    public function releaseLock(Smarty $tpl_obj)
    {
        // release lock
        return true;
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
                $tpl->cached->isValid = false;
            }
        }
        foreach (Smarty::$resource_cache as $source_key => $foo) {
            unset(Smarty::$resource_cache[$source_key]['cache']);
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
        $_cache_resource = $smarty->_loadHandler(SMARTY::CACHE, $type ? $type : $smarty->caching_type);
        Smarty_CacheResource::invalidLoadedCache($smarty);
        return $_cache_resource->clear($smarty, $template_name, $cache_id, $compile_id, $exp_time);
    }

    /**
     * Empty cache folder
     *
     * @api
     * @param Smarty $smarty    Smarty object
     * @param integer $exp_time expiration time
     * @param string $type     resource type
     * @return integer number of cache files deleted
     */
    public static function clearAllCache(Smarty $smarty, $exp_time = null, $type = null)
    {
        $_cache_resource = $smarty->_loadHandler(SMARTY::CACHE, $type ? $type : $smarty->caching_type);
        Smarty_CacheResource::invalidLoadedCache($smarty);
        return $_cache_resource->clearAll($smarty, $exp_time);
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
                if ($this->source->uncompiled) {
                    $output = $this->source->getRenderedTemplate($tpl_obj, $_scope);
                } else {
                    $output = $tpl_obj->compiled->getRenderedTemplate($tpl_obj, $_scope);
                }
                // write to cache when necessary
                if (!$this->source->recompiled) {
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
            $tpl->usage = Smarty::IS_TEMPLATE;
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
    public function writeCache(Smarty $tpl_obj, $content)
    {
        if (!$tpl_obj->source->recompiled) {
            if ($this->writeCachedContent($tpl_obj, $content)) {
                $this->timestamp = time();
                $this->exists = true;
                $this->isValid = true;
                if ($tpl_obj->cache_locking) {
                    $this->releaseLock($tpl_obj);
                }
                return true;
            }
        }
        return false;
    }

    /**
     * create Cached Object container
     *
     * @param Smarty $tpl_obj template object
     */
    public static function load(Smarty $tpl_obj, $type = null)
    {

        // todo  check the last cde sequence
        // check runtime cache
        $source_key = $tpl_obj->source->uid;
        $compiled_key = $tpl_obj->compile_id ? $tpl_obj->compile_id : '#null#';
        $cache_key = $tpl_obj->cache_id ? $tpl_obj->cache_id : '#null#';
        if ($tpl_obj->cache_objs && isset(Smarty::$resource_cache[$source_key]['cache'][$compiled_key][$cache_key])) {
            $res_obj = Smarty::$resource_cache[$source_key]['cache'][$compiled_key][$cache_key];
        } else {
            // load Cache resource handler
            $res_obj = Smarty_Resource::loadHandler($tpl_obj, $tpl_obj->caching_type, SMARTY::CACHE);
            $res_obj->populate($tpl_obj);
            // save in cache?
            if ($tpl_obj->cache_objs) {
                Smarty::$resource_cache[$source_key]['cache'][$compiled_key][$cache_key] = $res_obj;
            } else {
                // load Cache resource handler
                $res_obj = Smarty_Resource::loadHandller($tpl_obj, $tpl_obj->caching_type, SMARTY::CACHE);
                $res_obj->populate($tpl_obj);
                // save in cache?
                if ($tpl_obj->cache_objs) {
                    Smarty::$resource_cache[$source_key]['cache'][$compiled_key][$cache_key] = $res_obj;
                }
            }

            $res_obj->compile_id = $tpl_obj->compile_id;
            $res_obj->cache_id = $tpl_obj->cache_id;
            return $res_obj;

            if (!($tpl_obj->caching == Smarty::CACHING_LIFETIME_CURRENT || $tpl_obj->caching == Smarty::CACHING_LIFETIME_SAVED) || $this->source->recompiled) {
                $res_obj->populate($tpl_obj);
                return;
            }
            while (true) {
                while (true) {
                    $res_obj->populate($tpl_obj);
                    if ($res_obj->timestamp === false || $tpl_obj->force_compile || $tpl_obj->force_cache) {
                        $res_obj->isValid = false;
                    } else {
                        $res_obj->isValid = true;
                    }
                    if ($res_obj->isValid && $tpl_obj->caching == Smarty::CACHING_LIFETIME_CURRENT && $tpl_obj->cache_lifetime >= 0 && time() > ($res_obj->timestamp + $tpl_obj->cache_lifetime)) {
                        // lifetime expired
                        $res_obj->isValid = false;
                    }
                    if ($res_obj->isValid || !$tpl_obj->cache_locking) {
                        break;
                    }
                    if (!$res_obj->locked($tpl_obj)) {
                        $res_obj->acquireLock($tpl_obj);
                        break 2;
                    }
                }
                if ($res_obj->isValid) {
                    if (!$tpl_obj->cache_locking || $res_obj->locked($tpl_obj) === null) {
                        // load cache file for the following checks
                        if ($tpl_obj->debugging) {
                            Smarty_Internal_Debug::start_cache($tpl_obj);
                        }
                        if ($res_obj->process($tpl_obj) === false) {
                            $res_obj->isValid = false;
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
                if ($res_obj->isValid && $tpl_obj->caching === Smarty::CACHING_LIFETIME_SAVED && $res_obj->smarty_content->cache_lifetime >= 0 && (time() > ($res_obj->timestamp + $res_obj->cache_lifetime))) {
                    $res_obj->isValid = false;
                }
                if (!$res_obj->isValid && $tpl_obj->cache_locking) {
                    $res_obj->acquireLock($tpl_obj);
                    return;
                } else {
                    return;
                }
            }
        }
    }


    /**
     * <<magic>> Generic getter.
     * Get Smarty_template_Cache property
     *
     * @param string $property_name property name
     * @throws SmartyException
     * @return $this|bool|\Smarty_Compiled|\Smarty_template_Cached|Smarty_Resource
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
