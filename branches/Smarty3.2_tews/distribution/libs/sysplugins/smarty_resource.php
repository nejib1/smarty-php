<?php

/**
 * Smarty Resource Plugin
 *
 *
 * @package TemplateResources
 * @author Rodney Rehm
 */

/**
 * Smarty Resource Plugin
 *
 * Base implementation for resource plugins
 *
 *
 * @package TemplateResources
 */
abstract class Smarty_Resource
{

    /**
     * cache for Smarty_Template_Source instances
     * @var array
     */
    public static $sources = array();

    /**
     * cache for Smarty_Resource instances
     * @var array
     */
    public static $resources = array();

    /**
     * resource types provided by the core
     * @var array
     */
    protected static $sysplugins = array(
        'file' => true,
        'string' => true,
        'extends' => true,
        'stream' => true,
        'eval' => true,
        'php' => true
    );

    /**
     * Name of the Class to compile this resource's contents with
     * @var string
     */
    public $compiler_class = 'Smarty_Internal_template_Compiler';

    /**
     * Name of the Class to tokenize this resource's contents with
     * @var string
     */
    public $template_lexer_class = 'Smarty_Internal_Template_lexer';

    /**
     * Name of the Class to parse this resource's contents with
     * @var string
     */
    public $template_parser_class = 'Smarty_Internal_Template_parser';

    /**
     * Load template's source into current template object
     *
     * {@internal The loaded source is assigned to $tpl_obj->source->content directly.}}
     *
     * @param Smarty_Template_Source $source source object
     * @return string template source
     * @throws SmartyException if source cannot be loaded
     */
    public abstract function getContent(Smarty_Template_Source $source);

    /**
     * populate Source Object with meta data from Resource
     *
     * @param Smarty_Template_Source $source source object
     * @param Smarty $tpl_obj     template object
     */
    public abstract function populate(Smarty_Template_Source $source, Smarty $tpl_obj = null);

    /**
     * populate Source Object with timestamp and exists from Resource
     *
     * @param Smarty_Template_Source $source source object
     */
    public function populateTimestamp(Smarty_Template_Source $source)
    {
        // intentionally left blank
    }

    /**
     * modify resource_name according to resource handlers specifications
     *
     * @param Smarty $smarty        Smarty instance
     * @param string $resource_name resource_name to make unique
     * @return string unique resource name
     */
    protected function buildUniqueResourceName(Smarty $smarty, $resource_name)
    {
        return get_class($this) . '#' . ($smarty->usage == Smarty::IS_CONFIG ? $smarty->joined_config_dir : $smarty->joined_template_dir) . '#' . $resource_name;
    }

    /**
     * Normalize Paths "foo/../bar" to "bar"
     *
     * @param string $_path path to normalize
     * @param boolean $ds respect windows directory separator
     * @return string normalized path
     */
    protected function normalizePath($_path, $ds = true)
    {
        if ($ds) {
            // don't we all just love windows?
            $_path = str_replace('\\', '/', $_path);
        }

        $offset = 0;
        // resolve simples
        $_path = preg_replace('#(/\./(\./)*)|/{2,}#', '/', $_path);
        // resolve parents
        while (true) {
            $_parent = strpos($_path, '/../', $offset);
            if (!$_parent) {
                break;
            } else if ($_path[$_parent - 1] === '.') {
                $offset = $_parent + 3;
                continue;
            }

            $_pos = strrpos($_path, '/', $_parent - strlen($_path) - 1);
            if ($_pos === false) {
                // don't we all just love windows?
                $_pos = $_parent;
            }

            $_path = substr_replace($_path, '', $_pos, $_parent + 3 - $_pos);
        }

        if ($ds && DS != '/') {
            // don't we all just love windows?
            $_path = str_replace('/', '\\', $_path);
        }

        return $_path;
    }

