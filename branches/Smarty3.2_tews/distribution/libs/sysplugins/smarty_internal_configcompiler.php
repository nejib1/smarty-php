<?php

/**
 * Smarty Internal Plugin Config Compiler
 *
 * This is the config compiler class. It calls the lexer and parser to
 * perform the compiling.
 *
 * @package Smarty
 * @subpackage Config
 * @author Uwe Tews
 */

/**
 * Main config file compiler class
 *
 * @package Smarty
 * @subpackage Config
 */
class Smarty_Internal_ConfigCompiler extends Smarty_Internal_Code {

    /**
     * Lexer object
     *
     * @var object
     */
    public $lex;

    /**
     * Parser object
     *
     * @var object
     */
    public $parser;

    /**
     * file dependencies
     *
     * @var array
     */
    public $file_dependency = array();

    /**
     * Compiled config data sections and variables
     *
     * @var array
     */
    public $config_data = array();

    /**
     * Initialize compiler
     *
     * @param Smarty $smarty base instance
     */
    public function __construct($lexerclass, $parserclass, $template) {
        $this->lexerclass = $lexerclass;
        $this->parserclass = $parserclass;
        $this->template = $template;
        $this->config_data['sections'] = array();
        $this->config_data['vars'] = array();
    }

    /**
     * Method to compile a Smarty config template.
     *
     * @return bool true if compiling succeeded, false if it failed
     */
    public function compileTemplateSource() {
        /* here is where the compiling takes place. Smarty
          tags in the templates are replaces with PHP code,
          then written to compiled files. */
        $this->file_dependency[$this->template->source->uid] = array($this->template->source->filepath, $this->template->source->timestamp, $this->template->source->type);
        // get config file source
        $_content = $this->template->source->content . "\n";
        // on empty template just return
        if ($_content == '') {
            return true;
        }
        // init the lexer/parser to compile the config file
        $this->lex = new $this->lexerclass($_content, $this);
        $this->parser = new $this->parserclass($this->lex, $this);
        if ($this->template->_parserdebug)
            $parser->PrintTrace();
        // get tokens from lexer and parse them
        while ($this->lex->yylex()) {
            if ($this->template->_parserdebug)
                echo "<br>Parsing  {$this->parser->yyTokenName[$this->lex->token]} Token {$this->lex->value} Line {$this->lex->line} \n";
            $this->parser->doParse($this->lex->token, $this->lex->value);
        }
        // finish parsing process
        $this->parser->doParse(0, 0);
        // init code buffer
        $this->buffer = '';
        $this->indentation = 0;
        // content class name
        $class = '__Smarty_Content_' . str_replace('.', '_', uniqid('', true));
        $this->raw("<?php")->newline();
        $this->raw("/* Smarty version " . Smarty::SMARTY_VERSION . ", created on " . strftime("%Y-%m-%d %H:%M:%S") . " compiled from \"" . $this->template->source->filepath . "\" */")->newline();
        $this->php("if (!class_exists('{$class}',false)) {")->newline()->indent()->php("class {$class} extends Smarty_Internal_Content {")->newline()->indent();
        $this->php("public \$version = '" . Smarty::SMARTY_VERSION . "';")->newline();
        $this->php("public \$file_dependency = ")->repr($this->file_dependency)->raw(";")->newline()->newline();
        $this->php("public \$config_data = ")->repr($this->config_data)->raw(";")->newline()->newline();

        $this->php("function get_template_content (\$_smarty_tpl) {")->newline()->indent();
        $this->php("\$ptr = \$_smarty_tpl->parent;")->newline();
        $this->php("\$this->load_config_values (\$_smarty_tpl, \$ptr->tpl_vars);")->newline();
        $this->php("\$ptr = \$ptr->parent;")->newline();
        $this->php("if (\$_smarty_tpl->tpl_vars->___config_scope['value'] == 'parent' && \$ptr != null) {")->newline()->indent();
        $this->php("\$this->load_config_values (\$_smarty_tpl, \$ptr->tpl_vars);")->newline();
        $this->outdent()->php('}')->newline();
        $this->php("if (\$_smarty_tpl->tpl_vars->___config_scope['value'] == 'root' || \$_smarty_tpl->tpl_vars->___config_scope['value'] == 'global') {")->newline()->indent();
        $this->php("while (\$ptr != null && isset(\$ptr->is_template) && \$ptr->is_template) {")->newline()->indent();
        $this->php("\$this->load_config_values (\$_smarty_tpl, \$ptr->tpl_vars);")->newline();
        $this->php("\$ptr = \$ptr->parent;")->newline();
        $this->outdent()->php('}')->newline();
        $this->outdent()->php('}')->newline();
        $this->php("if (\$_smarty_tpl->tpl_vars->___config_scope['value'] == 'root') {")->newline()->indent();
        $this->php("while (\$ptr != null) {")->newline()->indent();
        $this->php("\$this->load_config_values (\$_smarty_tpl, \$ptr->tpl_vars);")->newline();
        $this->php("\$ptr = \$ptr->parent;")->newline();
        $this->outdent()->php('}')->newline();
        $this->outdent()->php('}')->newline();
        $this->php("if (\$_smarty_tpl->tpl_vars->___config_scope['value'] == 'global') {")->newline()->indent();
        $this->php("\$this->load_config_values (\$_smarty_tpl, Smarty::\$global_tpl_vars);")->newline();
        $this->outdent()->php('}')->newline();
        $this->outdent()->php('}')->newline()->newline();

        $this->php("function load_config_values (\$_smarty_tpl, \$tpl_vars) {")->newline()->indent();
        $this->php("foreach (\$this->config_data['vars'] as \$var => \$value) {")->newline()->indent();
        $this->php("if (\$_smarty_tpl->config_overwrite || !isset(\$tpl_vars->\$var)) {")->newline()->indent();
        $this->php("\$tpl_vars->\$var = array('value' => \$value);")->newline();
        $this->outdent()->php("} else {")->newline()->indent();
        $this->php("\$tpl_vars->\$var = array('value' => array_merge((array) \$tpl_vars->{\$var}['value'], (array) \$value));")->newline();
        $this->outdent()->php('}')->newline();
        $this->outdent()->php('}')->newline();
        $this->php("if (isset(\$this->config_data['sections'][\$_smarty_tpl->tpl_vars->___config_sections['value']])) {")->newline()->indent();
        $this->php("foreach (\$this->config_data['sections'][\$_smarty_tpl->tpl_vars->___config_sections['value']]['vars'] as \$var => \$value) {")->newline()->indent();
        $this->php("if (\$_smarty_tpl->config_overwrite || !isset(\$tpl_vars->\$var)) {")->newline()->indent();
        $this->php("\$tpl_vars->\$var = array('value' => \$value);")->newline();
        $this->outdent()->php("} else {")->newline()->indent();
        $this->php("\$tpl_vars->\$var = array('value' => array_merge((array) \$tpl_vars->{\$var}['value'], (array) \$value));")->newline();
        $this->outdent()->php('}')->newline();
        $this->outdent()->php('}')->newline();
        $this->outdent()->php('}')->newline();

        $this->outdent()->php("}")->newline();
        $this->outdent()->php("}")->newline()->outdent()->php("}")->newline();
        $this->php("\$this->smarty_content = new $class(\$_template);")->newline()->newline();

        Smarty_Internal_Write_File::writeFile($this->template->compiled->filepath, $this->buffer, $this->template);
        $this->buffer = '';
        $this->config_data = array();
        $this->lex->compiler = null;
        $this->parser->compiler = null;
        $this->lex = null;
        $this->parser = null;
        $this->template->compiled->exists = true;
        $this->template->compiled->isCompiled = true;
        $this->template->mustCompile = false;
    }

