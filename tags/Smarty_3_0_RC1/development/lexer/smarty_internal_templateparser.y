/**
* Smarty Internal Plugin Templateparser
*
* This is the template parser
* 
* 
* @package Smarty
* @subpackage Compiler
* @author Uwe Tews
*/
%name TP_
%declare_class {class Smarty_Internal_Templateparser}
%include_class
{
    // states whether the parse was successful or not
    public $successful = true;
    public $retvalue = 0;
    private $lex;
    private $internalError = false;

    function __construct($lex, $compiler) {
        // set instance object
        self::instance($this); 
        $this->lex = $lex;
        $this->compiler = $compiler;
        $this->smarty = $this->compiler->smarty;
        $this->template = $this->compiler->template;
        if ($this->template->security && isset($this->smarty->security_handler)) {
              $this->sec_obj = $this->smarty->security_policy;
        } else {
              $this->sec_obj = $this->smarty;
        }
        $this->compiler->has_variable_string = false;
				$this->compiler->prefix_code = array();
				$this->prefix_number = 0;
				$this->block_nesting_level = 0;
				$this->is_xml = false;
    }
    public static function &instance($new_instance = null)
    {
        static $instance = null;
        if (isset($new_instance) && is_object($new_instance))
            $instance = $new_instance;
        return $instance;
    }

    public static function escape_start_tag($tag_text) {
       $tag = preg_replace('/\A<\?(.*)\z/', '<<?php ?>?\1', $tag_text, -1 , $count); //Escape tag
       assert($tag !== false && $count === 1);
       return $tag;
    }

    public static function escape_end_tag($tag_text) {
       assert($tag_text === '?>');
       return '?<?php ?>>';
    }

    
} 


%token_prefix TP_

%parse_accept
{
    $this->successful = !$this->internalError;
    $this->internalError = false;
    $this->retvalue = $this->_retvalue;
    //echo $this->retvalue."\n\n";
}

%syntax_error
{
    $this->internalError = true;
    $this->yymajor = $yymajor;
    $this->compiler->trigger_template_error();
}

%left VERT.
%left COLON.

//
// complete template
//
start(res)       ::= template(t). { res = t; }

//
// loop over template elements
//
											// single template element
template(res)       ::= template_element(e). {if ($this->template->extract_code == false) {
                                                  res = e;
                                               } else {
                                                 // store code in extract buffer
                                                  $this->template->extracted_compiled_code .= e;
                                               } 
                                             }
											// loop of elements
template(res)       ::= template(t) template_element(e). {if ($this->template->extract_code == false) {
                                                             res = t.e;
                                                           } else {
                                                             // store code in extract buffer
                                                             $this->template->extracted_compiled_code .= e;
                                                             res = t;
                                                           } 
                                                          }

//
// template elements
//
											// Smarty tag
template_element(res)::= smartytag(st). {
                                          if ($this->compiler->has_code) {
                                            $tmp =''; foreach ($this->compiler->prefix_code as $code) {$tmp.=$code;} $this->compiler->prefix_code=array();
                                            res = $this->compiler->processNocacheCode($tmp.st,true);
                                         } else { 
                                           res = st;
                                         }  
                                         $this->compiler->has_variable_string = false;
                                         $this->block_nesting_level = count($this->compiler->_tag_stack);
                                        }	

											// comments
template_element(res)::= COMMENT. { res = '';}

											// Literal
template_element(res) ::= literal(l). { res = l; }

											// '<?php' tag
template_element(res)::= PHPSTARTTAG(st). {
                                      if ($this->sec_obj->php_handling == SMARTY_PHP_PASSTHRU) {
					                             res = self::escape_start_tag(st);
                                      } elseif ($this->sec_obj->php_handling == SMARTY_PHP_QUOTE) {
                                       res = $this->compiler->processNocacheCode(htmlspecialchars(st, ENT_QUOTES),false);
                                      }elseif ($this->sec_obj->php_handling == SMARTY_PHP_ALLOW) {
                                       res = $this->compiler->processNocacheCode('<?php', true);
                                      }elseif ($this->sec_obj->php_handling == SMARTY_PHP_REMOVE) {
                                       res = '';
                                      }
                                     }
											// '?>' tag