    /**
     * build template filepath by traversing the template_dir array
     *
     * @param Smarty_Template_Source $source    source object
     * @param Smarty $tpl_obj template object
     * @return string fully qualified filepath
     * @throws SmartyException if default template handler is registered but not callable
     */
    protected function buildFilepath(Smarty_Template_Source $source, Smarty $tpl_obj = null)
    {
        $file = $source->name;
        if ($tpl_obj->usage == Smarty::IS_CONFIG) {
            $_directories = $tpl_obj->getConfigDir();
            $_default_handler = $tpl_obj->default_config_handler_func;
        } else {
            $_directories = $tpl_obj->getTemplateDir();
            $_default_handler = $tpl_obj->default_template_handler_func;
        }

        // go relative to a given template?
        $_file_is_dotted = $file[0] == '.' && ($file[1] == '.' || $file[1] == '/' || $file[1] == "\\");
        if ($tpl_obj && isset($tpl_obj->parent) && $tpl_obj->parent->usage == Smarty::IS_TEMPLATE && $_file_is_dotted) {
            if ($tpl_obj->parent->source->type != 'file' && $tpl_obj->parent->source->type != 'extends' && !$tpl_obj->parent->allow_relative_path) {
                throw new SmartyException("Template '{$file}' cannot be relative to template of resource type '{$tpl_obj->parent->source->type}'");
            }
            $file = dirname($tpl_obj->parent->source->filepath) . DS . $file;
            $_file_exact_match = true;
            if (!preg_match('/^([\/\\\\]|[a-zA-Z]:[\/\\\\])/', $file)) {
                // the path gained from the parent template is relative to the current working directory
                // as expansions (like include_path) have already been done
                $file = getcwd() . DS . $file;
            }
        }

        // resolve relative path
        if (!preg_match('/^([\/\\\\]|[a-zA-Z]:[\/\\\\])/', $file)) {
            // don't we all just love windows?
            $_path = DS . trim(str_replace('\\', '/', $file), '/');
            $_was_relative = true;
        } else {
            // don't we all just love windows?
            $_path = str_replace('\\', '/', $file);
        }
        $_path = $this->normalizePath($_path, false);

        if (DS != '/') {
            // don't we all just love windows?
            $_path = str_replace('/', '\\', $_path);
        }
        // revert to relative
        if (isset($_was_relative)) {
            $_path = substr($_path, 1);
        }

        // this is only required for directories
        $file = rtrim($_path, '/\\');

        // files relative to a template only get one shot
        if (isset($_file_exact_match)) {
            return $this->fileExists($source, $file) ? $file : false;
        }

        // template_dir index?
        if (preg_match('#^\[(?P<key>[^\]]+)\](?P<file>.+)$#', $file, $match)) {
            $_directory = null;
            // try string indexes
            if (isset($_directories[$match['key']])) {
                $_directory = $_directories[$match['key']];
            } else if (is_numeric($match['key'])) {
                // try numeric index
                $match['key'] = (int)$match['key'];
                if (isset($_directories[$match['key']])) {
                    $_directory = $_directories[$match['key']];
                } else {
                    // try at location index
                    $keys = array_keys($_directories);
                    $_directory = $_directories[$keys[$match['key']]];
                }
            }

            if ($_directory) {
                $_file = substr($file, strpos($file, ']') + 1);
                $_filepath = $_directory . $_file;
                if ($this->fileExists($source, $_filepath)) {
                    return $_filepath;
                }
            }
        }

        $_stream_resolve_include_path = function_exists('stream_resolve_include_path');
        // relative file name?
        if (!preg_match('/^([\/\\\\]|[a-zA-Z]:[\/\\\\])/', $file)) {
            foreach ($_directories as $_directory) {
                $_filepath = $_directory . $file;
                if ($this->fileExists($source, $_filepath)) {
                    return $this->normalizePath($_filepath);
                }
                if ($tpl_obj->use_include_path && !preg_match('/^([\/\\\\]|[a-zA-Z]:[\/\\\\])/', $_directory)) {
                    // try PHP include_path
                    if ($_stream_resolve_include_path) {
                        $_filepath = stream_resolve_include_path($_filepath);
                    } else {
                        $_filepath = Smarty_Internal_Get_Include_Path::getIncludePath($_filepath);
                    }
                    if ($_filepath !== false) {
                        if ($this->fileExists($source, $_filepath)) {
                            return $this->normalizePath($_filepath);
                        }
                    }
                }
            }
        }

        // try absolute filepath
        if ($this->fileExists($source, $file)) {
            return $file;
        }

        // no tpl file found
        if ($_default_handler) {
            if (!is_callable($_default_handler)) {
                if ($tpl_obj->usage == Smarty::IS_CONFIG) {
                    throw new SmartyException("Default config handler not callable");
                } else {
                    throw new SmartyException("Default template handler not callable");
                }
            }
            $_return = call_user_func_array($_default_handler, array($source->type, $source->name, &$_content, &$_timestamp, $tpl_obj));
            if (is_string($_return)) {
                $source->timestamp = @filemtime($_return);
                $source->exists = !!$source->timestamp;
                return $_return;
            } elseif ($_return === true) {
                $source->content = $_content;
                $source->timestamp = $_timestamp;
                $source->exists = true;
                return $_filepath;
            }
        }

        // give up
        return false;
    }