    /**
     * display compiler error messages without dying
     *
     * If parameter $args is empty it is a parser detected syntax error.
     * In this case the parser is called to obtain information about exspected tokens.
     *
     * If parameter $args contains a string this is used as error message
     *
     * @param string $args individual error message or null
     */
    public function trigger_config_file_error($args = null) {
        // get template source line which has error
        $line = $this->lex->line;
        if (isset($args)) {
            // $line--;
        }
        $match = preg_split("/\n/", $this->lex->data);
        $error_text = "Syntax error in config file '{$this->template->source->filepath}' on line {$line} '{$match[$line - 1]}' ";
        if (isset($args)) {
            // individual error message
            $error_text .= $args;
        } else {
            // exspected token from parser
            foreach ($this->parser->yy_get_expected_tokens($this->parser->yymajor) as $token) {
                $exp_token = $this->parser->yyTokenName[$token];
                if (isset($this->lex->smarty_token_names[$exp_token])) {
                    // token type from lexer
                    $expect[] = '"' . $this->lex->smarty_token_names[$exp_token] . '"';
                } else {
                    // otherwise internal token name
                    $expect[] = $this->parser->yyTokenName[$token];
                }
            }
            // output parser error message
            $error_text .= ' - Unexpected "' . $this->lex->value . '", expected one of: ' . implode(' , ', $expect);
        }
        throw new SmartyCompilerException($error_text);
    }

}