template_element(res)::= PHPENDTAG. {if ($this->is_xml) {
                                       $this->compiler->tag_nocache = true; 
                                       $this->is_xml = true; 
                                       res = $this->compiler->processNocacheCode("<?php echo '?>';?>", $this->compiler, true);
                                      }elseif ($this->sec_obj->php_handling == SMARTY_PHP_PASSTHRU) {
					                             res = '?<??>>';
                                      } elseif ($this->sec_obj->php_handling == SMARTY_PHP_QUOTE) {
                                       res = $this->compiler->processNocacheCode(htmlspecialchars('?>', ENT_QUOTES), false);
                                      }elseif ($this->sec_obj->php_handling == SMARTY_PHP_ALLOW) {
                                       res = $this->compiler->processNocacheCode('?>', true);
                                      }elseif ($this->sec_obj->php_handling == SMARTY_PHP_REMOVE) {
                                       res = '';
                                      }
                                     }

template_element(res)::= FAKEPHPSTARTTAG(t). {if ($this->lex->strip) {
                                       res = preg_replace('![\t ]*[\r\n]+[\t ]*!', '', self::escape_start_tag(t));	
                                     } else {
                                       res = self::escape_start_tag(t);	
                                     }
                                    }

											// XML tag
template_element(res)::= XMLTAG. { $this->compiler->tag_nocache = true; $this->is_xml = true; res = $this->compiler->processNocacheCode("<?php echo '<?xml';?>", $this->compiler, true);}	

											// Other template text
template_element(res)::= OTHER(o). {if ($this->lex->strip) {
                                       res = preg_replace('![\t ]*[\r\n]+[\t ]*!', '', o);	
                                     } else {
                                       res = o;	
                                     }
                                    }


literal(res) ::= LITERALSTART LITERALEND. { res = ''; }
literal(res) ::= LITERALSTART literal_elements(l) LITERALEND. { res = l; }
 
literal_elements(res) ::= literal_elements(l1) literal_element(l2). { res = l1.l2; }
literal_elements(res) ::= . { res = ''; }

literal_element(res) ::= literal(l). { res = l; }
literal_element(res) ::= LITERAL(l). { res = l; }
literal_element(res) ::= PHPSTARTTAG(st). { res = self::escape_start_tag(st); }
literal_element(res) ::= FAKEPHPSTARTTAG(st). { res = self::escape_start_tag(st); }
literal_element(res) ::= PHPENDTAG(et). { res = self::escape_end_tag(et); }


//
// output tags start here
//

									// output with optional attributes
smartytag(res)   ::= LDEL value(e) RDEL. { res = $this->compiler->compileTag('private_print_expression',array('value'=>e));}
smartytag(res)   ::= LDEL value(e) attributes(a) RDEL. { res = $this->compiler->compileTag('private_print_expression',array_merge(array('value'=>e),a));}
smartytag(res)   ::= LDEL variable(e) attributes(a) RDEL. { res = $this->compiler->compileTag('private_print_expression',array_merge(array('value'=>e),a));}
smartytag(res)   ::= LDEL expr(e) attributes(a) RDEL. { res = $this->compiler->compileTag('private_print_expression',array_merge(array('value'=>e),a));}
smartytag(res)   ::= LDEL ternary(t) attributes(a) RDEL. { res = $this->compiler->compileTag('private_print_expression',array_merge(array('value'=>t),a));}
//smartytag(res)   ::= LDEL expr(e) filter(f) modparameters(p) attributes(a) RDEL. { res = $this->compiler->compileTag('private_print_expression',array_merge(array('value'=>e),a));}

//
// Smarty tags start here
//

									// assign new style
