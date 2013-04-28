<?php
    /**
    * Smarty PHPunit test suite
    *
    * @package PHPunit
    * @author Uwe Tews
    */                              
    $test = 'ExtendsResourceTests';
    $function= array('testExtendExists');

    include 'smartytestdebug.inc.php';
    include $test.'.php';


    if  (empty($function))  {
        $c = new ReflectionClass('PHPUnit_Framework_TestCase'); 
        $m1 = $c->getMethods();
        foreach ($m1 as $m) {
            $remove[] = $m->name;
        }
        $remove[] = 'setUp';
        $remove[] = 'isRunnable';

        $class = new ReflectionClass($test); 
        $methods = $class->getMethods();

        foreach ($methods as $method) {
            $function[] = $method->name;
        }
        $function =array_diff($function, $remove);
    }

    $o = new $test;

    foreach ($function as $func) {
        $o->current_function = $func;
        $o->setUP();
        $o->$func();
    }

    // repeat error functions
    if (!empty($o->error_functions)) {
        $error_functions = $o->error_functions;
        $o->error_functions = array();

        foreach ($error_functions as $func) {
            $o->current_function = $func;
            $o->setUP();
            $o->$func();
        }    
    }


    $i =1;

