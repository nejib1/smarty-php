<?php

/**
 * Smarty Internal Plugin Config Compiler
 *
 * This is the config compiler class. It calls the lexer and parser to
 * perform the compiling.
 *
 *
 * @package Config
 * @author Uwe Tews
 */

/**
 * Main config file compiler class
 *
 *
 * @package Config
 */
class Smarty_Internal_Config_Compiler extends Smarty_Internal_Code
{

    /**
     * Lexer class name
     *
     * @var string
     */
    public $lexer_class = '';

    /**
     * Parser class name
     *
     * @var string
     */
    public $parser_class = '';

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
     * current template
     *
     * @var Smarty
     */
    public $tpl_obj = null;

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
     * @param string $lexer_class config lexer class name
     * @param string $parser_class config parser class name
     * @param Smarty $tpl_obj clone of Smarty class for config file
     */
    public function __construct($lexer_class, $parser_class, $tpl_obj)
    {
        $this->lexer_class = $lexer_class;
        $this->parser_class = $parser_class;
        $this->tpl_obj = $tpl_obj;
        $this->config_data['sections'] = array();
        $this->config_data['vars'] = array();
    }

    /**
     * Method to compile a Smarty config template.
     *
     * @return bool true if compiling succeeded, false if it failed
     */
    public function compileTemplateSource()
    {
        /* here is where the compiling takes place. Smarty
          tags in the templates are replaces with PHP code,
          then written to compiled files. */
        $this->file_dependency[$this->tpl_obj->source->uid] = array($this->tpl_obj->source->filepath, $this->tpl_obj->source->timestamp, $this->tpl_obj->source->type);
        // get config file source
        $_content = $this->tpl_obj->source->content . "\n";
        // on empty template just return
        if ($_content == '') {
            return true;
        }
        // init the lexer/parser to compile the config file
        $this->lex = new $this->lexer_class($_content, $this);
        $this->parser = new $this->parser_class($this->lex, $this);
        if ($this->tpl_obj->_parserdebug)
            $this->parser->PrintTrace();
        // get tokens from lexer and parse them
        while ($this->lex->yylex()) {
            if ($this->tpl_obj->_parserdebug)
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
        $this->raw("/* Smarty version " . Smarty::SMARTY_VERSION . ", created on " . strftime("%Y-%m-%d %H:%M:%S") . " compiled from \"" . $this->tpl_obj->source->filepath . "\" */")->newline();
        $this->php("if (!class_exists('{$class}',false)) {")->newline()->indent()->php("class {$class} extends Smarty_Internal_Content {")->newline()->indent();
        $this->php("public \$version = '" . Smarty::SMARTY_VERSION . "';")->newline();
        $this->php("public \$file_dependency = ")->repr($this->file_dependency)->raw(";")->newline()->newline();
        $this->php("public \$config_data = ")->repr($this->config_data)->raw(";")->newline()->newline();

        $this->php("function get_template_content (\$_smarty_tpl) {")->newline()->indent();
        $this->php("\$this->_loadConfigVars(\$_smarty_tpl);")->newline();
        $this->outdent()->php("}")->newline();

        $this->outdent()->php("}")->newline()->outdent()->php("}")->newline();
        $this->php("\$this->smarty_content = new $class(\$tpl_obj, \$this);")->newline()->newline();

        Smarty_Internal_Write_File::writeFile($this->tpl_obj->compiled->filepath, $this->buffer, $this->tpl_obj);
        $this->buffer = '';
        $this->config_data = array();
        $this->lex->compiler = null;
        $this->parser->compiler = null;
        $this->lex = null;
        $this->parser = null;
        $this->tpl_obj->compiled->exists = true;
        $this->tpl_obj->compiled->isCompiled = true;
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
     * @throws SmartyCompilerException
     */
    public function trigger_config_file_error($args = null)
    {
        // get template source line which has error
        $line = $this->lex->line;
        if (isset($args)) {
            // $line--;
        }
        $match = preg_split("/\n/", $this->lex->data);
        $error_text = "Syntax error in config file '{$this->tpl_obj->source->filepath}' on line {$line} '{$match[$line - 1]}' ";
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