smartytag(res)   ::= LDEL DOLLAR ID(i) EQUAL value(e) RDEL. { res = $this->compiler->compileTag('assign',array('value'=>e,'var'=>"'".i."'"));}									
smartytag(res)   ::= LDEL DOLLAR ID(i) EQUAL expr(e) RDEL. { res = $this->compiler->compileTag('assign',array('value'=>e,'var'=>"'".i."'"));}									
smartytag(res)   ::= LDEL DOLLAR ID(i) EQUAL expr(e) attributes(a) RDEL. { res = $this->compiler->compileTag('assign',array_merge(array('value'=>e,'var'=>"'".i."'"),a));}									
smartytag(res)   ::= LDEL DOLLAR ID(i) EQUAL ternary(t) attributes(a) RDEL. { res = $this->compiler->compileTag('assign',array_merge(array('value'=>t,'var'=>"'".i."'"),a));}									
smartytag(res)   ::= LDEL varindexed(vi) EQUAL expr(e) attributes(a) RDEL. { res = $this->compiler->compileTag('assign',array_merge(array('value'=>e),vi,a));}									
smartytag(res)   ::= LDEL varindexed(vi) EQUAL ternary(t) attributes(a) RDEL. { res = $this->compiler->compileTag('assign',array_merge(array('value'=>t),vi,a));}									
									// tag with optional Smarty2 style attributes
smartytag(res)   ::= LDEL ID(i) attributes(a) RDEL. { res = $this->compiler->compileTag(i,a);}
smartytag(res)   ::= LDEL FOREACH(i) attributes(a) RDEL. { res = $this->compiler->compileTag(i,a);}
smartytag(res)   ::= LDEL ID(i) RDEL. { res = $this->compiler->compileTag(i,array());}
									// registered object tag
smartytag(res)   ::= LDEL ID(i) PTR ID(m) attributes(a) RDEL. { res = $this->compiler->compileTag(i,array_merge(array('object_methode'=>m),a));}
									// tag with modifier and optional Smarty2 style attributes
smartytag(res)   ::= LDEL ID(i) modifier(m) modparameters(p) attributes(a) RDEL. {  res = '<?php ob_start();?>'.$this->compiler->compileTag(i,a).'<?php echo ';
                                                                                    res .= $this->compiler->compileTag('private_modifier',array('modifier'=>m,'params'=>'ob_get_clean()'.p)).'?>';
                                                                                 }
									// registered object tag with modifiers
smartytag(res)   ::= LDEL ID(i) PTR ID(me) modifier(m) modparameters(p) attributes(a) RDEL. {  res = '<?php ob_start();?>'.$this->compiler->compileTag(i,array_merge(array('object_methode'=>me),a)).'<?php echo ';
                                                                                               res .= $this->compiler->compileTag('private_modifier',array('modifier'=>m,'params'=>'ob_get_clean()'.p)).'?>';
                                                                                            }
									// {if}, {elseif} and {while} tag
smartytag(res)   ::= LDEL IF(i) SPACE expr(ie) RDEL. { res = $this->compiler->compileTag((i == 'else if')? 'elseif' : i,array('if condition'=>ie));}
smartytag(res)   ::= LDEL IF(i) SPACE statement(ie) RDEL. { res = $this->compiler->compileTag((i == 'else if')? 'elseif' : i,array('if condition'=>ie));}
									// {for} tag
smartytag(res)   ::= LDEL FOR(i) SPACE statements(st) SEMICOLON optspace expr(ie) SEMICOLON optspace DOLLAR varvar(v2) foraction(e2) RDEL. {
                                                             res = $this->compiler->compileTag(i,array('start'=>st,'ifexp'=>ie,'varloop'=>v2,'loop'=>e2));}

  foraction(res)	 ::= EQUAL expr(e). { res = '='.e;}
  foraction(res)	 ::= INCDEC(e). { res = e;}
smartytag(res)   ::= LDEL FOR(i) SPACE statement(st) TO expr(v) attributes(a) RDEL. { res = $this->compiler->compileTag(i,array_merge(array('start'=>st,'to'=>v),a));}
smartytag(res)   ::= LDEL FOR(i) SPACE statement(st) TO expr(v) STEP expr(v2) RDEL. { res = $this->compiler->compileTag(i,array('start'=>st,'to'=>v,'step'=>v2));}
									// {foreach $array as $var} tag
smartytag(res)   ::= LDEL FOREACH(i) SPACE value(v1) AS DOLLAR varvar(v0) RDEL. {
                                                            res = $this->compiler->compileTag(i,array('from'=>v1,'item'=>v0));}
smartytag(res)   ::= LDEL FOREACH(i) SPACE value(v1) AS DOLLAR varvar(v2) APTR DOLLAR varvar(v0) RDEL. {
                                                            res = $this->compiler->compileTag(i,array('from'=>v1,'item'=>v0,'key'=>v2));}
