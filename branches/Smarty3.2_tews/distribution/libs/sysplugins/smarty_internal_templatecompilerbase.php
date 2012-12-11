<?php

/**
 * Smarty Internal Plugin Smarty Template Compiler Base
 *
 * This file contains the basic classes and methodes for compiling Smarty templates with lexer/parser
 *
 * @package Smarty
 * @subpackage Compiler
 * @author Uwe Tews
 */

/**
 * Main abstract compiler class
 *
 * @package Smarty
 * @subpackage Compiler
 */
abstract class Smarty_Internal_TemplateCompilerBase extends Smarty_Internal_Code {

    /**
     * inline template code templates
     *
     * @var array
     */
    public static $merged_inline_templates = array();

    /**
     * flag for nocache section
     *
     * @var bool
     */
    public $nocache = false;

    /**
     * flag for nocache tag
     *
     * @var bool
     */
    public $tag_nocache = false;

    /**
     * flag for nocache code not setting $has_nocache_flag
     *
     * @var bool
     */
    public $nocache_nolog = false;

    /**
     * suppress generation of nocache code
     *
     * @var bool
     */
    public $suppressNocacheProcessing = false;

    /**
     * force compilation of complete template as nocache
     * 0 = off
     * 1 = observe nocache flags on template type recompiled
     * 2 = force all code to be nocache
     *
     * @var integer
     */
    public $forceNocache = 0;

    /**
     * suppress generation of traceback code
     *
     * @var bool
     */
    public $suppressTraceback = false;

    /**
     * compile tag objects
     *
     * @var array
     */
    public static $_tag_objects = array();

    /**
     * tag stack
     *
     * @var array
     */
    public $_tag_stack = array();

    /**
     * current template
     *
     * @var Smarty_Internal_Template
     */
    public $template = null;

    /**
     * file dependencies
     *
     * @var array
     */
    public $file_dependency = array();

    /**
     * template function properties
     *
     * @var array
     */
    public $template_functions = array();

    /**
     * template function compiled code
     *
     * @var array
     */
    public $template_functions_code = array();

    /**
     * block function properties
     *
     * @var array
     */
    public $block_functions = array();

    /**
     * block function compiled code
     *
     * @var array
     */
    public $block_functions_code = array();

    /**
     * inheritance extends template
     *
     * @var string
     */
    public $extends_resource_name = null;

    /**

      /**
     * plugins loaded by default plugin handler
     *
     * @var array
     */
    public $default_handler_plugins = array();

    /**
     * saved preprocessed modifier list
     *
     * @var mixed
     */
    public $default_modifier_list = null;

    /**
     * suppress Smarty header code in compiled template
     * @var bool
     */
    public $suppressHeader = false;

    /**
     * suppress template property header code in compiled template
     * @var bool
     */
    public $suppressTemplatePropertyHeader = false;

    /**
     * suppress processing of post filter
     * @var bool
     */
    public $suppressPostFilter = false;

    /**
     * flag if compiled template file shall we written
     * @var bool
     */
    public $write_compiled_code = true;

    /**
     * flag if currently a template function is compiled
     * @var bool
     */
    public $compiles_template_function = false;

    /**
     * called subfuntions from template function
     * @var array
     */
    public $called_template_functions = array();

    /**
     * template functions called nocache
     * @var array
     */
    public $called_nocache_template_functions = array();

    /**
     * content class name
     * @var string
     */
    public $content_class = '';

    /**
     * required plugins
     * @var array
     * @internal
     */
    public $required_plugins = array('compiled' => array(), 'nocache' => array());

    /**
     * flags for used modifier plugins
     * @var array
     */
    public $modifier_plugins = array();

    /**
     * type of already compiled modifier
     * @var array
     */
    public $known_modifier_type = array();

