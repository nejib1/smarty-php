<?php

/**
 * Smarty Internal Plugin
 *
 *
 * @package Exception
 */

/**
 * Smarty exception class
 *
 * @package Exception
 */
class SmartynException extends Exception
{

    public static $escape = true;
    public $no_escape = false;

    public function __construct($message)
    {
        $this->message = (self::$escape && !$this->no_escape) ? htmlentities($message) : $message;
    }

    public function __toString()
    {
        return "Smarty error: {$this->message}\n";
    }

}

/**
 * Smarty compiler exception class
 *
 * @package Exception
 */
class SmartyCompilerException extends SmartyException
{

    public $no_escape = true;

    public function __toString()
    {
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
 *
 * @package Exception
 */
class SmartyRuntimeException extends SmartyException
{

    protected $object = null;
    protected $line = null;
    protected $trace_call_stack = null;
    public $no_escape = true;

    public function __construct($message, $object = null)
    {
        $this->message = $message;
        $this->object = $object;
        if ($object->enable_traceback) {
            $this->trace_call_stack = $object->trace_call_stack;
            $this->line = $this->trace_call_stack[0][1];
        }
    }

    public function __toString()
    {
        $source = '';
        $source_trace = $this->object->enable_traceback;
        if ($source_trace) {
            if ($this->trace_call_stack[0][2] == 'eval' || $this->trace_call_stack[0][2] == 'string') {
                $this->file = $this->trace_call_stack[0][2] . ':';
                $source_trace = false;
            } else {
                $ptr = $this->object->_resourceLoader(Smarty::SOURCE, $this->trace_call_stack[0][2] . ':' . $this->trace_call_stack[0][0]);
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
        array_shift($this->trace_call_stack);
        foreach ($this->trace_call_stack as $info) {
            $msg .= "<b>called by {$info[0]} in line {$info[1]}</b><br>";
        }
        $ptr = $this->object;
        while ($ptr->parent->usage == Smarty::IS_TEMPLATE || $ptr->parent->usage == Smarty::IS_CONFIG) {
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
 * Smarty exception class
 * @package Smarty
 */
class SmartyException extends Exception
{

    public function __toString()
    {
        return "Smarty error: {$this->message}\n";
    }

}

/**
class SmartyException extends Exception
{

public static $error_debug = true;
public $debug_obj = null;
public static $escape = true;
public $no_escape = false;
public $parameter = null;
public $trace_back = array();

public function __construct($err, $obj = null, $parameter = null)
{
$this->obj = $obj;
$this->parameter = $parameter;
$this->info = array();

$this->buildTraceInfo();


if (!method_exists($this, strtolower($err))) {
$this->message = (self::$escape && !$this->no_escape) ? htmlentities($err) : $err;
} else {
$err = strtolower($err);
$this->message = $this->$err();
}
}

public function __toString()
{
return "Smarty error: {$this->message}\n";
}

public function buildTraceInfo()
{
$i = 0;
$j = 0;
$this->info = array();
$this->trace = $this->getTrace();
$this->traceString = $this->getTraceAsString();

while (isset($this->trace[$i])) {
$item = $this->trace[$i];
switch ($item['function']) {
case '__set':
case '__get':
case '__call':
if (isset($trace[$i + 1]['function']) && $item['function'] == $trace[$i + 1]['function']) {
break;
}
$this->info[$j]['args'] = $item['args'][0];
$this->info[$j]['function'] = $this->trace[$i + 1]['function'];
$this->info[$j]['class'] = $this->trace[$i + 1]['class'];
$this->info[$j]['line'] = $item['line'];
$this->info[$j]['file'] = $item['file'];
$j++;
break;
default:
$this->info[$j]['args'] = $item['args'];
$this->info[$j]['function'] = $item['function'];
$this->info[$j]['class'] = $item['class'];
$this->info[$j]['line'] = $item['line'];
$this->info[$j]['file'] = $item['file'];
$j++;
break;
}
$i++;
}
}


public function classInfo($class = null)
{
if ($class == null) {
$class = $this->trace_back['class'];
}
switch ($class) {
case 'Smarty_Resource':
return "Source Object [{$this->obj->type}:{$this->obj->name}] : ";
case 'Smarty':
case 'SmartyBC':
case 'SmartyBC3':
if ($this->obj->usage == Smarty::IS_SMARTY) {
return "Smarty Object : ";
} else {
return "Template Object ['{$this->obj->source->type}:{$this->obj->source->name}'] : ";
}
default:
return "$class : ";
}

}

public function err1()
{
$this->line = $this->info[0]['line'];
$this->file = $this->info[0]['file'];
return "read access to undefined property '{$this->info[0]['class']}::\${$this->info[0]['args']}' ";
}

public function err2()
{
$this->line = $this->info[0]['line'];
$this->file = $this->info[0]['file'];
return "write access to undefined property '{$this->info[0]['class']}::\${$this->info[0]['args']}' ";
}

public function err3()
{
$this->line = $this->info[0]['line'];
$this->file = $this->info[0]['file'];
return $this->classInfo($this->info[0]['class']) . "undefined resource type '{$this->parameter}' called by smarty->{$this->trace_back['function']} ('{$this->trace_back['args'][0]}'...)";
}

public function err4()
{
$this->line = $this->info[0]['line'];
$this->file = $this->info[0]['file'];
return "call of undefined method '{$this->info[0]['class']}::{$this->info[0]['args']}(...)' ";
}

public function err5()
{
$this->line = $this->info[0]['line'];
$this->file = $this->info[0]['file'];
return $this->classInfo() . 'PHP5 requires you to call __construct() instead of Smarty()';
}

public function err6()
{
$this->line = $this->info[0]['line'];
$this->file = $this->info[0]['file'];
return $this->classInfo() . "attribute 'template_resoure' must be a string at smarty->{$this->info[0]['function']}(...), was '" . gettype($this->info[0]['args'][0]) . "'";
}

public function err7()
{
$this->line = $this->trace[0]['line'];
$this->file = $this->trace[0]['file'];
return "Unable to find template source at smarty->{$this->trace[0]['function']}('{$this->obj->source->type}:{$this->obj->source->name}''...)";
//        return "Unable to find template source at smarty->{$this->trace[0]['function']}('{$this->trace[0]['args'][0]}'...)";
}
 */