smartytag(res)   ::= LDEL FOREACH(i) SPACE array(a) AS DOLLAR varvar(v0) RDEL. { 
                                                            res = $this->compiler->compileTag(i,array('from'=>a,'item'=>v0));}
smartytag(res)   ::= LDEL FOREACH(i) SPACE array(a) AS DOLLAR varvar(v1) APTR DOLLAR varvar(v0) RDEL. { 
                                                            res = $this->compiler->compileTag(i,array('from'=>a,'item'=>v0,'key'=>v1));}

									// end of block tag  {/....}									
smartytag(res)   ::= LDELSLASH ID(i) RDEL. { res = $this->compiler->compileTag(i.'close',array());}
smartytag(res)   ::= LDELSLASH specialclose(i) RDEL. { res = $this->compiler->compileTag(i.'close',array());}
specialclose(res)::= IF(i). { res = i; }
specialclose(res)::= FOR(i). { res = i; }
specialclose(res)::= FOREACH(i). { res = i; }
smartytag(res)   ::= LDELSLASH ID(i) attributes(a) RDEL. { res = $this->compiler->compileTag(i.'close',a);}
smartytag(res)   ::= LDELSLASH ID(i) modifier(m) modparameters(p) attributes(a) RDEL. {  res = '<?php ob_start();?>'.$this->compiler->compileTag(i.'close',a).'<?php echo ';
                                                                                         res .= $this->compiler->compileTag('private_modifier',array('modifier'=>m,'params'=>'ob_get_clean()'.p)).'?>';
                                                                                      }
									// end of block object tag  {/....}									
smartytag(res)   ::= LDELSLASH ID(i) PTR ID(m) RDEL. {  res = $this->compiler->compileTag(i.'close',array('object_methode'=>m));}


//
//Attributes of Smarty tags 
//
									// list of attributes
attributes(res)  ::= attributes(a1) attribute(a2). { res = array_merge(a1,a2);}
									// single attribute
attributes(res)  ::= attribute(a). { res = a;}
									// no attributes
attributes(res)  ::= . { res = array();}
									
									// attribute
attribute(res)   ::= SPACE ID(v) EQUAL ID(id). { if (preg_match('~^true$~i', id)) {
                                                  res = array(v=>'true');
                                                 } elseif (preg_match('~^false$~i', id)) {
                                                  res = array(v=>'false');
                                                 } elseif (preg_match('~^null$~i', id)) {
                                                  res = array(v=>'null');
                                                 } else
                                                  res = array(v=>"'".id."'");}
attribute(res)   ::= SPACE ID(v) EQUAL expr(e). { res = array(v=>e);}
attribute(res)   ::= SPACE ID(v) EQUAL value(e). { res = array(v=>e);}
attribute(res)   ::= SPACE ID(v) EQUAL ternary(t). { res = array(v=>t);}
attribute(res)   ::= SPACE ID(v). { res = array(v=>'true');}
attribute(res)   ::= SPACE INTEGER(i) EQUAL expr(e). { res = array(i=>e);}
									

//
// statement
//
statements(res)		::= statement(s). { res = array(s);}
statements(res)		::= statements(s1) COMMA statement(s). { s1[]=s; res = s1;}

statement(res)		::= DOLLAR varvar(v) EQUAL expr(e). { res = array('var' => v, 'value'=>e);}

//
// expressions
//

									// single value
expr(res)        ::= value(v). { res = v; }
                 // resources/streams
expr(res)	       ::= DOLLAR ID(i) COLON ID(i2). {res = '$_smarty_tpl->getStreamVariable(\''. i .'://'. i2 . '\')';}
									// arithmetic expression
expr(res)        ::= expr(e) MATH(m) value(v). { res = e . trim(m) . v; } 
expr(res)        ::= expr(e) UNIMATH(m) value(v). { res = e . trim(m) . v; } 
									// bit operation 
expr(res)        ::= expr(e) ANDSYM(m) value(v). { res = e . trim(m) . v; } 

                  // array
expr(res)				::= array(a).	{res = a;}

                  // modifier