    /**
     * Compiles the template source
     *
     * If the template is not evaluated the compiled template is saved on disk
     * @param  Smarty_Internal_Template $template template object to compile
     */
    public function compileTemplateSource() {
        if (!$this->template->source->recompiled) {
            if ($this->template->source->components) {
                // uses real resource for file dependency
                $source = end($this->template->source->components);
            } else {
                $source = $this->template->source;
            }
            $this->file_dependency[$this->template->source->uid] = array($this->template->source->filepath, $this->template->source->timestamp, $source->type);
        }
        if ($this->template->debugging) {
            Smarty_Internal_Debug::start_compile($this->template);
        }
        // compile locking
        if ($this->template->compile_locking && !$this->template->source->recompiled) {
            if ($saved_timestamp = $this->template->compiled->timestamp) {
                touch($this->template->compiled->filepath);
            }
        }
        // call compiler
        try {
            $code = $this->compileTemplate();
        } catch (Exception $e) {
            // restore old timestamp in case of error
            if ($this->template->compile_locking && !$this->template->source->recompiled && $saved_timestamp) {
                touch($this->template->compiled->filepath, $saved_timestamp);
            }
            throw $e;
        }
        // compiling succeded
        if (!$this->template->source->recompiled && $this->template->compiler->write_compiled_code) {
            // write compiled template
            $_filepath = $this->template->compiled->filepath;
            if ($_filepath === false)
                throw new SmartyException('Invalid filepath for compiled template');
            Smarty_Internal_Write_File::writeFile($_filepath, $code, $this->template);
            $this->template->compiled->exists = true;
            $this->template->compiled->isCompiled = true;
        }
        if ($this->template->debugging) {
            Smarty_Internal_Debug::end_compile($this->template);
        }
    }

    /**
     * Method to compile a Smarty template
     *
     * @param  Smarty_Internal_Template $template template object to compile
     * @return bool true if compiling succeeded, false if it failed
     */
    public function compileTemplate() {
        // flag for nochache sections
//        $this->nocache = false;
        $this->tag_nocache = false;
        // init code buffer
        $this->indentation = 3;
        $this->buffer = '';
        // reset has noche code flag
        $this->template->has_nocache_code = false;
        // check if content class name already predefine
        if (empty($this->content_class)) {
            $this->content_class = '__Smarty_Content_' . str_replace('.', '_', uniqid('', true));
        }
        $this->template->_current_file = $saved_filepath = $this->template->source->filepath;
        // template header code
        if (!$this->suppressHeader) {
            $template_header = "<?php /* Smarty version " . Smarty::SMARTY_VERSION . ", created on " . strftime("%Y-%m-%d %H:%M:%S") . " compiled from \"" . $this->template->source->filepath . "\" */\n";
        } else {
            $template_header = '<?php ';
        }

        do {
            // flag for aborting current and start recompile
            $this->abort_and_recompile = false;
            // run prefilter if required
            if (isset($this->template->autoload_filters['pre']) || isset($this->template->registered_filters['pre'])) {
                $content = Smarty_Internal_Filter_Handler::runFilter('pre', $this->template->source->content, $this->template);
            } else {
                $content = $this->template->source->content;
            }
            // call compiler
            $this->doCompile($content);
        } while ($this->abort_and_recompile);
        $this->template->source->filepath = $saved_filepath;
        // free memory
        $this->parser->compiler = null;
        $this->parser = null;
        $this->lex->compiler = null;
        $this->lex = null;
        self::$_tag_objects = array();
        // return compiled code to template object
        // run postfilter if required on compiled template code
        if (!$this->suppressPostFilter && (isset($this->template->autoload_filters['post']) || isset($this->template->registered_filters['post']))) {
            $this->buffer = Smarty_Internal_Filter_Handler::runFilter('post', $this->buffer, $this->template);
        }
        if (!$this->suppressTemplatePropertyHeader) {
            $this->buffer = $template_header . $this->createSmartyContentClass($this->template);
        }
        return $this->buffer;
    }