    /**
     * test is file exists and save timestamp
     *
     * @param Smarty_Template_Source $source    source object
     * @param string $file file name
     * @return bool  true if file exists
     */
    protected function fileExists(Smarty_Template_Source $source, $file)
    {
        $source->timestamp = @filemtime($file);
        return $source->exists = !!$source->timestamp;
    }

    /**
     * Determine basename for compiled filename
     *
     * @param Smarty_Template_Source $source source object
     * @return string resource's basename
     */
    protected function getBasename(Smarty_Template_Source $source)
    {
        return null;
    }

    /**
     * Load Resource Handler
     *
     * @param Smarty $smarty    smarty object
     * @param string $type      name of the resource
     * @throws SmartyException
     * @return Smarty_Resource Resource Handler
     */
    public static function load(Smarty $smarty, $type)
    {
        // try smarty's cache
        if (isset($smarty->_resource_handlers[$type])) {
            return $smarty->_resource_handlers[$type];
        }

        // try registered resource
        if (isset($smarty->registered_resources[$type])) {
            if ($smarty->registered_resources[$type] instanceof Smarty_Resource) {
                $smarty->_resource_handlers[$type] = $smarty->registered_resources[$type];
                // note registered to smarty is not kept unique!
                return $smarty->_resource_handlers[$type];
            }

            if (!isset(self::$resources['registered'])) {
                self::$resources['registered'] = new Smarty_Internal_Resource_Registered();
            }
            if (!isset($smarty->_resource_handlers[$type])) {
                $smarty->_resource_handlers[$type] = self::$resources['registered'];
            }

            return $smarty->_resource_handlers[$type];
        }

        // try sysplugins dir
        if (isset(self::$sysplugins[$type])) {
            if (!isset(self::$resources[$type])) {
                $_resource_class = 'Smarty_Internal_Resource_' . ucfirst($type);
                self::$resources[$type] = new $_resource_class();
            }
            return $smarty->_resource_handlers[$type] = self::$resources[$type];
        }

        // try plugins dir
        $_resource_class = 'Smarty_Resource_' . ucfirst($type);
        if ($smarty->_loadPlugin($_resource_class)) {
            if (isset(self::$resources[$type])) {
                return $smarty->_resource_handlers[$type] = self::$resources[$type];
            }

            if (class_exists($_resource_class, false)) {
                self::$resources[$type] = new $_resource_class();
                return $smarty->_resource_handlers[$type] = self::$resources[$type];
            } else {
                $smarty->registerResource($type, array(
                    "smarty_resource_{$type}_source",
                    "smarty_resource_{$type}_timestamp",
                    "smarty_resource_{$type}_secure",
                    "smarty_resource_{$type}_trusted"
                ));

                // give it another try, now that the resource is registered properly
                return self::load($smarty, $type);
            }
        }

        // try streams
        $_known_stream = stream_get_wrappers();
        if (in_array($type, $_known_stream)) {
            // is known stream
            if (is_object($smarty->security_policy)) {
                $smarty->security_policy->isTrustedStream($type);
            }
            if (!isset(self::$resources['stream'])) {
                self::$resources['stream'] = new Smarty_Internal_Resource_Stream();
            }
            return $smarty->_resource_handlers[$type] = self::$resources['stream'];
        }

        // TODO: try default_(template|config)_handler
        // give up
        throw new SmartyException('err3', $smarty, $type);
    }