expr(res)        ::= expr(e) modifier(m) modparameters(p). {  res = $this->compiler->compileTag('private_modifier',array('modifier'=>m,'params'=>e.p)); }

// if expression
										// simple expression
expr(res)        ::= expr(e1) ifcond(c) expr(e2). {res = e1.c.e2;}
expr(res)			   ::= expr(e1) ISIN array(a).	{res = 'in_array('.e1.','.a.')';}
expr(res)			   ::= expr(e1) ISIN value(v).	{res = 'in_array('.e1.',(array)'.v.')';}
expr(res)			   ::= expr(e1) lop(o) expr(e2).	{res = e1.o.e2;}
expr(res)			   ::= expr(e1) ISDIVBY expr(e2).	{res = '!('.e1.' % '.e2.')';}
expr(res)			   ::= expr(e1) ISNOTDIVBY expr(e2).	{res = '('.e1.' % '.e2.')';}
expr(res)			   ::= expr(e1) ISEVEN.	{res = '!(1 & '.e1.')';}
expr(res)			   ::= expr(e1) ISNOTEVEN.	{res = '(1 & '.e1.')';}
expr(res)			   ::= expr(e1) ISEVENBY expr(e2).	{res = '!(1 & '.e1.' / '.e2.')';}
expr(res)			   ::= expr(e1) ISNOTEVENBY expr(e2).	{res = '(1 & '.e1.' / '.e2.')';}
expr(res)			   ::= expr(e1) ISODD.	{res = '(1 & '.e1.')';}
expr(res)			   ::= expr(e1) ISNOTODD.	{res = '!(1 & '.e1.')';}
expr(res)			   ::= expr(e1) ISODDBY expr(e2).	{res = '(1 & '.e1.' / '.e2.')';}
expr(res)			   ::= expr(e1) ISNOTODDBY expr(e2).	{res = '!(1 & '.e1.' / '.e2.')';}
expr(res)        ::= value(v1) INSTANCEOF(i) ID(id). {res = v1.i.id;}
expr(res)        ::= value(v1) INSTANCEOF(i) value(v2). {$this->prefix_number++; $this->compiler->prefix_code[] = '<?php $_tmp'.$this->prefix_number.'='.v2.';?>'; res = v1.i.'$_tmp'.$this->prefix_number;}


//
// ternary
//
ternary(res)				::= OPENP expr(v) CLOSEP  QMARK  expr(e1) COLON  expr(e2). { res = v.' ? '.e1.' : '.e2;}

								 // value
value(res)		   ::= variable(v). { res = v; }
									// +/- value
value(res)        ::= UNIMATH(m) value(v). { res = m.v; }
									// logical negation
value(res)		   ::= NOT value(v). { res = '!'.v; }
value(res)		   ::= TYPECAST(t) value(v). { res = t.v; }
value(res)		   ::= variable(v) INCDEC(o). { res = v.o; }
                 // numeric
value(res)       ::= INTEGER(n). { res = n; }
value(res)       ::= INTEGER(n1) DOT INTEGER(n2). { res = n1.'.'.n2; }
                 // ID, true, false, null
value(res)       ::= ID(id). { if (preg_match('~^true$~i', id)) {
                                res = 'true';
                               } elseif (preg_match('~^false$~i', id)) {
                                res = 'false';
                               } elseif (preg_match('~^null$~i', id)) {
                                res = 'null';
                               } else
                               res = "'".id."'"; }
									// function call
value(res)	     ::= function(f). { res = f; }
									// expression
value(res)       ::= OPENP expr(e) CLOSEP. { res = "(". e .")"; }
									// singele quoted string
value(res)	     ::= SINGLEQUOTESTRING(t). { res = t; }
									// double quoted string
value(res)	     ::= doublequoted_with_quotes(s). { res = s; }
									// static class access
value(res)	     ::= ID(c) DOUBLECOLON static_class_access(r). {if (!$this->template->security || $this->smarty->security_handler->isTrustedStaticClass(c, $this->compiler)) {
                                                                  res = c.'::'.r; 
                                                                }}
								  // Smarty tag
value(res)	     ::= smartytag(st). { $this->prefix_number++; $this->compiler->prefix_code[] = '<?php ob_start();?>'.st.'<?php $_tmp'.$this->prefix_number.'=ob_get_clean();?>'; res = '$_tmp'.$this->prefix_number; }