    /**
     * Compile Tag
     *
     * This is a call back from the lexer/parser
     * It executes the required compile plugin for the Smarty tag
     *
     * @param string $tag       tag name
     * @param array  $args      array with tag attributes
     * @param array  $parameter array with compilation parameter
     * @return string compiled code
     */
    public function compileTag($tag, $args, $parameter = array()) {
        // $args contains the attributes parsed and compiled by the lexer/parser
        // assume that tag does compile into code, but creates no HTML output
        $this->has_code = true;
        $this->has_output = false;
        // log tag/attributes
        if (isset($this->template->get_used_tags) && $this->template->get_used_tags) {
            $this->template->used_tags[] = array($tag, $args);
        }
        // check nocache option flag
        if (in_array("'nocache'", $args) || in_array(array('nocache' => 'true'), $args)
                || in_array(array('nocache' => '"true"'), $args) || in_array(array('nocache' => "'true'"), $args)) {
            $this->tag_nocache = true;
        }
        // compile the smarty tag (required compile classes to compile the tag are autoloaded)
        if (($_output = $this->callTagCompiler($tag, $args, $parameter)) === false) {
            if (isset($this->template_functions[$tag])) {
                // template defined by {template} tag
                $args['_attr']['name'] = "'" . $tag . "'";
                $_output = $this->callTagCompiler('call', $args, $parameter);
            }
        }
        if ($_output !== false) {
            if ($_output !== true) {
                // did we get compiled code
                if ($this->has_code) {
                    // Does it create output? TODO
                    if (false && $this->has_output) {
                        $_output .= "\n";
                    }
                    // return compiled code
                    return $_output;
                }
            }
            // tag did not produce compiled code
            return '';
        } else {
            // map_named attributes
            if (isset($args['_attr'])) {
                foreach ($args['_attr'] as $key => $attribute) {
                    if (is_array($attribute)) {
                        $args = array_merge($args, $attribute);
                    }
                }
            }
            // not an internal compiler tag
            if (strlen($tag) < 6 || substr($tag, -5) != 'close') {
                // check if tag is a registered object
                if (isset($this->template->registered_objects[$tag]) && isset($parameter['object_methode'])) {
                    $methode = $parameter['object_methode'];
                    if (!in_array($methode, $this->template->registered_objects[$tag][3]) &&
                            (empty($this->template->registered_objects[$tag][1]) || in_array($methode, $this->template->registered_objects[$tag][1]))) {
                        return $this->callTagCompiler('private_object_function', $args, $parameter, $tag, $methode);
                    } elseif (in_array($methode, $this->template->registered_objects[$tag][3])) {
                        return $this->callTagCompiler('private_object_block_function', $args, $parameter, $tag, $methode);
                    } else {
                        return $this->trigger_template_error('unallowed methode "' . $methode . '" in registered object "' . $tag . '"', $this->lex->taglineno);
                    }
                }
                // check if tag is registered
                foreach (array(Smarty::PLUGIN_COMPILER, Smarty::PLUGIN_FUNCTION, Smarty::PLUGIN_BLOCK) as $plugin_type) {
                    if (isset($this->template->registered_plugins[$plugin_type][$tag])) {
                        // if compiler function plugin call it now
                        if ($plugin_type == Smarty::PLUGIN_COMPILER) {
                            return $this->callTagCompiler('private_compiler_plugin', $args, $parameter, $tag);
                        }
                        // compile registered function or block function
                        if ($plugin_type == Smarty::PLUGIN_FUNCTION || $plugin_type == Smarty::PLUGIN_BLOCK) {
                            return $this->callTagCompiler('private_registered_' . $plugin_type, $args, $parameter, $tag);
                        }
                    }
                }
                // check plugins from plugins folder
                foreach ($this->template->plugin_search_order as $plugin_type) {
                    if ($plugin_type == Smarty::PLUGIN_BLOCK && $this->template->loadPlugin('smarty_compiler_' . $tag) && (!isset($this->template->security_policy) || $this->template->security_policy->isTrustedTag($tag, $this))) {
                        $plugin = 'smarty_compiler_' . $tag;
                        if (is_callable($plugin) || class_exists($plugin, false)) {
                            return $this->callTagCompiler('private_compiler_plugin', $args, $parameter, $tag);
                        }
                        $this->trigger_template_error("Plugin '{{$tag}...}' not callable", $this->lex->taglineno);
                    } else {
                        if ($function = $this->getPlugin($tag, $plugin_type)) {
                            if (!isset($this->template->security_policy) || $this->template->security_policy->isTrustedTag($tag, $this)) {
                                return $this->callTagCompiler('private_' . $plugin_type . '_plugin', $args, $parameter, $tag, $function);
                            }
                        }
                    }
                }
                if (is_callable($this->template->default_plugin_handler_func)) {
                    $found = false;
                    // look for already resolved tags
                    foreach ($this->template->plugin_search_order as $plugin_type) {
                        if (isset($this->default_handler_plugins[$plugin_type][$tag])) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        // call default handler
                        foreach ($this->template->plugin_search_order as $plugin_type) {
                            if ($this->getPluginFromDefaultHandler($tag, $plugin_type)) {
                                $found = true;
                                break;
                            }
                        }
                    }
                    if ($found) {
                        // if compiler function plugin call it now
                        if ($plugin_type == Smarty::PLUGIN_COMPILER) {
                            return $this->callTagCompiler('private_compiler_plugin', $args, $parameter, $tag);
                        } else {
                            return $this->callTagCompiler('private_registered_' . $plugin_type, $args, $parameter, $tag);
                        }
                    }
                }
            } else {
                // compile closing tag of block function
                $base_tag = substr($tag, 0, -5);
                // check if closing tag is a registered object
                if (isset($this->template->registered_objects[$base_tag]) && isset($parameter['object_methode'])) {
                    $methode = $parameter['object_methode'];
                    if (in_array($methode, $this->template->registered_objects[$base_tag][3])) {
                        return $this->callTagCompiler('private_object_block_function', $args, $parameter, $tag, $methode);
                    } else {
                        return $this->trigger_template_error('unallowed closing tag methode "' . $methode . '" in registered object "' . $base_tag . '"', $this->lex->taglineno);
                    }
                }
                // registered compiler plugin ?
                if (isset($this->template->registered_plugins[Smarty::PLUGIN_COMPILER][$tag])) {
                    return $this->callTagCompiler('private_compiler_pluginclose', $args, $parameter, $tag);
                }
                // registered block tag ?
                if (isset($this->template->registered_plugins[Smarty::PLUGIN_BLOCK][$base_tag]) || isset($this->default_handler_plugins[Smarty::PLUGIN_BLOCK][$base_tag])) {
                    return $this->callTagCompiler('private_registered_block', $args, $parameter, $tag);
                }
                // block plugin?
                if ($function = $this->getPlugin($base_tag, Smarty::PLUGIN_BLOCK)) {
                    return $this->callTagCompiler('private_block_plugin', $args, $parameter, $tag, $function);
                }
                if ($this->template->loadPlugin('smarty_compiler_' . $tag)) {
                    return $this->callTagCompiler('private_compiler_pluginclose', $args, $parameter, $tag);
                }
                $this->trigger_template_error("Plugin '{{$tag}...}' not callable", $this->lex->taglineno);
            }
            $this->trigger_template_error("unknown tag '{{$tag}...}'", $this->lex->taglineno);
        }
    }

