<?php
require_once(dirname(__FILE__)."/../dev_settings.php");
// Create Lexer
require_once './LexerGenerator.php';
$lex = new PHP_LexerGenerator('smarty_internal_configfile_lexer.plex');
$contents = file_get_contents('smarty_internal_configfile_lexer.php');
file_put_contents('smarty_internal_configfile_lexer.php', substr($contents, 0 , strlen($contents)-2));
copy('smarty_internal_configfile_lexer.php','../../distribution/libs/sysplugins/smarty_internal_configfile_lexer.php');


// Create Parser
passthru("$smarty_dev_php_cli_bin ./ParserGenerator/cli.php smarty_internal_configfile_parser.y");

$contents = file_get_contents('smarty_internal_configfile_parser.php');
$contents = '<?php
/**
* Smarty Internal Plugin Configfile Parser
*
* This is the config file parser.
* It is generated from the internal.configfile_parser.y file
* @package Smarty
* @subpackage Compiler
* @author Uwe Tews
*/
'.substr($contents,6);
file_put_contents('smarty_internal_configfile_parser.php', $contents);
copy('smarty_internal_configfile_parser.php','../../distribution/libs/sysplugins/smarty_internal_configfile_parser.php');
?>