//
// variables 
//
									// simplest Smarty variable
//variable(res)    ::= DOLLAR varvar(v).  { res = '$_smarty_tpl->getVariable(\''. v .'\')->value'; $this->compiler->tag_nocache=$this->compiler->tag_nocache|$this->template->getVariable('v', null, true, false)->nocache;}
									// Smarty variable (optional array)
variable(res)    ::= varindexed(vi). {if (vi['var'] == '\'smarty\'') { res =  $this->compiler->compileTag('private_special_variable',vi['smarty_internal_index']);
                                      } else {
                                      if (isset($this->compiler->local_var[vi['var']])) {
                                          res = '$_smarty_tpl->tpl_vars['. vi['var'] .']->value'.vi['smarty_internal_index'];
                                         } else {
                                          res = '$_smarty_tpl->getVariable('. vi['var'] .')->value'.vi['smarty_internal_index'];
                                         }
                                       $this->compiler->tag_nocache=$this->compiler->tag_nocache|$this->template->getVariable(trim(vi['var'],"'"), null, true, false)->nocache;}}
									// variable with property
variable(res)    ::= DOLLAR varvar(v) AT ID(p). {if (isset($this->compiler->local_var[v])) {
                                                  res = '$_smarty_tpl->tpl_vars['. v .']->'.p;
                                                 } else {
                                                  res = '$_smarty_tpl->getVariable('. v .')->'.p;
                                                 }
                                                  $this->compiler->tag_nocache=$this->compiler->tag_nocache|$this->template->getVariable(trim(v,"'"), null, true, false)->nocache;}
									// object
variable(res)    ::= object(o). { res = o; }
                  // config variable
variable(res)	   ::= HATCH ID(i) HATCH. {res = '$_smarty_tpl->getConfigVariable(\''. i .'\')';}
variable(res)	   ::= HATCH variable(v) HATCH. {res = '$_smarty_tpl->getConfigVariable('. v .')';}
                  // stream access

varindexed(res)  ::= DOLLAR varvar(v) arrayindex(a). {res = array('var'=>v, 'smarty_internal_index'=>a);}

//
// array index
//
										// multiple array index
arrayindex(res)  ::= arrayindex(a1) indexdef(a2). {res = a1.a2;}
										// no array index
arrayindex        ::= . {return;}

// single index definition
										// Smarty2 style index 
indexdef(res)    ::= DOT DOLLAR varvar(v).  { res = '[$_smarty_tpl->getVariable('. v .')->value]'; $this->compiler->tag_nocache=$this->compiler->tag_nocache|$this->template->getVariable('v', null, true, false)->nocache;}
indexdef(res)    ::= DOT DOLLAR varvar(v) AT ID(p). { res = '[$_smarty_tpl->getVariable('. v .')->'.p.']'; $this->compiler->tag_nocache=$this->compiler->tag_nocache|$this->template->getVariable(trim(v,"'"), null, true, false)->nocache;}
indexdef(res)   ::= DOT ID(i). { res = "['". i ."']";}
indexdef(res)   ::= DOT INTEGER(n). { res = "[". n ."]";}
indexdef(res)   ::= DOT LDEL expr(e) RDEL. { res = "[". e ."]";}
										// section tag index
indexdef(res)   ::= OPENB ID(i)CLOSEB. { res = '['.$this->compiler->compileTag('private_special_variable','[\'section\'][\''.i.'\'][\'index\']').']';}
indexdef(res)   ::= OPENB ID(i) DOT ID(i2) CLOSEB. { res = '['.$this->compiler->compileTag('private_special_variable','[\'section\'][\''.i.'\'][\''.i2.'\']').']';}
										// PHP style index
indexdef(res)   ::= OPENB expr(e) CLOSEB. { res = "[". e ."]";}
										// f�r assign append array
indexdef(res)  ::= OPENB CLOSEB. {res = '';}

//
// variable variable names
//
										// singel identifier element
varvar(res)			 ::= varvarele(v). {res = v;}
										// sequence of identifier elements