    /**
     * lazy loads internal compile plugin for tag and calls the compile methode
     *
     * compile objects cached for reuse.
     * class name format:  Smarty_Internal_Compile_TagName
     * plugin filename format: Smarty_Internal_Tagname.php
     *
     * @param string $tag   tag name
     * @param array $args   list of tag attributes
     * @param mixed $param1 optional parameter
     * @param mixed $param2 optional parameter
     * @param mixed $param3 optional parameter
     * @return string compiled code
     */
    public function callTagCompiler($tag, $args, $param1 = null, $param2 = null, $param3 = null) {
        // re-use object if already exists
        if (isset(self::$_tag_objects[$tag])) {
            // compile this tag
            return self::$_tag_objects[$tag]->compile($args, $this, $param1, $param2, $param3);
        }
        // lazy load internal compiler plugin
        $class_name = 'Smarty_Internal_Compile_' . $tag;
        if ($this->template->loadPlugin($class_name)) {
            // check if tag allowed by security
            if (!isset($this->template->security_policy) || $this->template->security_policy->isTrustedTag($tag, $this)) {
                // use plugin if found
                self::$_tag_objects[$tag] = new $class_name;
                // compile this tag
                return self::$_tag_objects[$tag]->compile($args, $this, $param1, $param2, $param3);
            }
        }
        // no internal compile plugin for this tag
        return false;
    }

