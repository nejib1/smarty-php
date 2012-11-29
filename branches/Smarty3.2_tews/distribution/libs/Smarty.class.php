<?php

    /**
    * Project:     Smarty: the PHP compiling template engine
    * File:        Smarty.class.php
    * SVN:         $Id$
    *
    * This library is free software; you can redistribute it and/or
    * modify it under the terms of the GNU Lesser General Public
    * License as published by the Free Software Foundation; either
    * version 2.1 of the License, or (at your option) any later version.
    *
    * This library is distributed in the hope that it will be useful,
    * but WITHOUT ANY WARRANTY; without even the implied warranty of
    * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
    * Lesser General Public License for more details.
    *
    * You should have received a copy of the GNU Lesser General Public
    * License along with this library; if not, write to the Free Software
    * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
    *
    * For questions, help, comments, discussion, etc., please join the
    * Smarty mailing list. Send a blank e-mail to
    * smarty-discussion-subscribe@googlegroups.com
    *
    * @link http://www.smarty.net/
    * @copyright 2008 New Digital Group, Inc.
    * @author Monte Ohrt <monte at ohrt dot com>
    * @author Uwe Tews
    * @author Rodney Rehm
    * @package Smarty
    * @version 3.2-DEV
    */
    /**
    * define shorthand directory separator constant
    */
    if (!defined('DS')) {
        define('DS', DIRECTORY_SEPARATOR);
    }

    /**
    * set SMARTY_DIR to absolute path to Smarty library files.
    * Sets SMARTY_DIR only if user application has not already defined it.
    */
    if (!defined('SMARTY_DIR')) {
        define('SMARTY_DIR', dirname(__FILE__) . DS);
    }

    /**
    * set SMARTY_SYSPLUGINS_DIR to absolute path to Smarty internal plugins.
    * Sets SMARTY_SYSPLUGINS_DIR only if user application has not already defined it.
    */
    if (!defined('SMARTY_SYSPLUGINS_DIR')) {
        define('SMARTY_SYSPLUGINS_DIR', SMARTY_DIR . 'sysplugins' . DS);
    }
    if (!defined('SMARTY_PLUGINS_DIR')) {
        define('SMARTY_PLUGINS_DIR', SMARTY_DIR . 'plugins' . DS);
    }
    if (!defined('SMARTY_MBSTRING')) {
        define('SMARTY_MBSTRING', function_exists('mb_split'));
    }
    if (!defined('SMARTY_RESOURCE_CHAR_SET')) {
        // UTF-8 can only be done properly when mbstring is available!
        /**
        * @deprecated in favor of Smarty::$_CHARSET
        */
        define('SMARTY_RESOURCE_CHAR_SET', SMARTY_MBSTRING ? 'UTF-8' : 'ISO-8859-1');
    }
    if (!defined('SMARTY_RESOURCE_DATE_FORMAT')) {
        /**
        * @deprecated in favor of Smarty::$_DATE_FORMAT
        */
        define('SMARTY_RESOURCE_DATE_FORMAT', '%b %e, %Y');
    }

    /**
    * register the class autoloader
    */
    if (!defined('SMARTY_SPL_AUTOLOAD')) {
        define('SMARTY_SPL_AUTOLOAD', 0);
    }

    if (SMARTY_SPL_AUTOLOAD && set_include_path(get_include_path() . PATH_SEPARATOR . SMARTY_SYSPLUGINS_DIR) !== false) {
        $registeredAutoLoadFunctions = spl_autoload_functions();
        if (!isset($registeredAutoLoadFunctions['spl_autoload'])) {
            spl_autoload_register();
        }
    } else {
        spl_autoload_register('smartyAutoload');
    }

    /**
    * Load always needed external class files
    */
    include_once SMARTY_SYSPLUGINS_DIR . 'smarty_internal_data.php';
    include_once SMARTY_SYSPLUGINS_DIR . 'smarty_resource.php';
    include_once SMARTY_SYSPLUGINS_DIR . 'smarty_internal_resource_file.php';
    include_once SMARTY_SYSPLUGINS_DIR . 'smarty_internal_content.php';

    /**
    * Dummy Template class for Smarty 3.1 BC
    *
    * @package Smarty
    */
    class Smarty_Internal_Template extends Smarty_Internal_Data {

    }

    /**
    * This is the main Smarty class
    * @package Smarty
    */
    class Smarty extends Smarty_Internal_Template {
        /*     * #@+
        * constant definitions
        */

        /**
        * smarty version
        */
        const SMARTY_VERSION = 'Smarty 3.2-DEV';

        /**
        * define variable scopes
        */
        const SCOPE_LOCAL = 0;
        const SCOPE_PARENT = 1;
        const SCOPE_ROOT = 2;
        const SCOPE_GLOBAL = 3;
        /**
        * define caching modes
        */
        const CACHING_OFF = 0;
        const CACHING_LIFETIME_CURRENT = 1;
        const CACHING_LIFETIME_SAVED = 2;
        const CACHING_NOCACHE_CODE = 3;     // create nocache code but no cache file
        /**
        * define compile check modes
        */
        const COMPILECHECK_OFF = 0;
        const COMPILECHECK_ON = 1;
        const COMPILECHECK_CACHEMISS = 2;
        /**
        * modes for handling of "<?php ... ?>" tags in templates.
        */
        const PHP_PASSTHRU = 0; //-> print tags as plain text
        const PHP_QUOTE = 1; //-> escape tags as entities
        const PHP_REMOVE = 2; //-> escape tags as entities
        const PHP_ALLOW = 3; //-> escape tags as entities
        /**
        * filter types
        */
        const FILTER_POST = 'post';
        const FILTER_PRE = 'pre';
        const FILTER_OUTPUT = 'output';
        const FILTER_VARIABLE = 'variable';
        /**
        * plugin types
        */
        const PLUGIN_FUNCTION = 'function';
        const PLUGIN_BLOCK = 'block';
        const PLUGIN_COMPILER = 'compiler';
        const PLUGIN_MODIFIER = 'modifier';
        const PLUGIN_MODIFIERCOMPILER = 'modifiercompiler';
        /**
        * unassigend template variable handling
        */
        const UNASSIGNED_IGNORE = 0;
        const UNASSIGNED_NOTICE = 1;
        const UNASSIGNED_EXCEPTION = 2;

        /*     * #@- */

        /**
        * assigned global tpl vars
        * @internal
        */
        public static $global_tpl_vars = null;

        /**
        * error handler returned by set_error_hanlder() in Smarty::muteExpectedErrors()
        * @internal
        */
        public static $_previous_error_handler = null;

        /**
        * contains directories outside of SMARTY_DIR that are to be muted by muteExpectedErrors()
        * @internal
        */
        public static $_muted_directories = array();

        /**
        * contains callbacks to invoke on events
        * @internal
        */
        public static $_callbacks = array();

        /**
        * Flag denoting if Multibyte String functions are available
        */
        public static $_MBSTRING = SMARTY_MBSTRING;

        /**
        * The character set to adhere to (e.g. "UTF-8")
        */
        public static $_CHARSET = SMARTY_RESOURCE_CHAR_SET;

        /**
        * The date format to be used internally
        * (accepts date() and strftime())
        */
        public static $_DATE_FORMAT = SMARTY_RESOURCE_DATE_FORMAT;

        /**
        * Flag denoting if PCRE should run in UTF-8 mode
        */
        public static $_UTF8_MODIFIER = 'u';

        /**
        * Flag denoting if operating system is windows
        */
        public static $_IS_WINDOWS = false;
        /** #@+
        * variables
        */

        /**
        * auto literal on delimiters with whitspace
        * @var boolean
        * @link http://www.smarty.net/docs/en/variable.auto.literal.tpl
        */
        public $auto_literal = true;

        /**
        * display error on not assigned variables
        * @var integer
        * @link <missing>
        * @uses UNASSIGNED_IGNORE as possible value
        * @uses UNASSIGNED_NOTICE as possible value
        * @uses UNASSIGNED_EXCEPTION as possible value
        */
        public $error_unassigned = self::UNASSIGNED_IGNORE;

        /**
        * look up relative filepaths in include_path
        * @var boolean
        * @link http://www.smarty.net/docs/en/variable.use.include.path.tpl
        */
        public $use_include_path = false;

        /**
        * enable source code tracback for runtime exceptions
        * @var boolean
        */
        public $enable_traceback = true;

        /**
        * template directory
        * @var array
        * @link http://www.smarty.net/docs/en/variable.template.dir.tpl
        */
        private $template_dir = array();

        /**
        * joined template directory string used in cache keys
        * @var string
        * @internal
        */
        public $joined_template_dir = null;

        /**
        * joined config directory string used in cache keys
        * @var string
        * @internal
        */
        public $joined_config_dir = null;

        /**
        * default template handler
        * @var callable
        * @link http://www.smarty.net/docs/en/variable.default.template.handler.func.tpl
        */
        public $default_template_handler_func = null;

        /**
        * default config handler
        * @var callable
        * @link http://www.smarty.net/docs/en/variable.default.config.handler.func.tpl
        */
        public $default_config_handler_func = null;

        /**
        * default plugin handler
        * @var callable
        * @link <missing>
        */
        public $default_plugin_handler_func = null;

        /**
        * default variable handler
        * @var callable
        * @link <missing>
        */
        public $default_variable_handler_func = null;

        /**
        * default config variable handler
        * @var callable
        * @link <missing>
        */
        public $default_config_variable_handler_func = null;

        /**
        * compile directory
        * @var string
        * @link http://www.smarty.net/docs/en/variable.compile.dir.tpl
        */
        private $compile_dir = null;

        /**
        * plugins directory
        * @var array
        * @link http://www.smarty.net/docs/en/variable.plugins.dir.tpl
        */
        private $plugins_dir = array();

        /**
        * cache directory
        * @var string
        * @link http://www.smarty.net/docs/en/variable.cache.dir.tpl
        */
        private $cache_dir = null;

        /**
        * config directory
        * @var array
        * @link http://www.smarty.net/docs/en/variable.fooobar.tpl
        */
        private $config_dir = array();

        /**
        * disable core plugins in {@link loadPlugin()}
        * @var boolean
        * @link <missing>
        */
        public $disable_core_plugins = false;

        /**
        * force template compiling?
        * @var boolean
        * @link http://www.smarty.net/docs/en/variable.force.compile.tpl
        */
        public $force_compile = false;

        /**
        * check template for modifications?
        * @var boolean
        * @link http://www.smarty.net/docs/en/variable.compile.check.tpl
        * @uses COMPILECHECK_OFF as possible value
        * @uses COMPILECHECK_ON as possible value
        * @uses COMPILECHECK_CACHEMISS as possible value
        */
        public $compile_check = self::COMPILECHECK_ON;

        /**
        * use sub dirs for compiled/cached files?
        * @var boolean
        * @link http://www.smarty.net/docs/en/variable.use.sub.dirs.tpl
        */
        public $use_sub_dirs = false;

        /**
        * allow ambiguous resources (that are made unique by the resource handler)
        * @var boolean
        */
        public $allow_ambiguous_resources = false;
        /*
        * caching enabled
        * @var integer
        * @link http://www.smarty.net/docs/en/variable.caching.tpl
        * @uses CACHING_OFF as possible value
        * @uses CACHING_LIFETIME_CURRENT as possible value
        * @uses CACHING_LIFETIME_SAVED as possible value
        */
        public $caching = self::CACHING_OFF;

        /**
        * merge compiled includes
        * @var boolean
        * @link http://www.smarty.net/docs/en/variable.merge.compiled.includes.tpl
        */
        public $merge_compiled_includes = false;

        /**
        * cache lifetime in seconds
        * @var integer
        * @link http://www.smarty.net/docs/en/variable.cache.lifetime.tpl
        */
        public $cache_lifetime = 3600;

        /**
        * force cache file creation
        * @var boolean
        * @link http://www.smarty.net/docs/en/variable.force.cache.tpl
        */
        public $force_cache = false;

        /**
        * Set this if you want different sets of cache files for the same
        * templates.
        * @var string
        * @link http://www.smarty.net/docs/en/variable.cache.id.tpl
        */
        public $cache_id = null;

        /**
        * Set this if you want different sets of compiled files for the same
        * templates.
        * @var string
        * @link http://www.smarty.net/docs/en/variable.compile.id.tpl
        */
        public $compile_id = null;

        /**
        * template left-delimiter
        * @var string
        * @link http://www.smarty.net/docs/en/variable.left.delimiter.tpl
        */
        public $left_delimiter = "{";

        /**
        * template right-delimiter
        * @var string
        * @link http://www.smarty.net/docs/en/variable.right.delimiter.tpl
        */
        public $right_delimiter = "}";
        /*     * #@+
        * security
        */

        /**
        * class name
        *
        * This should be instance of Smarty_Security.
        * @var string
        * @see Smarty_Security
        * @link <missing>
        */
        public $security_class = 'Smarty_Security';

        /**
        * implementation of security class
        * @var Smarty_Security
        * @see Smarty_Security
        * @link <missing>
        */
        public $security_policy = null;

        /**
        * controls handling of PHP-blocks
        * @var integer
        * @link http://www.smarty.net/docs/en/variable.php.handling.tpl
        * @uses PHP_PASSTHRU as possible value
        * @uses PHP_QUOTE as possible value
        * @uses PHP_REMOVE as possible value
        * @uses PHP_ALLOW as possible value
        */
        public $php_handling = self::PHP_PASSTHRU;

        /**
        * controls if the php template file resource is allowed
        * @var boolean
        * @link http://www.smarty.net/docs/en/api.variables.tpl#variable.allow.php.templates
        */
        public $allow_php_templates = false;

        /**
        * Should compiled-templates be prevented from being called directly?
        *
        * {@internal
        * Currently used by Smarty_Internal_Template only.
        * }}
        *
        * @var boolean
        * @link http://www.smarty.net/docs/en/variable.direct.access.security.tpl
        */
        public $direct_access_security = true;
        /*     * #@- */

        /**
        * debug mode
        *
        * Setting this to true enables the debug-console.
        *
        * @var boolean
        * @link http://www.smarty.net/docs/en/variable.debugging.tpl
        */
        public $debugging = false;

        /**
        * This determines if debugging is enable-able from the browser.
        * <ul>
        *  <li>NONE => no debugging control allowed</li>
        *  <li>URL => enable debugging when SMARTY_DEBUG is found in the URL.</li>
        * </ul>
        * @var string
        * @link http://www.smarty.net/docs/en/variable.debugging.ctrl.tpl
        */
        public $debugging_ctrl = 'NONE';

        /**
        * Name of debugging URL-param.
        * Only used when $debugging_ctrl is set to 'URL'.
        * The name of the URL-parameter that activates debugging.
        * @var string
        * @link http://www.smarty.net/docs/en/variable.smarty.debug.id.tpl
        */
        public $smarty_debug_id = 'SMARTY_DEBUG';

        /**
        * Path of debug template.
        * @var string
        * @link http://www.smarty.net/docs/en/variable.debug_template.tpl
        */
        public $debug_tpl = null;

        /**
        * When set, smarty uses this value as error_reporting-level.
        * @var integer
        * @link http://www.smarty.net/docs/en/variable.error.reporting.tpl
        */
        public $error_reporting = null;

        /**
        * Internal flag for getTags()
        * @var boolean
        * @internal
        */
        public $get_used_tags = false;

        /*     * #@+
        * config var settings
        */

        /**
        * Controls whether variables with the same name overwrite each other.
        * @var boolean
        * @link http://www.smarty.net/docs/en/variable.config.overwrite.tpl
        */
        public $config_overwrite = true;

        /**
        * Controls whether config values of on/true/yes and off/false/no get converted to boolean.
        * @var boolean
        * @link http://www.smarty.net/docs/en/variable.config.booleanize.tpl
        */
        public $config_booleanize = true;

        /**
        * Controls whether hidden config sections/vars are read from the file.
        * @var boolean
        * @link http://www.smarty.net/docs/en/variable.config.read.hidden.tpl
        */
        public $config_read_hidden = false;

        /*     * #@- */

        /*     * #@+
        * resource locking
        */

        /**
        * locking concurrent compiles
        * @var boolean
        * @link http://www.smarty.net/docs/en/variable.compile.locking.tpl
        */
        public $compile_locking = true;

        /**
        * Controls whether cache resources should emply locking mechanism
        * @var boolean
        * @link http://www.smarty.net/docs/en/variable.cache.locking.tpl
        */
        public $cache_locking = false;

        /**
        * seconds to wait for acquiring a lock before ignoring the write lock
        * @var float
        * @link http://www.smarty.net/docs/en/variable.locking.timeout.tpl
        */
        public $locking_timeout = 10;

        /*     * #@- */

        /**
        * global template functions
        * @var array
        * @internal
        */
        public $template_functions = array();

        /**
        * resource type used if none given
        * Must be an valid key of $registered_resources.
        * @var string
        * @link http://www.smarty.net/docs/en/variable.default.resource.type.tpl
        */
        public $default_resource_type = 'file';

        /**
        * caching type
        * Must be an element of $cache_resource_types.
        * @var string
        * @link http://www.smarty.net/docs/en/variable.caching.type.tpl
        */
        public $caching_type = 'file';

        /**
        * internal config properties
        * @var array
        * @internal
        */
        public $properties = array();

        /**
        * config type
        * @var string
        * @link http://www.smarty.net/docs/en/variable.default.config.type.tpl
        */
        public $default_config_type = 'file';

        /**
        * cached template objects
        * @var array
        * @internal
        */
        public static $template_objects = array();

        /**
        * check If-Modified-Since headers
        * @var boolean
        * @link http://www.smarty.net/docs/en/variable.cache.modified.check.tpl
        */
        public $cache_modified_check = false;

        /**
        * registered plugins
        * @var array
        * @internal
        */
        public $registered_plugins = array();

        /**
        * plugin search order
        * @var array
        * @link <missing>
        */
        public $plugin_search_order = array('function', 'block', 'compiler', 'class');

        /**
        * registered objects
        * @var array
        * @internal
        */
        public $registered_objects = array();

        /**
        * registered classes
        * @var array
        * @internal
        */
        public $registered_classes = array();

        /**
        * registered filters
        * @var array
        * @internal
        */
        public $registered_filters = array();

        /**
        * registered resources
        * @var array
        * @internal
        */
        public $registered_resources = array();

        /**
        * resource handler cache
        * @var array
        * @internal
        */
        public $_resource_handlers = array();

        /**
        * registered cache resources
        * @var array
        * @internal
        */
        public $registered_cache_resources = array();

        /**
        * cache resource handler cache
        * @var array
        * @internal
        */
        public $_cacheresource_handlers = array();

        /**
        * autoload filter
        * @var array
        * @link http://www.smarty.net/docs/en/variable.autoload.filters.tpl
        */
        public $autoload_filters = array();

        /**
        * default modifier
        * @var array
        * @link http://www.smarty.net/docs/en/variable.default.modifiers.tpl
        */
        public $default_modifiers = array();

        /**
        * autoescape variable output
        * @var boolean
        * @link http://www.smarty.net/docs/en/variable.escape.html.tpl
        */
        public $escape_html = false;

        /**
        * global internal smarty vars
        * @var array
        */
        public static $_smarty_vars = array();

        /**
        * start time for execution time calculation
        * @var integer
        * @internal
        */
        public $start_time = 0;

        /**
        * default file permissions (octal)
        * @var integer
        * @internal
        */
        public $_file_perms = 0644;

        /**
        * default dir permissions (octal)
        * @var integer
        * @internal
        */
        public $_dir_perms = 0771;

        /**
        * block tag hierarchy
        * @var array
        * @internal
        */
        public $_tag_stack = array();

        /**
        * required by the compiler for BC
        * @var string
        * @internal
        */
        public $_current_file = null;

        /**
        * internal flag to enable parser debugging
        * @var boolean
        * @internal
        */
        public $_parserdebug = false;

        /*     * #@- */

        /*     * #@+
        * template properties
        */

        /**
        * flag if thgis instance is a template object
        * @var boolean
        * @internal
        */
        public $is_template = false;

        /**
        * individually cached subtemplates
        * @var array
        */
        public $cached_subtemplates = array();

        /**
        * Template resource
        * @var string
        * @internal
        */
        public $template_resource = null;

        /**
        * flag if template does contain nocache code sections
        * @var boolean
        * @internal
        */
        public $has_nocache_code = false;

        /**
        * flag set when nocache code sections are executed
        * @var boolean
        * @internal
        */
        public $is_nocache = false;

        /**
        * root template of hierarchy
        *
        * @var Smarty_Internal_Template
        */
        public $rootTemplate = null;

        /**
        * forward pointer to inheritance template parent
        *
        * @var Smarty_Internal_Template
        */
        public $inheritanceParentTemplate = null;

        /**
        * flag if inheritance template is child
        *
        * @var bool
        */
        public $is_child = false;

        /**
        * {block} tags of this template
        *
        * @var array
        * @internal
        */
        public $block = array();

        /**
        * variable filters
        * @var array
        * @internal
        */
        public $variable_filters = array();

        /**
        * optional log of tag/attributes
        * @var array
        * @internal
        */
        public $used_tags = array();

        /**
        * internal flag to allow relative path in child template blocks
        * @var boolean
        * @internal
        */
        public $allow_relative_path = false;

        /**
        * internal capture runtime stack
        * @var array
        * @internal
        */
        public $_capture_stack = array(0 => array());

        /**
        * template template call stack for traceback
        * @var array
        * @internal
        */
        public $trace_call_stack = array();

        /**
        * Pointer to subtemplate with template functions
        * @var object Smarty_Internal_Content
        * @internal
        */
        public $template_function_chain = null;

        /**
        * $compiletime_options
        * value is computed of the compiletime options relevant for config files
        *      $config_read_hidden
        *      $config_booleanize
        *      $config_overwrite
        *
        * @var int
        */
        public $compiletime_options = 0;
        /*     * #@- */

        /**
        * Initialize new Smarty object
        *
        */
        public function __construct() {
            // create variabale container
            parent::__construct();
            if (!isset(self::$global_tpl_vars)) {
                self::$global_tpl_vars = new Smarty_Variable_Container();
            }
            // PHP options
            if (is_callable('mb_internal_encoding')) {
                mb_internal_encoding(Smarty::$_CHARSET);
            }
            $this->start_time = microtime(true);
            // set default dirs
            $this->setTemplateDir('.' . DS . 'templates' . DS)
            ->setCompileDir('.' . DS . 'templates_c' . DS)
            ->setPluginsDir(SMARTY_PLUGINS_DIR)
            ->setCacheDir('.' . DS . 'cache' . DS)
            ->setConfigDir('.' . DS . 'configs' . DS);

            $this->debug_tpl = 'file:' . dirname(__FILE__) . '/debug.tpl';
            if (isset($_SERVER['SCRIPT_NAME'])) {
                $this->assignGlobal('SCRIPT_NAME', $_SERVER['SCRIPT_NAME']);
            }
        }

        /**
        * Class destructor
        */
        public function __destruct() {
            if ($this->is_template && $this->cache_locking && isset($this->cached) && $this->cached->is_locked) {
                $this->cached->handler->releaseLock($this, $this->cached);
            }
        }

        /**
        * <<magic>> clone method
        */
        public function __clone() {
            // cloned object is template
            $this->is_template = true;
        }

        /**
        * <<magic>> Generic getter.
        * Get Smarty or Template property
        *
        * @param string $property_name property name
        */
        public function __get($property_name) {
            static $getter = array(
                'template_dir' => 'getTemplateDir',
                'config_dir' => 'getConfigDir',
                'plugins_dir' => 'getPluginsDir',
                'compile_dir' => 'getCompileDir',
                'cache_dir' => 'getCacheDir',
            );
            if ($this->is_template) {
                switch ($property_name) {
                    case 'source':
                        if (empty($this->template_resource)) {
                            throw new SmartyException("Unable to parse resource name \"{$this->template_resource}\"");
                        }
                        $this->source = Smarty_Resource::source($this);
                        // cache template object under a unique ID
                        // do not cache eval resources
                        if ($this->source->type != 'eval') {
                            if ($this->allow_ambiguous_resources) {
                                $_templateId = $this->source->unique_resource . $this->cache_id . $this->compile_id;
                            } else {
                                $_templateId = $this->joined_template_dir . '#' . $this->template_resource . $this->cache_id . $this->compile_id;
                            }

                            if (isset($_templateId[150])) {
                                $_templateId = sha1($_templateId);
                            }
                            self::$template_objects[$_templateId] = $this;
                        }
                        return $this->source;

                    case 'compiled':
                        // check runtime cache
                        $_cache_key = $this->source->unique_resource . '#' . $this->compile_id;
                        if (isset(Smarty_Compiled::$compileds[$_cache_key])) {
                            $this->compiled = Smarty_Compiled::$compileds[$_cache_key];
                        } else {
                            $this->compiled = Smarty_Compiled::$compileds[$_cache_key] = new Smarty_Compiled($this);
                        }
                        return $this->compiled;

                    case 'cached':
                        $this->cached = new Smarty_Template_Cached($this);
                        return $this->cached;

                    case 'compiler':
                        $this->loadPlugin($this->source->compiler_class);
                        $this->compiler = new $this->source->compiler_class($this->source->template_lexer_class, $this->source->template_parser_class, $this);
                        return $this->compiler;

                    case 'mustCompile':
                        return $this->mustCompile = (!$this->source->uncompiled && ($this->force_compile || $this->source->recompiled || $this->compiled->timestamp === false ||
                            ($this->compile_check && $this->compiled->timestamp < $this->source->timestamp)));
                }
            }
            switch ($property_name) {
                case 'template_dir':
                case 'config_dir':
                case 'plugins_dir':
                case 'compile_dir':
                case 'cache_dir':
                    return $this->{$getter[$property_name]}($value);
                case 'smarty':
                    return $this;
            }
            throw new SmartyException("Undefined property '$property_name'.");
        }

        /**
        * <<magic>> Generic setter.
        * Set Smarty or Template property
        *
        * @param string $property_name property name
        * @param mixed  $value         value
        */
        public function __set($property_name, $value) {
            static $setter = array(
                'template_dir' => 'setTemplateDir',
                'config_dir' => 'setConfigDir',
                'plugins_dir' => 'setPluginsDir',
                'compile_dir' => 'setCompileDir',
                'cache_dir' => 'setCacheDir',
            );
            if ($this->is_template) {
                switch ($property_name) {
                    case 'source':
                    case 'compiled':
                    case 'cached':
                    case 'compiler':
                    case 'mustCompile':
                    $this->$property_name = $value;
                    return;
                }
            }
            switch ($property_name) {
                case 'template_dir':
                case 'config_dir':
                case 'plugins_dir':
                case 'compile_dir':
                case 'cache_dir':
                    $this->{$setter[$property_name]}($value);
                    return;
            }
            throw new SmartyException("Undefined property '$property_name'.");
        }

        /**
        * fetches a rendered Smarty template
        *
        * @param string $template          the resource handle of the template file or template object
        * @param mixed  $cache_id          cache id to be used with this template
        * @param mixed  $compile_id        compile id to be used with this template
        * @param object $parent            next higher level of Smarty variables
        * @param bool   $display           true: display, false: fetch
        * @param bool   $no_output_filter  if true do not run output filter
        * @param bool   $merge_tpl_vars    if true parent template variables merged in to local scope
        * @return string rendered template output
        */
        public function fetch($template = null, $cache_id = null, $compile_id = null, $parent = null, $display = false, $no_output_filter = false, $merge_tpl_vars = true) {
            if ($template === null && $this->is_template) {
                $template = $this;
            }
            if (!empty($cache_id) && is_object($cache_id)) {
                $parent = $cache_id;
                $cache_id = null;
            }
            if ($parent === null && (!$this->is_template || is_string($template))) {
                $parent = $this;
            }
            // create template object if necessary
            $_template = (is_object($template)) ? $template : $this->createTemplate($template, $cache_id, $compile_id, $parent, false);
            // merge all variable from scopes into template
            if ($merge_tpl_vars && $_template->parent != null) {
                // save local variables
                $saved_tpl_vars = $_template->tpl_vars;
                $_template->tpl_vars = clone $_template->parent->tpl_vars;
                // merge local vars
                foreach ($saved_tpl_vars as $key => $var) {
                    $_template->tpl_vars->$key = $var;
                }
            }

            // dummy local smarty variable
            if (!isset($_template->tpl_vars->smarty)) {
                $_template->tpl_vars->smarty = null;
            }
            if (isset($this->error_reporting)) {
                $_smarty_old_error_level = error_reporting($this->error_reporting);
            }
            // check URL debugging control
            if (!$this->debugging && $this->debugging_ctrl == 'URL') {
                if (isset($_SERVER['QUERY_STRING'])) {
                    $_query_string = $_SERVER['QUERY_STRING'];
                } else {
                    $_query_string = '';
                }
                if (false !== strpos($_query_string, $this->smarty_debug_id)) {
                    if (false !== strpos($_query_string, $this->smarty_debug_id . '=on')) {
                        // enable debugging for this browser session
                        setcookie('SMARTY_DEBUG', true);
                        $this->debugging = true;
                    } elseif (false !== strpos($_query_string, $this->smarty_debug_id . '=off')) {
                        // disable debugging for this browser session
                        setcookie('SMARTY_DEBUG', false);
                        $this->debugging = false;
                    } else {
                        // enable debugging for this page
                        $this->debugging = true;
                    }
                } else {
                    if (isset($_COOKIE['SMARTY_DEBUG'])) {
                        $this->debugging = true;
                    }
                }
            }
            // get rendered template
            // disable caching for evaluated code
            if ($_template->source->recompiled) {
                $_template->caching = false;
            }
            // checks if template exists
            if (!$_template->source->exists) {
                $msg = "Unable to load template {$_template->source->type} '{$_template->source->name}'";
                if ($_template->parent->is_template) {
                    throw new SmartyRunTimeException($msg, $_template->parent);
                } else {
                    throw new SmartyException($msg);
                }
            }
            if ($_template->caching == Smarty::CACHING_LIFETIME_CURRENT || $_template->caching == Smarty::CACHING_LIFETIME_SAVED) {
                $browser_cache_valid = false;
                if ($display && $this->cache_modified_check && $_template->cached->valid && !$_template->has_nocache_code) {
                    $_last_modified_date = @substr($_SERVER['HTTP_IF_MODIFIED_SINCE'], 0, strpos($_SERVER['HTTP_IF_MODIFIED_SINCE'], 'GMT') + 3);
                    if ($_last_modified_date !== false && $_template->cached->timestamp <= ($_last_modified_timestamp = strtotime($_last_modified_date)) &&
                        $this->checkSubtemplateCache($_template, $_last_modified_timestamp)) {
                        $browser_cache_valid = true;
                        switch (PHP_SAPI) {
                            case 'cgi':         // php-cgi < 5.3
                            case 'cgi-fcgi':    // php-cgi >= 5.3
                            case 'fpm-fcgi':    // php-fpm >= 5.3.3
                                header('Status: 304 Not Modified');
                                break;

                            case 'cli':
                                if (/* ^phpunit */!empty($_SERVER['SMARTY_PHPUNIT_DISABLE_HEADERS'])/* phpunit$ */) {
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
                    $_output = $_template->cached->getRenderedTemplate($this, $_template, $no_output_filter);
                }
            } else {
                $_output = $_template->compiled->getRenderedTemplate($this, $_template);
            }
            if ((!$this->caching || $_template->has_nocache_code || $_template->source->recompiled) && !$no_output_filter && (isset($this->autoload_filters['output']) || isset($this->registered_filters['output']))) {
                $_output = Smarty_Internal_Filter_Handler::runFilter('output', $_output, $_template);
            }
            if (isset($this->error_reporting)) {
                error_reporting($_smarty_old_error_level);
            }
            // display or fetch
            if ($display) {
                if ($this->caching && $this->cache_modified_check) {
                    if (!$browser_cache_valid) {
                        switch (PHP_SAPI) {
                            case 'cli':
                                if (/* ^phpunit */!empty($_SERVER['SMARTY_PHPUNIT_DISABLE_HEADERS'])/* phpunit$ */) {
                                    $_SERVER['SMARTY_PHPUNIT_HEADERS'][] = 'Last-Modified: ' . gmdate('D, d M Y H:i:s', $_template->cached->timestamp) . ' GMT';
                                }
                                break;

                            default:
                                header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
                                break;
                        }
                        echo $_output;
                    }
                } else {
                    echo $_output;
                }
                // debug output
                if ($this->debugging) {
                    Smarty_Internal_Debug::display_debug($this);
                }
                if ($merge_tpl_vars && $_template->parent != null) {
                    // restore local variables
                    $_template->tpl_vars = $saved_tpl_vars;
                }
                return;
            }
            if ($merge_tpl_vars && $_template->parent != null) {
                // restore local variables
                $_template->tpl_vars = $saved_tpl_vars;
            }
            if ($display) {
                return;
            } else {
                // return output on fetch
                return $_output;
            }
        }

        /**
        * displays a Smarty template
        *
        * @param string $template   the resource handle of the template file or template object
        * @param mixed  $cache_id   cache id to be used with this template
        * @param mixed  $compile_id compile id to be used with this template
        * @param object $parent     next higher level of Smarty variables
        */
        public function display($template = null, $cache_id = null, $compile_id = null, $parent = null) {
            // display template
            $this->fetch($template, $cache_id, $compile_id, $parent, true);
        }

        /**
        * test if cache is valid
        *
        * @param string|object $template   the resource handle of the template file or template object
        * @param mixed         $cache_id   cache id to be used with this template
        * @param mixed         $compile_id compile id to be used with this template
        * @param object        $parent     next higher level of Smarty variables
        * @return boolean cache status
        */
        public function isCached($template = null, $cache_id = null, $compile_id = null, $parent = null) {
             if (is_object($template) && $template->is_template) {
                return $template->cached->valid;
             }

            if ($template === null && $this->is_template) {
                return $this->cached->valid;
            }
            if (!(is_object($template) && $this->is_template)) {
                if ($parent === null) {
                    $parent = $this;
                }
                $template = $this->createTemplate($template, $cache_id, $compile_id, $parent, false);
            }
            // return cache status of template
            return $template->cached->valid;
        }

        /**
        * Check timestamp of browser cache against timestamp of individually cached subtemplates
        *
        * @param Smarty_Internal_Template $_template template object
        * @param integer $_last_modified_timestamp browser cache timestamp
        * @returns bool true if browser cahe is valig
        */
        private function checkSubtemplateCache($_template, $_last_modified_timestamp) {
            $subtpl = reset($_template->cached_subtemplates);
            while ($subtpl) {
                $tpl = new $this->smarty->template_class($subtpl[0], $this->smarty, $_template, $subtpl[1], $subtpl[2], $subtpl[3], $subtpl[4], true);
                if (!$tpl->cached->valid || $tpl->has_nocache_code || $tpl->cached->timestamp > $_last_modified_timestamp ||
                    !$this->checkSubtemplateCache($tpl, $_last_modified_timestamp)) {
                    // browser cache invalid
                    return false;
                }
                $subtpl = next($_template->cached_subtemplates);
            }
            // browser cache valid
            return true;
        }

        /**
        * creates a data object
        *
        * @param object $parent next higher level of Smarty variables
        * @returns Smarty_Data data object
        */
        public function createData($parent = null) {
            return new Smarty_Data($parent, $this);
        }

        /**
        * Registers plugin to be used in templates
        *
        * @param string   $type       plugin type
        * @param string   $tag        name of template tag
        * @param callback $callback   PHP callback to register
        * @param boolean  $cacheable  if true (default) this fuction is cachable
        * @param array    $cache_attr caching attributes if any
        * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or Smarty_Internal_Template) instance for chaining
        * @throws SmartyException when the plugin tag is invalid
        */
        public function registerPlugin($type, $tag, $callback, $cacheable = true, $cache_attr = null) {
            if (isset($this->registered_plugins[$type][$tag])) {
                throw new SmartyException("registerPlugin(): Plugin tag \"{$tag}\" already registered");
            } elseif (!is_callable($callback)) {
                throw new SmartyException("registerPlugin(): Plugin \"{$tag}\" not callable");
            } else {
                if (is_object($callback)) {
                    $callback = array($callback, '__invoke');
                }
                $this->registered_plugins[$type][$tag] = array($callback, (bool) $cacheable, (array) $cache_attr);
            }
            return $this;
        }

        /**
        * Unregister Plugin
        *
        * @param string $type of plugin
        * @param string $tag name of plugin
        * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or Smarty_Internal_Template) instance for chaining
        */
        public function unregisterPlugin($type, $tag) {
            if (isset($this->registered_plugins[$type][$tag])) {
                unset($this->registered_plugins[$type][$tag]);
            }
            return $this;
        }

        /**
        * Registers a resource to fetch a template
        *
        * @param string $type name of resource type
        * @param Smarty_Resource|array $callback or instance of Smarty_Resource, or array of callbacks to handle resource (deprecated)
        * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or Smarty_Internal_Template) instance for chaining
        */
        public function registerResource($type, $callback) {
            $this->registered_resources[$type] = $callback instanceof Smarty_Resource ? $callback : array($callback, false);
            return $this;
        }

        /**
        * Unregisters a resource
        *
        * @param string $type name of resource type
        * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or Smarty_Internal_Template) instance for chaining
        */
        public function unregisterResource($type) {
            if (isset($this->registered_resources[$type])) {
                unset($this->registered_resources[$type]);
            }
            return $this;
        }

        /**
        * Registers a cache resource to cache a template's output
        *
        * @param string               $type     name of cache resource type
        * @param Smarty_CacheResource $callback instance of Smarty_CacheResource to handle output caching
        * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or Smarty_Internal_Template) instance for chaining
        */
        public function registerCacheResource($type, Smarty_CacheResource $callback) {
            $this->registered_cache_resources[$type] = $callback;
            return $this;
        }

        /**
        * Unregisters a cache resource
        *
        * @param string $type name of cache resource type
        * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or Smarty_Internal_Template) instance for chaining
        */
        public function unregisterCacheResource($type) {
            if (isset($this->registered_cache_resources[$type])) {
                unset($this->registered_cache_resources[$type]);
            }
            return $this;
        }

        /**
        * Registers static classes to be used in templates
        *
        * @param string $class name of template class
        * @param string $class_impl the referenced PHP class to register
        * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or Smarty_Internal_Template) instance for chaining
        * @throws SmartyException if $class_impl does not refer to an existing class
        */
        public function registerClass($class_name, $class_impl) {
            // test if exists
            if (!class_exists($class_impl)) {
                throw new SmartyException("registerClass(): Undefined class \"{$class_impl}\"");
            }
            // register the class
            $this->registered_classes[$class_name] = $class_impl;
            return $this;
        }

        /**
        * Registers a default plugin handler
        *
        * @param callable $callback class/method name
        * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or Smarty_Internal_Template) instance for chaining
        * @throws SmartyException if $callback is not callable
        */
        public function registerDefaultPluginHandler($callback) {
            if (is_callable($callback)) {
                $this->default_plugin_handler_func = $callback;
            } else {
                throw new SmartyException("registerDefaultPluginHandler(): Invalid callback");
            }
            return $this;
        }

        /**
        * Registers a default template handler
        *
        * @param callable $callback class/method name
        * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or Smarty_Internal_Template) instance for chaining
        * @throws SmartyException if $callback is not callable
        */
        public function registerDefaultTemplateHandler($callback) {
            if (is_callable($callback)) {
                $this->default_template_handler_func = $callback;
            } else {
                throw new SmartyException("registerDefaultTemplateHandler(): Invalid callback");
            }
            return $this;
        }

        /**
        * Registers a default variable handler
        *
        * @param callable $callback class/method name
        * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or Smarty_Internal_Template) instance for chaining
        * @throws SmartyException if $callback is not callable
        */
        public function registerDefaultVariableHandler($callback) {
            if (is_callable($callback)) {
                $this->default_variable_handler_func = $callback;
            } else {
                throw new SmartyException("registerDefaultVariableHandler(): Invalid callback");
            }
            return $this;
        }

        /**
        * Registers a default config variable handler
        *
        * @param callable $callback class/method name
        * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or Smarty_Internal_Template) instance for chaining
        * @throws SmartyException if $callback is not callable
        */
        public function registerDefaultConfigVariableHandler($callback) {
            if (is_callable($callback)) {
                $this->default_config_variable_handler_func = $callback;
            } else {
                throw new SmartyException("registerDefaultConfigVariableHandler(): Invalid callback");
            }
            return $this;
        }

        /**
        * Registers a default config handler
        *
        * @param callable $callback class/method name
        * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or Smarty_Internal_Template) instance for chaining
        * @throws SmartyException if $callback is not callable
        */
        public function registerDefaultConfigHandler($callback) {
            if (is_callable($callback)) {
                $this->default_config_handler_func = $callback;
            } else {
                throw new SmartyException("registerDefaultConfigHandler(): Invalid callback");
            }
            return $this;
        }

        /**
        * Registers a filter function
        *
        * @param string $type filter type
        * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or Smarty_Internal_Template) instance for chaining
        * @param callback $callback
        */
        public function registerFilter($type, $callback) {
            if (!in_array($type, array('pre', 'post', 'output', 'variable'))) {
                throw new SmartyException("registerFilter(): Invalid filter type \"{$type}\"");
            }
            if (is_callable($callback)) {
                if ($callback instanceof Closure) {
                    $this->registered_filters[$type][] = $callback;
                } else {
                    if (is_object($callback)) {
                        $callback = array($callback, '__invoke');
                    }
                    $this->registered_filters[$type][$this->_get_filter_name($callback)] = $callback;
                }
            } else {
                throw new SmartyException("registerFilter(): Invalid callback");
            }
            return $this;
        }

        /**
        * Unregisters a filter function
        *
        * @param string $type filter type
        * @param callback $callback
        * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or Smarty_Internal_Template) instance for chaining
        */
        public function unregisterFilter($type, $callback) {
            if (!isset($this->registered_filters[$type])) {
                return $this;
            }
            if ($callback instanceof Closure) {
                foreach ($this->registered_filters[$type] as $key => $_callback) {
                    if ($callback === $_callback) {
                        unset($this->registered_filters[$type][$key]);
                        return $this;
                    }
                }
            } else {
                if (is_object($callback)) {
                    $callback = array($callback, '__invoke');
                }
                $name = $this->_get_filter_name($callback);
                if (isset($this->registered_filters[$type][$name])) {
                    unset($this->registered_filters[$type][$name]);
                }
            }
            return $this;
        }

        /**
        * Return internal filter name
        *
        * @param callback $function_name
        */
        public function _get_filter_name($function_name) {
            if (is_array($function_name)) {
                $_class_name = (is_object($function_name[0]) ?
                    get_class($function_name[0]) : $function_name[0]);
                return $_class_name . '_' . $function_name[1];
            } else {
                return $function_name;
            }
        }

        /**
        * load a filter of specified type and name
        *
        * @param string $type filter type
        * @param string $name filter name
        * @return bool
        */
        public function loadFilter($type, $name) {
            if (!in_array($type, array('pre', 'post', 'output', 'variable'))) {
                throw new SmartyException("loadFilter(): Invalid filter type \"{$type}\"");
            }
            $_plugin = "smarty_{$type}filter_{$name}";
            $_filter_name = $_plugin;
            if ($this->loadPlugin($_plugin)) {
                if (class_exists($_plugin, false)) {
                    $_plugin = array($_plugin, 'execute');
                }
                if (is_callable($_plugin)) {
                    $this->registered_filters[$type][$_filter_name] = $_plugin;
                    return true;
                }
            }
            throw new SmartyException("loadFilter(): {$type}filter \"{$name}\" not callable");
            return false;
        }

        /**
        * unload a filter of specified type and name
        *
        * @param string $type filter type
        * @param string $name filter name
        * @return Smarty_Internal_Templatebase current Smarty_Internal_Templatebase (or Smarty or Smarty_Internal_Template) instance for chaining
        */
        public function unloadFilter($type, $name) {
            $_filter_name = "smarty_{$type}filter_{$name}";
            if (isset($this->registered_filters[$type][$_filter_name])) {
                unset($this->registered_filters[$type][$_filter_name]);
            }
            return $this;
        }

        /**
        * Check if a template resource exists
        *
        * @param string $resource_name template name
        * @return boolean status
        */
        public function templateExists($resource_name) {
            $source = Smarty_Resource::source(null, $this, $resource_name);
            return $source->exists;
        }

        /**
        * Returns a single or all global  variables
        *
        * @param object $smarty
        * @param string $varname variable name or null
        * @return string variable value or or array of variables
        */
        public function getGlobal($varname = null) {
            if (isset($varname)) {
                if (isset(self::$global_tpl_vars->{$varname}['value'])) {
                    return self::$global_tpl_vars->{$varname}['value'];
                } else {
                    return '';
                }
            } else {
                $_result = array();
                foreach (self::$global_tpl_vars AS $key => $var) {
                    if (strpos($key, '___') !== 0) {
                        $_result[$key] = $var['value'];
                    }
                }
                return $_result;
            }
        }

        /**
        * Empty cache folder
        *
        * @param integer $exp_time expiration time
        * @param string  $type     resource type
        * @return integer number of cache files deleted
        */
        function clearAllCache($exp_time = null, $type = null) {
            // load cache resource and call clearAll
            $_cache_resource = Smarty_CacheResource::load($this, $type);
            Smarty_CacheResource::invalidLoadedCache($this);
            return $_cache_resource->clearAll($this, $exp_time);
        }

        /**
        * Empty cache for a specific template
        *
        * @param string  $template_name template name
        * @param string  $cache_id      cache id
        * @param string  $compile_id    compile id
        * @param integer $exp_time      expiration time
        * @param string  $type          resource type
        * @return integer number of cache files deleted
        */
        public function clearCache($template_name = null, $cache_id = null, $compile_id = null, $exp_time = null, $type = null) {
            // load cache resource and call clear
            $_cache_resource = Smarty_CacheResource::load($this, $type);
            Smarty_CacheResource::invalidLoadedCache($this);
            return $_cache_resource->clear($this, $template_name, $cache_id, $compile_id, $exp_time);
        }

        /**
        * Delete compiled template file
        *
        * @param string $resource_name template name
        * @param string $compile_id compile id
        * @param integer $exp_time expiration time
        * @return integer number of template files deleted
        */
        public function clearCompiledTemplate($resource_name = null, $compile_id = null, $exp_time = null) {
            return Smarty_Compiled::clearCompiledTemplate($resource_name, $compile_id, $exp_time, $this);
        }

        /**
        * Loads security class and enables security
        *
        * @param string|Smarty_Security $security_class if a string is used, it must be class-name
        * @return Smarty current Smarty instance for chaining
        * @throws SmartyException when an invalid class name is provided
        */
        public function enableSecurity($security_class = null) {
            if ($security_class instanceof Smarty_Security) {
                $this->security_policy = $security_class;
                return $this;
            } elseif (is_object($security_class)) {
                throw new SmartyException("enableSecurity(): Class '" . get_class($security_class) . "' must extend Smarty_Security.");
            }
            if ($security_class == null) {
                $security_class = $this->security_class;
            }
            if (!class_exists($security_class)) {
                throw new SmartyException("enableSecurity(): Security class '$security_class' is not defined");
            } elseif ($security_class !== 'Smarty_Security' && !is_subclass_of($security_class, 'Smarty_Security')) {
                throw new SmartyException("enableSecurity(): Class '$security_class' must extend Smarty_Security.");
            } else {
                $this->security_policy = new $security_class($this);
            }

            return $this;
        }

        /**
        * Disable security
        * @return Smarty current Smarty instance for chaining
        */
        public function disableSecurity() {
            $this->security_policy = null;

            return $this;
        }

        /**
        * Set template directory
        *
        * @param string|array $template_dir directory(s) of template sources
        * @return Smarty current Smarty instance for chaining
        */
        public function setTemplateDir($template_dir) {
            $this->template_dir = array();
            foreach ((array) $template_dir as $k => $v) {
                $this->template_dir[$k] = rtrim($v, '/\\') . DS;
            }

            $this->joined_template_dir = join(DIRECTORY_SEPARATOR, $this->template_dir);
            return $this;
        }

        /**
        * Add template directory(s)
        *
        * @param string|array $template_dir directory(s) of template sources
        * @param string       $key          of the array element to assign the template dir to
        * @return Smarty current Smarty instance for chaining
        */
        public function addTemplateDir($template_dir, $key=null) {
            // make sure we're dealing with an array
            $this->template_dir = (array) $this->template_dir;

            if (is_array($template_dir)) {
                foreach ($template_dir as $k => $v) {
                    if (is_int($k)) {
                        // indexes are not merged but appended
                        $this->template_dir[] = rtrim($v, '/\\') . DS;
                    } else {
                        // string indexes are overridden
                        $this->template_dir[$k] = rtrim($v, '/\\') . DS;
                    }
                }
            } elseif ($key !== null) {
                // override directory at specified index
                $this->template_dir[$key] = rtrim($template_dir, '/\\') . DS;
            } else {
                // append new directory
                $this->template_dir[] = rtrim($template_dir, '/\\') . DS;
            }
            $this->joined_template_dir = join(DIRECTORY_SEPARATOR, $this->template_dir);
            return $this;
        }

        /**
        * Get template directories
        *
        * @param mixed index of directory to get, null to get all
        * @return array|string list of template directories, or directory of $index
        */
        public function getTemplateDir($index=null) {
            if ($index !== null) {
                return isset($this->template_dir[$index]) ? $this->template_dir[$index] : null;
            }

            return (array) $this->template_dir;
        }

        /**
        * Set config directory
        *
        * @param string|array $template_dir directory(s) of configuration sources
        * @return Smarty current Smarty instance for chaining
        */
        public function setConfigDir($config_dir) {
            $this->config_dir = array();
            foreach ((array) $config_dir as $k => $v) {
                $this->config_dir[$k] = rtrim($v, '/\\') . DS;
            }

            $this->joined_config_dir = join(DIRECTORY_SEPARATOR, $this->config_dir);
            return $this;
        }

        /**
        * Add config directory(s)
        *
        * @param string|array $config_dir directory(s) of config sources
        * @param string key of the array element to assign the config dir to
        * @return Smarty current Smarty instance for chaining
        */
        public function addConfigDir($config_dir, $key=null) {
            // make sure we're dealing with an array
            $this->config_dir = (array) $this->config_dir;

            if (is_array($config_dir)) {
                foreach ($config_dir as $k => $v) {
                    if (is_int($k)) {
                        // indexes are not merged but appended
                        $this->config_dir[] = rtrim($v, '/\\') . DS;
                    } else {
                        // string indexes are overridden
                        $this->config_dir[$k] = rtrim($v, '/\\') . DS;
                    }
                }
            } elseif ($key !== null) {
                // override directory at specified index
                $this->config_dir[$key] = rtrim($config_dir, '/\\') . DS;
            } else {
                // append new directory
                $this->config_dir[] = rtrim($config_dir, '/\\') . DS;
            }

            $this->joined_config_dir = join(DIRECTORY_SEPARATOR, $this->config_dir);
            return $this;
        }

        /**
        * Get config directory
        *
        * @param mixed index of directory to get, null to get all
        * @return array|string configuration directory
        */
        public function getConfigDir($index=null) {
            if ($index !== null) {
                return isset($this->config_dir[$index]) ? $this->config_dir[$index] : null;
            }

            return (array) $this->config_dir;
        }

        /**
        * Set plugins directory
        *
        * Adds {@link SMARTY_PLUGINS_DIR} if not specified
        * @param string|array $plugins_dir directory(s) of plugins
        * @return Smarty current Smarty instance for chaining
        */
        public function setPluginsDir($plugins_dir) {
            $this->plugins_dir = array();
            foreach ((array) $plugins_dir as $k => $v) {
                $this->plugins_dir[$k] = rtrim($v, '/\\') . DS;
            }

            return $this;
        }

        /**
        * Adds directory of plugin files
        *
        * @param object $smarty
        * @param string $ |array $ plugins folder
        * @return Smarty current Smarty instance for chaining
        */
        public function addPluginsDir($plugins_dir) {
            // make sure we're dealing with an array
            $this->plugins_dir = (array) $this->plugins_dir;

            if (is_array($plugins_dir)) {
                foreach ($plugins_dir as $k => $v) {
                    if (is_int($k)) {
                        // indexes are not merged but appended
                        $this->plugins_dir[] = rtrim($v, '/\\') . DS;
                    } else {
                        // string indexes are overridden
                        $this->plugins_dir[$k] = rtrim($v, '/\\') . DS;
                    }
                }
            } else {
                // append new directory
                $this->plugins_dir[] = rtrim($plugins_dir, '/\\') . DS;
            }

            $this->plugins_dir = array_unique($this->plugins_dir);
            return $this;
        }

        /**
        * Get plugin directories
        *
        * @return array list of plugin directories
        */
        public function getPluginsDir() {
            return (array) $this->plugins_dir;
        }

        /**
        * Set compile directory
        *
        * @param string $compile_dir directory to store compiled templates in
        * @return Smarty current Smarty instance for chaining
        */
        public function setCompileDir($compile_dir) {
            $this->compile_dir = rtrim($compile_dir, '/\\') . DS;
            if (!isset(Smarty::$_muted_directories[$this->compile_dir])) {
                Smarty::$_muted_directories[$this->compile_dir] = null;
            }
            return $this;
        }

        /**
        * Get compiled directory
        *
        * @return string path to compiled templates
        */
        public function getCompileDir() {
            return $this->compile_dir;
        }

        /**
        * Set cache directory
        *
        * @param string $cache_dir directory to store cached templates in
        * @return Smarty current Smarty instance for chaining
        */
        public function setCacheDir($cache_dir) {
            $this->cache_dir = rtrim($cache_dir, '/\\') . DS;
            if (!isset(Smarty::$_muted_directories[$this->cache_dir])) {
                Smarty::$_muted_directories[$this->cache_dir] = null;
            }
            return $this;
        }

        /**
        * Get cache directory
        *
        * @return string path of cache directory
        */
        public function getCacheDir() {
            return $this->cache_dir;
        }

        /**
        * Set default modifiers
        *
        * @param array|string $modifiers modifier or list of modifiers to set
        * @return Smarty current Smarty instance for chaining
        */
        public function setDefaultModifiers($modifiers) {
            $this->default_modifiers = (array) $modifiers;
            return $this;
        }

        /**
        * Add default modifiers
        *
        * @param array|string $modifiers modifier or list of modifiers to add
        * @return Smarty current Smarty instance for chaining
        */
        public function addDefaultModifiers($modifiers) {
            if (is_array($modifiers)) {
                $this->default_modifiers = array_merge($this->default_modifiers, $modifiers);
            } else {
                $this->default_modifiers[] = $modifiers;
            }

            return $this;
        }

        /**
        * Get default modifiers
        *
        * @return array list of default modifiers
        */
        public function getDefaultModifiers() {
            return $this->default_modifiers;
        }

        /**
        * Set autoload filters
        *
        * @param array $filters filters to load automatically
        * @param string $type "pre", "output", … specify the filter type to set. Defaults to none treating $filters' keys as the appropriate types
        * @return Smarty current Smarty instance for chaining
        */
        public function setAutoloadFilters($filters, $type=null) {
            if ($type !== null) {
                $this->autoload_filters[$type] = (array) $filters;
            } else {
                $this->autoload_filters = (array) $filters;
            }

            return $this;
        }

        /**
        * Add autoload filters
        *
        * @param array $filters filters to load automatically
        * @param string $type "pre", "output", … specify the filter type to set. Defaults to none treating $filters' keys as the appropriate types
        * @return Smarty current Smarty instance for chaining
        */
        public function addAutoloadFilters($filters, $type=null) {
            if ($type !== null) {
                if (!empty($this->autoload_filters[$type])) {
                    $this->autoload_filters[$type] = array_merge($this->autoload_filters[$type], (array) $filters);
                } else {
                    $this->autoload_filters[$type] = (array) $filters;
                }
            } else {
                foreach ((array) $filters as $key => $value) {
                    if (!empty($this->autoload_filters[$key])) {
                        $this->autoload_filters[$key] = array_merge($this->autoload_filters[$key], (array) $value);
                    } else {
                        $this->autoload_filters[$key] = (array) $value;
                    }
                }
            }

            return $this;
        }

        /**
        * Get autoload filters
        *
        * @param string $type type of filter to get autoloads for. Defaults to all autoload filters
        * @return array array( 'type1' => array( 'filter1', 'filter2', … ) ) or array( 'filter1', 'filter2', …) if $type was specified
        */
        public function getAutoloadFilters($type=null) {
            if ($type !== null) {
                return isset($this->autoload_filters[$type]) ? $this->autoload_filters[$type] : array();
            }

            return $this->autoload_filters;
        }

        /**
        * return name of debugging template
        *
        * @return string
        */
        public function getDebugTemplate() {
            return $this->debug_tpl;
        }

        /**
        * set the debug template
        *
        * @param string $tpl_name
        * @return Smarty current Smarty instance for chaining
        * @throws SmartyException if file is not readable
        */
        public function setDebugTemplate($tpl_name) {
            if (!is_readable($tpl_name)) {
                throw new SmartyException("setDebugTemplate(): Unknown file '{$tpl_name}'");
            }
            $this->debug_tpl = $tpl_name;

            return $this;
        }

        /**
        * creates a template object
        *
        * @param string $template the resource handle of the template file
        * @param mixed $cache_id cache id to be used with this template
        * @param mixed $compile_id compile id to be used with this template
        * @param object $parent next higher level of Smarty variables
        * @param boolean $do_clone flag is Smarty object shall be cloned
        * @return object template object
        */
        public function createTemplate($template, $cache_id = null, $compile_id = null, $parent = null, $do_clone = true) {
            if (!empty($cache_id) && (is_object($cache_id) || is_array($cache_id))) {
                $parent = $cache_id;
                $cache_id = null;
            }
            if (!empty($parent) && is_array($parent)) {
                $data = $parent;
                $parent = null;
            } else {
                $data = null;
            }
            // default to cache_id and compile_id of Smarty object
            $cache_id = $cache_id === null ? $this->cache_id : $cache_id;
            $compile_id = $compile_id === null ? $this->compile_id : $compile_id;
            // already in template cache?
            if ($this->allow_ambiguous_resources) {
                $_templateId = Smarty_Resource::getUniqueTemplateName($this, $template) . $cache_id . $compile_id;
            } else {
                $_templateId = $this->joined_template_dir . '#' . $template . $cache_id . $compile_id;
            }
            if (isset($_templateId[150])) {
                $_templateId = sha1($_templateId);
            }
            if (isset(self::$template_objects[$_templateId])) {
                // return cached template object
                if ($do_clone) {
                    $tpl = clone self::$template_objects[$_templateId];
                } else {
                    $tpl = self::$template_objects[$_templateId];
                }
            } else {
                // create new template object
                $tpl = clone $this;
                unset ($tpl->source, $tpl->compiled, $tpl->cached, $tpl->compiler ,$tpl->mustCompile);
                $tpl->template_resource = $template;
                $tpl->cache_id = $cache_id;
                $tpl->compile_id = $compile_id;
                $tpl->tpl_vars = new Smarty_Variable_Container($tpl);
            }
            $tpl->parent = $parent;
            // fill data if present
            if (!empty($data) && is_array($data)) {
                // set up variable values
                foreach ($data as $varname => $value) {
                    $tpl->tpl_vars->$varname = array('value' => $value);
                }
            }
            return $tpl;
        }


        /**
        * Takes unknown classes and loads plugin files for them
        * class name format: Smarty_PluginType_PluginName
        * plugin filename format: plugintype.pluginname.php
        *
        * @param string $plugin_name    class plugin name to load
        * @param bool   $check          check if already loaded
        * @return string |boolean filepath of loaded file or false
        */
        public function loadPlugin($plugin_name, $check = true) {
            // if function or class exists, exit silently (already loaded)
            if ($check && (is_callable($plugin_name) || class_exists($plugin_name, false))) {
                return true;
            }
            // Plugin name is expected to be: Smarty_[Type]_[Name]
            $_name_parts = explode('_', $plugin_name, 3);
            // class name must have three parts to be valid plugin
            // count($_name_parts) < 3 === !isset($_name_parts[2])
            if (!isset($_name_parts[2]) || strtolower($_name_parts[0]) !== 'smarty') {
                throw new SmartyException("loadPlugin(): Plugin {$plugin_name} is not a valid name format");
                return false;
            }
            // if type is "internal", get plugin from sysplugins
            if (strtolower($_name_parts[1]) == 'internal') {
                $file = SMARTY_SYSPLUGINS_DIR . strtolower($plugin_name) . '.php';
                if (file_exists($file)) {
                    require_once($file);
                    return $file;
                } else {
                    return false;
                }
            }
            // plugin filename is expected to be: [type].[name].php
            $_plugin_filename = "{$_name_parts[1]}.{$_name_parts[2]}.php";


            $_stream_resolve_include_path = function_exists('stream_resolve_include_path');
            // add SMARTY_PLUGINS_DIR if not present
            $_plugins_dir = $this->getPluginsDir();
            if (!$this->disable_core_plugins) {
                $_plugins_dir[] = SMARTY_PLUGINS_DIR;
            }

            // loop through plugin dirs and find the plugin
            foreach ($_plugins_dir as $_plugin_dir) {
                $names = array(
                    $_plugin_dir . $_plugin_filename,
                    $_plugin_dir . strtolower($_plugin_filename),
                );
                foreach ($names as $file) {
                    if (file_exists($file)) {
                        require_once($file);
                        return $file;
                    }
                    if ($this->use_include_path && !preg_match('/^([\/\\\\]|[a-zA-Z]:[\/\\\\])/', $_plugin_dir)) {
                        // try PHP include_path
                        if ($_stream_resolve_include_path) {
                            $file = stream_resolve_include_path($file);
                        } else {
                            $file = Smarty_Internal_Get_Include_Path::getIncludePath($file);
                        }
                        if ($file !== false) {
                            require_once($file);
                            return $file;
                        }
                    }
                }
            }

            // no plugin loaded
            return false;
        }

        /**
        * Compile all template files
        *
        * @param string $extension file extension
        * @param bool $force_compile force all to recompile
        * @param int $time_limit
        * @param int $max_errors
        * @return integer number of template files recompiled
        */
        public function compileAllTemplates($extention = '.tpl', $force_compile = false, $time_limit = 0, $max_errors = null) {
            return Smarty_Internal_Utility::compileAllTemplates($extention, $force_compile, $time_limit, $max_errors, $this);
        }

        /**
        * Compile all config files
        *
        * @param string $extension file extension
        * @param bool $force_compile force all to recompile
        * @param int $time_limit
        * @param int $max_errors
        * @return integer number of template files recompiled
        */
        public function compileAllConfig($extention = '.conf', $force_compile = false, $time_limit = 0, $max_errors = null) {
            return Smarty_Internal_Utility::compileAllConfig($extention, $force_compile, $time_limit, $max_errors, $this);
        }

        /**
        * Return array of tag/attributes of all tags used by an template
        *
        * @param object $templae template object
        * @return array of tag/attributes
        */
        public function getTags(Smarty_Internal_Template $template) {
            return Smarty_Internal_Utility::getTags($template);
        }

        /**
        * Run installation test
        *
        * @param array $errors Array to write errors into, rather than outputting them
        * @return boolean true if setup is fine, false if something is wrong
        */
        public function testInstall(&$errors=null) {
            return Smarty_Internal_Utility::testInstall($this, $errors);
        }

        /**
        * Get Smarty Configuration Information
        *
        * @param boolean $html return formatted HTML, array else
        * @param integer $flags see Smarty_Internal_Info constants
        * @return string|array configuration information
        */
        public function info($html=true, $flags=0) {
            $info = new Smarty_Internal_Info($this);
            return $html ? $info->getHtml($flags) : $info->getArray($flags);
        }

        /**
        * Error Handler to mute expected messages
        *
        * @link http://php.net/set_error_handler
        * @param integer $errno Error level
        * @return boolean
        */
        public static function mutingErrorHandler($errno, $errstr, $errfile, $errline, $errcontext) {
            $_is_muted_directory = false;

            // add the SMARTY_DIR to the list of muted directories
        if (!isset(Smarty::$_muted_directories[SMARTY_DIR])) {
            $smarty_dir = realpath(SMARTY_DIR);
            if ($smarty_dir !== false) {
                Smarty::$_muted_directories[SMARTY_DIR] = array(
                    'file' => $smarty_dir,
                    'length' => strlen($smarty_dir),
                );
            }
        }

            // walk the muted directories and test against $errfile
            foreach (Smarty::$_muted_directories as $key => &$dir) {
                if (!$dir) {
                    // resolve directory and length for speedy comparisons
                    $file = realpath($key);
                if ($file === false) {
                    // this directory does not exist, remove and skip it
                    unset(Smarty::$_muted_directories[$key]);
                    continue;
                }
                    $dir = array(
                        'file' => $file,
                        'length' => strlen($file),
                    );
                }
                if (strpos($errfile, $dir['file']) === 0) {
                    $_is_muted_directory = true;
                    break;
                }
            }

            // pass to next error handler if this error did not occur inside SMARTY_DIR
            // or the error was within smarty but masked to be ignored
            if (!$_is_muted_directory || ($errno && $errno & error_reporting())) {
                if (Smarty::$_previous_error_handler) {
                    return call_user_func(Smarty::$_previous_error_handler, $errno, $errstr, $errfile, $errline, $errcontext);
                } else {
                    return false;
                }
            }
        }

        /**
        * Enable error handler to mute expected messages
        *
        * @return void
        */
        public static function muteExpectedErrors() {
            /*
            error muting is done because some people implemented custom error_handlers using
            http://php.net/set_error_handler and for some reason did not understand the following paragraph:

            It is important to remember that the standard PHP error handler is completely bypassed for the
            error types specified by error_types unless the callback function returns FALSE.
            error_reporting() settings will have no effect and your error handler will be called regardless -
            however you are still able to read the current value of error_reporting and act appropriately.
            Of particular note is that this value will be 0 if the statement that caused the error was
            prepended by the @ error-control operator.

            Smarty deliberately uses @filemtime() over file_exists() and filemtime() in some places. Reasons include
            - @filemtime() is almost twice as fast as using an additional file_exists()
            - between file_exists() and filemtime() a possible race condition is opened,
            which does not exist using the simple @filemtime() approach.
            */
            $error_handler = array('Smarty', 'mutingErrorHandler');
            $previous = set_error_handler($error_handler);

            // avoid dead loops
            if ($previous !== $error_handler) {
                Smarty::$_previous_error_handler = $previous;
            }
        }

        /**
        * Disable error handler muting expected messages
        *
        * @return void
        */
        public static function unmuteExpectedErrors() {
            restore_error_handler();
        }

        /*
        EVENTS:
        filesystem:write
        filesystem:delete
        */

        // TODO: document registerCallback()
        public static function registerCallback($event, $callback=null) {
            // TODO: test if is callable
            if (is_array($event)) {
                foreach ($event as $_event => $_callback) {
                    if (!is_callable($_callback)) {
                        throw new SmartyException("registerCallback(): \"{$_event}\" not callable");
                    }

                    self::$_callbacks[$_event][] = $_callback;
                }
            } else {
                if (!is_callable($callback)) {
                    throw new SmartyException("registerCallback(): \"{$event}\" not callable");
                }
            }
        }

        // TODO: document triggerCallback()
        public static function triggerCallback($event, $data) {
            if (isset(self::$_callbacks[$event])) {
                foreach (self::$_callbacks[$event] as $callback) {
                    call_user_func_array($callback, $data);
                }
            }
        }

        /**
        * Identify and get top-level template instance
        *
        * @return Smarty_Internal_Template
        */
        public function findRootTemplate() {
            $tpl = $this;
            while ($tpl->parent && $tpl->parent->is_template) {
                if ($tpl->rootTemplate) {
                    return $this->rootTemplate = $tpl->rootTemplate;
                }

                $tpl = $tpl->parent;
            }

            return $this->rootTemplate = $tpl;
        }

        /**
        * Save value to persistent cache storage
        *
        * @param string|array $key   key to store data under, or array of key => values to store
        * @param mixed        $value value to store for $key, ignored if key is an array
        * @return Smarty_Internal_Template $this for chaining
        */
        public function assignCached($key, $value=null) {
            if (!$this->rootTemplate) {
                $this->findRootTemplate();
            }

            if (is_array($key)) {
                foreach ($key as $_key => $_value) {
                    if ($_key !== '') {
                        $this->rootTemplate->properties['cachedValues'][$_key] = $_value;
                    }
                }
            } else {
                if ($key !== '') {
                    $this->rootTemplate->properties['cachedValues'][$key] = $value;
                }
            }

            return $this;
        }

        /**
        * Get value from persistent cache storage
        *
        * @param string $key key of value to retrieve, null for all values (default)
        * @return mixed value or array of values
        */
        public function getCachedVars($key=null) {
            if (!$this->rootTemplate) {
                $this->findRootTemplate();
            }

            if ($key === null) {
                return isset($this->rootTemplate->properties['cachedValues']) ? $this->rootTemplate->properties['cachedValues'] : array();
            }

            return isset($this->rootTemplate->properties['cachedValues'][$key]) ? $this->rootTemplate->properties['cachedValues'][$key] : null;
        }

        /**
        * clean up object pointer
        *
        */
        public function cleanPointer() {
            unset ($this->source, $this->compiled, $this->cached, $this->compiler, $this->must_compile);
            $this->tpl_vars = $this->parent = $this->inheritanceParentTemplate = $this->template_function_chain = $this->rootTemplate = null;
        }

        /**
        * runtime error for not matching capture tags
        *
        */
        public function _capture_error() {
            throw new SmartyRuntimeException("Not matching {capture} open/close", $this);
        }

        /**
        * Get parent or root of template parent chain
        *
        * @param int $scope    parent or root scope
        * @return mixed object
        */
        public function _getScopePointer($scope) {
            if ($scope == Smarty::SCOPE_PARENT && !empty($this->parent)) {
                return $this->parent;
            } elseif ($scope == Smarty::SCOPE_ROOT && !empty($this->parent)) {
                $ptr = $this->parent;
                while (!empty($ptr->parent)) {
                    $ptr = $ptr->parent;
                }
                return $ptr;
            }
            return null;
        }

        /**
        * Get Template Configuration Information
        *
        * @param boolean $html  return formatted HTML, array else
        * @param integer $flags see Smarty_Internal_Info constants
        * @return string|array configuration information
        */
        public function info_TEMPLATE($html=true, $flags=0) {
            $info = new Smarty_Internal_Info($this->smarty, $this);
            return $html ? $info->getHtml($flags) : $info->getArray($flags);
        }


        /**
        * [util function] to use either var_export or unserialize/serialize to generate code for the
        * cachevalue optionflag of {assign} tag
        *
        * @param mixed $var Smarty variable value
        * @return string PHP inline code
        */
        public function _export_cache_value($var) {
            if (is_int($var) || is_float($var) || is_bool($var) || is_string($var) || (is_array($var) && !is_object($var) && !array_reduce($var, array($this, '_check_array_callback')))) {
                return var_export($var, true);
            }
            if (is_resource($var)) {
                throw new SmartyException('Cannot serialize resource');
            }
            return 'unserialize(\'' . serialize($var) . '\')';
        }

        /**
        * callback used by _export_cache_value to check arrays recursively
        *
        * @param bool $flag status of previous elements
        * @param mixed $element array element to check
        * @return bool status
        */
        private function _check_array_callback($flag, $element) {
            if (is_resource($element)) {
                throw new SmartyException('Cannot serialize resource');
            }
            $flag = $flag || is_object($element) || (!is_int($element) && !is_float($element) && !is_bool($element) && !is_string($element) && (is_array($element) && array_reduce($element, array($this, '_check_array_callback'))));
            return $flag;
        }

        /**
        * Handle unknown class methods
        *
        * @param string $name unknown method-name
        * @param array  $args argument array
        */
        public function __call($name, $args) {
            static $_prefixes = array('set' => true, 'get' => true);
            static $_resolved_property_name = array();

            // see if this is a set/get for a property
            $first3 = strtolower(substr($name, 0, 3));
            if (isset($_prefixes[$first3]) && isset($name[3]) && $name[3] !== '_') {
                if (isset($_resolved_property_name[$name])) {
                    $property_name = $_resolved_property_name[$name];
                } else {
                    // try to keep case correct for future PHP 6.0 case-sensitive class methods
                    // lcfirst() not available < PHP 5.3.0, so improvise
                    $property_name = strtolower(substr($name, 3, 1)) . substr($name, 4);
                    // convert camel case to underscored name
                    $property_name = preg_replace_callback('/([A-Z])/', array($this, 'replaceCamelcase'), $property_name);
                    $_resolved_property_name[$name] = $property_name;
                }
                if (property_exists($this, $property_name)) {
                    if ($first3 == 'get') {
                        return $this->$property_name;
                    } else {
                        return $this->$property_name = $args[0];
                    }
                } else {
                    throw new SmartyException("Template/Smarty property \"{$property_name}\" does not exist");
                }
            }
            if ($name == 'Smarty') {
                throw new SmartyException("PHP5 requires you to call __construct() instead of Smarty()");
            }
            // must be unknown
            throw new SmartyException("Smarty method \"{$name}\" does not exist");
        }

        /**
        * preg_replace callback to convert camelcase getter/setter to underscore property names
        *
        * @param string $match match string
        * @return string  replacemant
        */
        private function replaceCamelcase($match) {
            return "_" . strtolower($match[1]);
        }

    }

    // Check if we're running on windows
    Smarty::$_IS_WINDOWS = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

    // let PCRE (preg_*) treat strings as ISO-8859-1 if we're not dealing with UTF-8
    if (Smarty::$_CHARSET !== 'UTF-8') {
        Smarty::$_UTF8_MODIFIER = '';
    }

    /**
    * Smarty exception class
    * @package Smarty
    */
    class SmartyException extends Exception {
   	 		public function __construct($message) {
        		$this->message = htmlentities($message);
    		}

        public function __toString() {
            return "Smarty error: {$this->message}\n";
        }

    }

    /**
    * Smarty compiler exception class
    * @package Smarty
    */
    class SmartyCompilerException extends SmartyException {

        public function __toString() {
            // TODO
            // NOTE: PHP does escape \n and HTML tags on return. For this reasion we echo the message.
            // This needs to be investigated later.
            echo "Compiler: {$this->message}";
            return '';
        }

    }

    /**
    * Smarty runtime exception class
    * loads template source and displays line where error did occur
    *
    * @package Smarty
    */
    class SmartyRuntimeException extends SmartyException {

        protected $object = null;
        protected $line = null;

        public function __construct($message, $object = null) {
            $this->message = $message;
            $this->object = $object;
            if ($object->enable_traceback) {
                $this->line = $object->trace_call_stack[0][1];
            }
        }

        public function __toString() {
            $source = '';
            $source_trace = $this->object->enable_traceback;
            if ($source_trace) {
                if ($this->object->trace_call_stack[0][2] == 'eval' || $this->object->trace_call_stack[0][2] == 'string') {
                    $this->file = $this->object->trace_call_stack[0][2] . ':';
                    $source_trace = false;
                } else {
                    $ptr = Smarty_Resource::source(null, $this->object, $this->object->trace_call_stack[0][0]);
                    // make sure we reload source content
                    unset($ptr->content);
                    $this->file = $ptr->filepath;
                    if (!$ptr->exists) {
                        $source_trace = false;
                    }
                }
            }
            if ($source_trace == true) {
                preg_match_all("/\n/", $ptr->content, $match, PREG_OFFSET_CAPTURE);
                $start_line = max(1, $this->line - 2);
                $end_line = min($this->line + 2, count($match[0]) + 1);
                $source = "<br>";
                for ($i = $start_line; $i <= $end_line; $i++) {
                    $from = 0;
                    $to = 99999999;
                    if (isset($match[0][$i - 2])) {
                        $from = $match[0][$i - 2][1];
                    }
                    if (isset($match[0][$i - 1])) {
                        $to = $match[0][$i - 1][1] - $from;
                    }
                    $substr = substr($ptr->content, $from, $to);
                    $source .= sprintf('%4d : ', $i) . htmlspecialchars(trim(preg_replace('![\t\r\n]+!', ' ', $substr))) . "<br>";
                }
            }
            $msg = "<br>Smarty runtime exception: <b>{$this->message}</b> in <b>{$this->file}</b> line <b>{$this->line}</b>{$source}<br><br>";
            $stack = $this->object->trace_call_stack;
            array_shift($stack);
            foreach ($stack as $info) {
                $msg .= "<b>called by {$info[0]} in line {$info[1]}</b><br>";
            }
            $ptr = $this->object;
            while ($ptr->parent->is_template) {
                $ptr = $ptr->parent;
                foreach ($ptr->trace_call_stack as $info) {
                    $msg .= "<b>called by {$info[0]} in line {$info[1]}</b><br>";
                }
            }
            // TODO
            // NOTE: PHP does escape \n and HTML tags on return. For this reasion we echo the message.
            // This needs to be investigated later.
            echo $msg;
            return $this->message;
        }

    }

    /**
    * Autoloader
    */
    function smartyAutoload($class) {
        $_class = strtolower($class);
        static $_classes = array(
            'smarty_config_source' => true,
            'smarty_config_compiled' => true,
            'smarty_security' => true,
            'smarty_cacheresource' => true,
            'smarty_cacheresource_custom' => true,
            'smarty_cacheresource_keyvaluestore' => true,
            'smarty_resource' => true,
            'smarty_resource_custom' => true,
            'smarty_resource_uncompiled' => true,
            'smarty_resource_recompiled' => true,
            'smarty_compiled' => true,
            'smarty_template_cached' => 'smarty_cacheresource'
        );

        if (strpos($_class, 'smarty_internal_') === 0 || (isset($_classes[$_class]) && $_classes[$_class] === true)) {
            include SMARTY_SYSPLUGINS_DIR . $_class . '.php';
        } elseif (isset($_classes[$_class])) {
            include SMARTY_SYSPLUGINS_DIR . $_classes[$_class] . '.php';
        }
    }