    /**
     * extract resource_type and resource_name from template_resource and config_resource
     *
     * @note "C:/foo.tpl" was forced to file resource up till Smarty 3.1.3 (including).
     * @param string $resource_name    template_resource or config_resource to parse
     * @param string $default_resource the default resource_type defined in $smarty
     * @param string &$name             the parsed resource name
     * @param string &$type             the parsed resource type
     * @return void
     */
    protected static function parseResourceName($resource_name, $default_resource, &$name, &$type)
    {
        $parts = explode(':', $resource_name, 2);
        if (!isset($parts[1]) || !isset($parts[0][1])) {
            // no resource given, use default
            // or single character before the colon is not a resource type, but part of the filepath
            $type = $default_resource;
            $name = $resource_name;
        } else {
            $type = $parts[0];
            $name = $parts[1];
        }
    }

    /**
     * modify resource_name according to resource handlers specifications
     *
     * @param Smarty $smarty        Smarty instance
     * @param string $resource_name resource_name to make unique
     * @return string unique resource name
     */

    /**
     * modify template_resource according to resource handlers specifications
     *
     * @param string $smarty            Smarty instance
     * @param string $template_resource template_resource to extracate resource handler and name of
     * @return string unique resource name
     */
    public static function getUniqueTemplateName($smarty, $template_resource)
    {
        self::parseResourceName($template_resource, $smarty->default_resource_type, $name, $type);
        // TODO: optimize for Smarty's internal resource types
        $resource = Smarty_Resource::load($smarty, $type);
        return $resource->buildUniqueResourceName($smarty, $name);
    }

    /**
     * initialize Source Object for given resource
     *
     *
     * @param Smarty $tpl_obj         template object
     * @param string $template_resource resource identifier  optional
     * @return Smarty_Template_Source Source Object
     */
    public static function source(Smarty $tpl_obj, $template_resource = null)
    {
        if ($template_resource == null) {
            $template_resource = $tpl_obj->template_resource;
        }

        // parse resource_name, load resource handler, identify unique resource name
        self::parseResourceName($template_resource, $tpl_obj->default_resource_type, $name, $type);
        $resource = Smarty_Resource::load($tpl_obj, $type);
        $unique_resource_name = $resource->buildUniqueResourceName($tpl_obj, $name);

        // create source
        $source = new Smarty_Template_Source($resource, $tpl_obj, $template_resource, $type, $name, $unique_resource_name);
        $resource->populate($source, $tpl_obj);
        return $source;
    }

}

/**
 * Smarty Resource Data Object
 *
 * Meta Data Container for Template Files
 *
 *
 * @package TemplateResources
 * @author Rodney Rehm
 *
 * @property integer $timestamp Source Timestamp
 * @property boolean $exists    Source Existence
 * @property boolean $template  Extended Template reference
 * @property string  $content   Source Content
 */
class Smarty_Template_Source extends Smarty_Internal_Magic_Error
{

    /**
     * Name of the Class to compile this resource's contents with
     * @var string
     */
    public $compiler_class = null;

    /**
     * Name of the Class to tokenize this resource's contents with
     * @var string
     */
    public $template_lexer_class = null;