    /**
     * Check for plugins and return function name
     *
     * @param string $pugin_name  name of plugin or function
     * @param string $plugin_type type of plugin
     * @return string call name of function
     */
    public function getPlugin($plugin_name, $plugin_type) {
        $function = null;
        if ($this->template->caching && ($this->nocache || $this->tag_nocache)) {
            if (isset($this->required_plugins['nocache'][$plugin_name][$plugin_type])) {
                $function = $this->required_plugins['nocache'][$plugin_name][$plugin_type]['function'];
            } else if (isset($this->required_plugins['compiled'][$plugin_name][$plugin_type])) {
                $this->required_plugins['nocache'][$plugin_name][$plugin_type] = $this->required_plugins['compiled'][$plugin_name][$plugin_type];
                $function = $this->required_plugins['nocache'][$plugin_name][$plugin_type]['function'];
            }
        } else {
            if (isset($this->required_plugins['compiled'][$plugin_name][$plugin_type])) {
                $function = $this->required_plugins['compiled'][$plugin_name][$plugin_type]['function'];
            } else if (isset($this->required_plugins['nocache'][$plugin_name][$plugin_type])) {
                $this->required_plugins['compiled'][$plugin_name][$plugin_type] = $this->required_plugins['nocache'][$plugin_name][$plugin_type];
                $function = $this->required_plugins['compiled'][$plugin_name][$plugin_type]['function'];
            }
        }
        if (isset($function)) {
            if ($plugin_type == 'modifier') {
                $this->modifier_plugins[$plugin_name] = true;
            }
            return $function;
        }
        // loop through plugin dirs and find the plugin
        $function = 'smarty_' . $plugin_type . '_' . $plugin_name;
        $file = $this->template->loadPlugin($function, false);

        if (is_string($file)) {
            if ($this->template->caching && ($this->nocache || $this->tag_nocache)) {
                $this->required_plugins['nocache'][$plugin_name][$plugin_type]['file'] = $file;
                $this->required_plugins['nocache'][$plugin_name][$plugin_type]['function'] = $function;
            } else {
                $this->required_plugins['compiled'][$plugin_name][$plugin_type]['file'] = $file;
                $this->required_plugins['compiled'][$plugin_name][$plugin_type]['function'] = $function;
            }
            if ($plugin_type == 'modifier') {
                $this->modifier_plugins[$plugin_name] = true;
            }
            return $function;
        }
        if (is_callable($function)) {
            // plugin function is defined in the script
            return $function;
        }
        return false;
    }

    /**
     * Check for plugins by default plugin handler
     *
     * @param string $tag         name of tag
     * @param string $plugin_type type of plugin
     * @return boolean true if found
     */
    public function getPluginFromDefaultHandler($tag, $plugin_type) {
        $callback = null;
        $script = null;
        $cacheable = true;
        $result = call_user_func_array(
                $this->template->default_plugin_handler_func, array($tag, $plugin_type, $this->template, &$callback, &$script, &$cacheable)
        );
        if ($result) {
            $this->tag_nocache = $this->tag_nocache || !$cacheable;
            if ($script !== null) {
                if (is_file($script)) {
                    if ($this->template->caching && ($this->nocache || $this->tag_nocache)) {
                        $this->required_plugins['nocache'][$tag][$plugin_type]['file'] = $script;
                        $this->required_plugins['nocache'][$tag][$plugin_type]['function'] = $callback;
                    } else {
                        $this->required_plugins['compiled'][$tag][$plugin_type]['file'] = $script;
                        $this->required_plugins['compiled'][$tag][$plugin_type]['function'] = $callback;
                    }
                    include_once $script;
                } else {
                    $this->trigger_template_error("Default plugin handler: Returned script file \"{$script}\" for \"{$tag}\" not found");
                }
            }
            if (!is_string($callback) && !(is_array($callback) && is_string($callback[0]) && is_string($callback[1]))) {
                $this->trigger_template_error("Default plugin handler: Returned callback for \"{$tag}\" must be a static function name or array of class and function name");
            }
            if (is_callable($callback)) {
                $this->default_handler_plugins[$plugin_type][$tag] = array($callback, true, array());
                return true;
            } else {
                $this->trigger_template_error("Default plugin handler: Returned callback for \"{$tag}\" not callable");
            }
        }
        return false;
    }

