<?php

function add_include_path($path) {
  $include_paths = explode(PATH_SEPARATOR, get_include_path());
  $include_paths[] = realpath($path);
  $include_paths = array_flip(array_flip($include_paths));
  set_include_path(implode(PATH_SEPARATOR, $include_paths));
  return $include_paths;
}

add_include_path('./../../lib/ZF/1.10.7');
add_include_path('./../../src');
add_include_path(__DIR__);

function println($msg = '', $prefix = '') {
  if (!is_string($msg)) {
    $msg = print_r($msg, TRUE);
  }
  print ($prefix ? "$prefix => " : '') . "$msg\n";
}

function __autoload($className){
  $psr0ClassName = str_replace('\\', '/', ltrim($className, '\\')) . '.php';
  if (@include_once $psr0ClassName){
    return;
  }

  // this is to take care of the PEAR-style of naming classes
  $pearClassName = str_replace('_', '/', $psr0ClassName);
  if (@include_once $pearClassName){
    return;
  }

  // And finally see if we can load it as a Zend class which (might)
  // have a directory with the same name as the class.
  $className = str_replace('\\', '/', ltrim($className, '\\'));
  $classes = explode('/', str_replace('_', '/', $className));
  $zendClassName = $className . '/' . array_pop($classes) . '.php';
  @include_once $zendClassName;
}

spl_autoload_register('__autoload');