varvar(res)			 ::= varvar(v1) varvarele(v2). {res = v1.'.'.v2;}
										// fix sections of element
varvarele(res)	 ::= ID(s). {res = '\''.s.'\'';}
										// variable sections of element
varvarele(res)	 ::= LDEL expr(e) RDEL. {res = '('.e.')';}

//
// objects
//
object(res)    ::= varindexed(vi) objectchain(oc). { if (vi['var'] == '\'smarty\'') { res =  $this->compiler->compileTag('private_special_variable',vi['smarty_internal_index']).oc;} else {
                                                         res = '$_smarty_tpl->getVariable('. vi['var'] .')->value'.vi['smarty_internal_index'].oc; $this->compiler->tag_nocache=$this->compiler->tag_nocache|$this->template->getVariable(trim(vi['var'],"'"), null, true, false)->nocache;}}
object(res)    ::= varindexed(vi) DOUBLECOLON ID(id). { if (vi['var'] == '\'smarty\'') { res =  $this->compiler->compileTag('private_special_variable',vi['smarty_internal_index']).'::'.id;} else {
                                                         res = '$_smarty_tpl->getVariable('. vi['var'] .')->value'.vi['smarty_internal_index'].'::'.id; $this->compiler->tag_nocache=$this->compiler->tag_nocache|$this->template->getVariable(trim(vi['var'],"'"), null, true, false)->nocache;}}
										// single element
objectchain(res) ::= objectelement(oe). {res  = oe; }
										// chain of elements 
objectchain(res) ::= objectchain(oc) objectelement(oe). {res  = oc.oe; }
										// variable
objectelement(res)::= PTR ID(i) arrayindex(a).	    { res = '->'.i.a;}
objectelement(res)::= PTR variable(v) arrayindex(a).	    { res = '->{'.v.a.'}';}
objectelement(res)::= PTR LDEL expr(e) RDEL arrayindex(a).	    { res = '->{'.e.a.'}';}
objectelement(res)::= PTR ID(ii) LDEL expr(e) RDEL arrayindex(a).	    { res = '->{\''.ii.'\'.'.e.a.'}';}
										// method
objectelement(res)::= PTR method(f).	{ res = '->'.f;}


//
// function
//
function(res)     ::= ID(f) OPENP params(p) CLOSEP.	{if (!$this->template->security || $this->smarty->security_handler->isTrustedPhpFunction(f, $this->compiler)) {
																					            if (f == 'isset' || f == 'empty' || f == 'array' || is_callable(f)) {
																					                res = f . "(". p .")";
																					            } else {
                                                       $this->compiler->trigger_template_error ("unknown function \"" . f . "\"");
                                                      }
                                                    }}

//
// method
//
method(res)     ::= ID(f) OPENP params(p) CLOSEP.	{ res = f . "(". p .")";}

// function/method parameter
										// multiple parameters
params(res)       ::= expr(e) COMMA params(p). { res = e.",".p;}
										// single parameter
params(res)       ::= expr(e). { res = e;}
										// kein parameter
params            ::= . { return;}

//
// modifier
//  
modifier(res)    ::= VERT AT ID(m). { res =  m;}
modifier(res)    ::= VERT ID(m). { res =  m;}

									// static class methode call
static_class_access(res)	     ::= method(m). { res = m; }
static_class_access(res)	     ::= DOLLAR ID(f) OPENP params(p) CLOSEP. { $this->prefix_number++; $this->compiler->prefix_code[] = '<?php $_tmp'.$this->prefix_number.'=$_smarty_tpl->getVariable(\''. f .'\')->value;?>'; res = '$_tmp'.$this->prefix_number.'('. p .')'; }
									// static class methode call with object chainig
static_class_access(res)	     ::= method(m) objectchain(oc). { res = m.oc; }
static_class_access(res)	     ::= DOLLAR ID(f) OPENP params(p) CLOSEP objectchain(oc). { $this->prefix_number++; $this->compiler->prefix_code[] = '<?php $_tmp'.$this->prefix_number.'=$_smarty_tpl->getVariable(\''. f .'\')->value;?>'; res = '$_tmp'.$this->prefix_number.'('. p .')'.oc; }
									// static class constant
static_class_access(res)       ::= ID(v). { res = v;}
									// static class variables