    /**
     * Inject inline code for nocache template sections
     *
     * This method gets the content of each template element from the parser.
     * If the content is compiled code and it should be not cached the code is injected
     * into the rendered output.
     *
     * @param string  $content content of template element
     * @param boolean $is_code true if content is compiled code
     * @param int $lineno linenumber for traceback
     * @return string content
     */
    public function nocacheCode($content, $is_code, $lineno = 0) {
        // If the template is not evaluated and we have a nocache section and or a nocache tag
        if ($is_code && (!empty($this->prefix_code) || !empty($content) || $lineno)) {
            if ($lineno && !$this->suppressTraceback) {
                $this->buffer .= "\n" . str_repeat(' ', $this->saved_indentation * 4) . "/* Line {$lineno} */\n" . str_repeat(' ', $this->saved_indentation * 4) . "\$_smarty_tpl->trace_call_stack[0][1] = {$lineno};\n";
            }
            // get prefix code
            $prefix_code = '';
            if (!empty($this->prefix_code)) {
                foreach ($this->prefix_code as $code) {
                    $prefix_code .=$code;
                }
                $this->prefix_code = array();
            }

            // generate replacement code
            $make_nocache_code = $this->nocache || $this->tag_nocache || $this->forceNocache == 2;
            if ((!($this->template->source->recompiled) || $this->forceNocache) && $this->template->caching && !$this->suppressNocacheProcessing &&
                    ($make_nocache_code || $this->nocache_nolog)) {
                if ($make_nocache_code) {
                    $this->template->has_nocache_code = true;
                }
                if ($lineno && !$this->suppressTraceback) {
                    $content = "/* Line {$this->lex->taglineno} */\$_smarty_tpl->trace_call_stack[0][1] = {$lineno};" . $content;
                }
                $content = $prefix_code . $content;
                $this->php("echo \"/*%%SmartyNocache%%*/" . str_replace(array("^#^", "^##^"), array('"', '$'), addcslashes($content, "\0\t\"\$\\")) . "/*/%%SmartyNocache%%*/\";\n");
                // make sure we include modifer plugins for nocache code
                foreach ($this->modifier_plugins as $plugin_name => $dummy) {
                    if (isset($this->required_plugins['compiled'][$plugin_name]['modifier'])) {
                        $this->required_plugins['nocache'][$plugin_name]['modifier'] = $this->required_plugins['compiled'][$plugin_name]['modifier'];
                    }
                }
            } else {
                if (!empty($prefix_code)) {
                    $this->formatPHP($prefix_code);
                }
                $this->raw($content);
            }
        } else {
            $this->raw($content);
        }
        $this->modifier_plugins = array();
        $this->suppressNocacheProcessing = false;
        $this->suppressTraceback = false;
        $this->tag_nocache = false;
        $this->nocache_nolog = false;
        return $this;
    }