    /**
     * Name of the Class to parse this resource's contents with
     * @var string
     */
    public $template_parser_class = null;

    /**
     * Unique Template ID
     * @var string
     */
    public $uid = null;

    /**
     * Template Resource (Smarty::$template_resource)
     * @var string
     */
    public $resource = null;

    /**
     * Resource Type
     * @var string
     */
    public $type = null;

    /**
     * Resource Name
     * @var string
     */
    public $name = null;

    /**
     * Unique Resource Name
     * @var string
     */
    public $unique_resource = null;

    /**
     * Source Filepath
     * @var string
     */
    public $filepath = null;

    /**
     * Source is bypassing compiler
     * @var boolean
     */
    public $uncompiled = null;

    /**
     * Source must be recompiled on every occasion
     * @var boolean
     */
    public $recompiled = null;

    /**
     * The Components an extended template is made of
     * @var array
     */
    public $components = null;

    /**
     * Resource Handler
     * @var Smarty_Resource
     */
    public $handler = null;


    /**
     * create Source Object container
     *
     * @param Smarty_Resource $handler          Resource Handler this source object communicates with
     * @param Smarty $tpl_obj
     * @param string $resource         full template_resource
     * @param string $type             type of resource
     * @param string $name             resource name
     * @param string $unique_resource  unique resource name
     */
    public function __construct(Smarty_Resource $handler, Smarty $tpl_obj, $resource, $type, $name, $unique_resource)
    {
        $this->handler = $handler; // Note: prone to circular references
        if ($tpl_obj->usage == Smarty::IS_CONFIG) {
            $this->template_lexer_class = 'Smarty_Internal_Configfilelexer';
            $this->template_parser_class = 'Smarty_Internal_Configfileparser';
            $this->compiler_class = 'Smarty_Internal_Config_Compiler';
        } else {
            $this->template_lexer_class = $handler->template_lexer_class;
            $this->template_parser_class = $handler->template_parser_class;
            $this->compiler_class = $handler->compiler_class;
        }
        $this->uncompiled = $this->handler instanceof Smarty_Resource_Uncompiled;
        $this->recompiled = $this->handler instanceof Smarty_Resource_Recompiled;

        $this->resource = $resource;
        $this->type = $type;
        $this->name = $name;
        $this->unique_resource = $unique_resource;
    }

    /**
     * get rendered template output from compiled template
     *
     * @param Smarty $tpl_obj template object
     * @param boolean $no_output_filter true if output filter shall nit run
     * @return string
     */
    public function getRenderedTemplate($tpl_obj, $no_output_filter = true)
    {
        $output = $this->handler->getRenderedTemplate($this, $tpl_obj);
        if (!$no_output_filter && (isset($tpl_obj->autoload_filters['output']) || isset($tpl_obj->registered_filters['output']))) {
            $output = Smarty_Internal_Filter_Handler::runFilter('output', $output, $tpl_obj);
        }
        return $output;
    }


    /**
     * <<magic>> Generic getter.
     *
     * @param string $property_name valid: timestamp, exists, content
     * @return mixed
     * @throws SmartyException if $property_name is not valid
     */
    public function __get($property_name)
    {
        switch ($property_name) {
            case 'timestamp':
            case 'exists':
                $this->handler->populateTimestamp($this);
                return $this->$property_name;
            case 'content':
                return $this->handler->getContent($this);

            default:
                parent::__get($property_name);
                throw new SmartyException('err2', $this);
        }
    }

    /**
     * <<magic>> Generic Setter.
     *
     * @param string $property_name valid: timestamp, exists, content, template
     * @param mixed $value        new value (is not checked)
     * @throws SmartyException if $property_name is not valid
     */
    public function __set($property_name, $value)
    {
        switch ($property_name) {
            // regular attributes
            case 'timestamp':
            case 'exists':
            case 'template':
                $this->$property_name = $value;
                break;

            default:
                parent::__set($property_name, $value);
        }
    }


}