static_class_access(res)       ::=  DOLLAR ID(v) arrayindex(a). { res = '$'.v.a;}
									// static class variables with object chain
static_class_access(res)       ::= DOLLAR ID(v) arrayindex(a) objectchain(oc). { res = '$'.v.a.oc;}

//
// filter
//  
//filter(res)    ::= HATCH VERT ID(m). { res = m;}

//
// modifier parameter
//
										// multiple parameter
modparameters(res) ::= modparameters(mps) modparameter(mp). { res = mps.mp;}
										// no parameter
modparameters      ::= . {return;}
										// parameter expression
modparameter(res) ::= COLON value(mp). {res = ','.mp;}
modparameter(res) ::= COLON array(mp). {res = ','.mp;}

// if conditions and operators
ifcond(res)        ::= EQUALS. {res = '==';}
ifcond(res)        ::= NOTEQUALS. {res = '!=';}
ifcond(res)        ::= GREATERTHAN. {res = '>';}
ifcond(res)        ::= LESSTHAN. {res = '<';}
ifcond(res)        ::= GREATEREQUAL. {res = '>=';}
ifcond(res)        ::= LESSEQUAL. {res = '<=';}
ifcond(res)        ::= IDENTITY. {res = '===';}
ifcond(res)        ::= NONEIDENTITY. {res = '!==';}
ifcond(res)        ::= MOD. {res = '%';}

lop(res)        ::= LAND. {res = '&&';}
lop(res)        ::= LOR. {res = '||';}
lop(res)        ::= LXOR. {res = ' XOR ';}

//
// ARRAY element assignment
//
array(res)		       ::=  OPENB arrayelements(a) CLOSEB.  { res = 'array('.a.')';}
arrayelements(res)   ::=  arrayelement(a).  { res = a; }
arrayelements(res)   ::=  arrayelements(a1) COMMA arrayelement(a).  { res = a1.','.a; }
arrayelements        ::=  .  { return; }
arrayelement(res)		 ::=  value(e1) APTR expr(e2). { res = e1.'=>'.e2;}
arrayelement(res)		 ::=  ID(i) APTR expr(e2). { res = '\''.i.'\'=>'.e2;}
arrayelement(res)		 ::=  expr(e). { res = e;}


//
// double qouted strings
//
doublequoted_with_quotes(res) ::= QUOTE QUOTE. { res = "''"; }
doublequoted_with_quotes(res) ::= QUOTE doublequoted(s) QUOTE. { res = s->to_smarty_php(); }

doublequoted(res)          ::= doublequoted(o1) doublequotedcontent(o2). { o1->append_subtree(o2); res = o1; }
doublequoted(res)          ::= doublequotedcontent(o). { res = new _smarty_doublequoted($this, o); }

doublequotedcontent(res)           ::=  BACKTICK variable(v) BACKTICK. { res = new _smarty_code($this, v); }
doublequotedcontent(res)           ::=  BACKTICK expr(e) BACKTICK. { res = new _smarty_code($this, e); }
doublequotedcontent(res)           ::=  DOLLARID(i). {if (isset($this->compiler->local_var["'".substr(i,1)."'"])) {
                                                       res = new _smarty_code($this, '$_smarty_tpl->tpl_vars[\''. substr(i,1) .'\']->value');
                                                      } else {
                                                       res = new _smarty_code($this, '$_smarty_tpl->getVariable(\''. substr(i,1) .'\')->value');
                                                      }
                                                      $this->compiler->tag_nocache = $this->compiler->tag_nocache | $this->template->getVariable(trim(i,"'"), null, true, false)->nocache;
  }
doublequotedcontent(res)           ::=  LDEL variable(v) RDEL. { res = new _smarty_code($this, v); }
doublequotedcontent(res)           ::=  LDEL expr(e) RDEL. { res = new _smarty_code($this, '('.e.')'); }
doublequotedcontent(res) 	   ::=  smartytag(st). {
   res = new _smarty_tag($this, st);
  }
doublequotedcontent(res)           ::=  OTHER(o). { res = new _smarty_dq_content($this, o); }


//
// optional space
//
optspace(res)			::= SPACE(s).  {res = s;}
optspace(res)			::= .          {res = '';}