    /**
     * display compiler error messages without dying
     *
     * If parameter $args is empty it is a parser detected syntax error.
     * In this case the parser is called to obtain information about expected tokens.
     *
     * If parameter $msg contains a string this is used as error message
     *
     * @param string $msg individual error message or null
     * @param string $line line-number
     * @throws SmartyCompilerException when an unexpected token is found
     */
    public function trigger_template_error($msg = null, $line = null) {
        // get template source line which has error
        if (!isset($line)) {
            $line = $this->lex->line;
        } else {
            $line = $line - $this->line_offset;
        }
        preg_match_all("/\n/", $this->lex->data, $match, PREG_OFFSET_CAPTURE);
        $start_line = max(1, $line - 2);
        $end_line = min($line + 2, count($match[0]) + 1);
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
            $substr = substr($this->lex->data, $from, $to);
            $source .= sprintf('%4d : ', $i + $this->line_offset) . htmlspecialchars(trim(preg_replace('![\t\r\n]+!', ' ', $substr))) . "<br>";
        }
        $error_text = "<b>Syntax Error</b> in template <b>'{$this->template->source->filepath}'</b>  on line " . ($line + $this->line_offset) . "<br>{$source}";
        if (isset($msg)) {
            // individual error message
            $error_text .= "<br><b>{$msg}</b><br>";
        } else {
            // expected token from parser
            $error_text .= "<br> Unexpected '<b>{$this->lex->value}</b>'";
            if (count($this->parser->yy_get_expected_tokens($this->parser->yymajor)) <= 4) {
                foreach ($this->parser->yy_get_expected_tokens($this->parser->yymajor) as $token) {
                    $exp_token = $this->parser->yyTokenName[$token];
                    if (isset($this->lex->smarty_token_names[$exp_token])) {
                        // token type from lexer
                        $expect[] = "'<b>{$this->lex->smarty_token_names[$exp_token]}</b>'";
                    } else {
                        // otherwise internal token name
                        $expect[] = $this->parser->yyTokenName[$token];
                    }
                }
                $error_text .= ', expected one of: ' . implode(' , ', $expect) . '<br>';
            }
        }
        throw new SmartyCompilerException($error_text);
    }

    /**
     * Create Smarty content class for compiled template files
     *
     * @param Smarty_Internal_Template $_template   template object
     * @param string $content   optional template content
     * @param bool   $noinstance     flag if code for creating instance shall be suppressed
     * @return string
     */
    public function createSmartyContentClass(Smarty_Internal_Template $_template, $content = null, $noinstance = false) {
        if ($content == null) {
            $content = $this->buffer;
            $this->buffer = '';
        }
        $this->indentation = 0;
        $this->php("if (!class_exists('{$this->content_class}',false)) {")->newline()->indent()->php("class {$this->content_class} extends Smarty_Internal_Content {")->newline()->indent();
        $this->php("public \$version = '" . Smarty::SMARTY_VERSION . "';")->newline();
        $this->php("public \$has_nocache_code = " . ($_template->has_nocache_code ? 'true' : 'false') . ";")->newline();
        if (!empty($_template->cached_subtemplates)) {
            $this->php("public \$cached_subtemplates = ")->repr($_template->cached_subtemplates)->raw(';')->newline();
        }
        if (!$noinstance) {
            $this->php("public \$file_dependency = ")->repr($this->file_dependency)->raw(';')->newline();
        }
        if (!empty($this->required_plugins['compiled'])) {
            $plugins = array();
            foreach ($this->required_plugins['compiled'] as $tmp) {
                foreach ($tmp as $data) {
                    $plugins[$data['file']] = $data['function'];
                }
            }
            $this->php("public \$required_plugins = ")->repr($plugins)->raw(';')->newline();
        }

        if (!empty($this->required_plugins['nocache'])) {
            $plugins = array();
            foreach ($this->required_plugins['nocache'] as $tmp) {
                foreach ($tmp as $data) {
                    $plugins[$data['file']] = $data['function'];
                }
            }
            $this->php("public \$required_plugins_nocache = ")->repr($plugins)->raw(';')->newline();
        }

        if (!empty($this->template_functions)) {
            $this->php("public \$template_functions = ")->repr($this->template_functions)->raw(';')->newline();
        }
        if (!empty($this->called_nocache_template_functions)) {
            $this->php("public \$called_nocache_template_functions = ")->repr($this->called_nocache_template_functions)->raw(';')->newline();
        }
        if (!empty($this->block_functions)) {
            $this->php("public \$block_functions = ")->repr($this->block_functions)->raw(';')->newline();
        }
        $this->php("function get_template_content (\$_smarty_tpl) {")->newline()->indent();
        $this->php("ob_start();")->newline();
        $this->raw($content);
        $content = '';
        if (isset($this->extends_resource_name)) {
            $this->buffer .= $this->extends_resource_name;
        }
        $this->php("return ob_get_clean();")->newline();
        $this->outdent()->php('}')->newline();
        foreach ($this->template_functions_code as $code) {
            $this->newline()->raw($code);
        }
        foreach ($this->block_functions_code as $code) {
            $this->newline()->raw($code);
        }
        $this->outdent()->php('}')->newline()->outdent()->php('}')->newline();
        if (!$noinstance) {
            $this->php("\$_template->compiled->smarty_content = new {$this->content_class}(\$_template);")->newline()->newline();
            foreach (Smarty_Internal_TemplateCompilerBase::$merged_inline_templates as $key => $inline_template) {
                $this->newline()->raw($inline_template['code']);
                unset(Smarty_Internal_TemplateCompilerBase::$merged_inline_templates[$key], $inline_template);
            }
        }
        return $this->buffer;
    }

}
