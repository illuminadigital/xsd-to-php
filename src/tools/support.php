<?php

/**
 * Check for file_exists() with include paths
 */
function fileexists($file) {
  if (file_exists($file)) return TRUE;
  foreach (explode(PATH_SEPARATOR, get_include_path()) as $path) {
    if (file_exists("$path/$file")) return TRUE;
  }
  return FALSE;
}

/**
 * Add a path to the include paths, and add
 * any sub-directories as well (allows the
 * __autoload() function to work better).
 *
 * @param string $path
 * @return array of include paths
 */
function add_include_path($path) {
  $include_paths = explode(PATH_SEPARATOR, get_include_path());
  $include_paths =  array_merge($include_paths, directoryToArray(realpath($path)));
  $include_paths = array_flip(array_flip($include_paths));
  set_include_path(implode(PATH_SEPARATOR, $include_paths));
  #println($include_paths);
  return $include_paths;
}

function directoryToArray($directory, $recursive = TRUE) {
  $array_items = array($directory);
  if ($handle = opendir($directory)) {
    while (false !== ($file = readdir($handle))) {
      if ($file != "." && $file != "..") {
        if (is_dir($directory. "/" . $file)) {
          if ($recursive) {
            $array_items = array_merge($array_items, directoryToArray($directory. "/" . $file, $recursive));
          }
          $file = $directory . "/" . $file;
          $array_items[] = preg_replace("/\/\//si", "/", $file);
        }
      }
    }
    closedir($handle);
  }
  return $array_items;
}

add_include_path(_DIR_ . '/../../lib/ZF/1.10.7');
add_include_path(_DIR_ . '/../../src');
add_include_path(__DIR__);

function println($msg = '', $prefix = '') {
  if (!is_string($msg)) {
    $msg = print_r($msg, TRUE);
  }
  print ($prefix ? "$prefix => " : '') . "$msg\n";
}

function __autoload($className){
  $debug = FALSE;
  if ($debug) {
    println(explode(PATH_SEPARATOR, get_include_path()), __FUNCTION__);
    println("Trying to load: $className", __FUNCTION__);
  }
  $psr0ClassName = str_replace('\\', '/', ltrim($className, '\\')) . '.php';
  if (@include_once $psr0ClassName){
    if ($debug) println("$psr0ClassName Loaded as PSR0", __FUNCTION__);
    return;
  }

  // Just try the raw name
  $classes = explode('/', $psr0ClassName);
  $rawClassName = array_pop($classes);
  if (@include_once $rawClassName){
    if ($debug) println("$rawClassName Loaded as Raw", __FUNCTION__);
    return;
  }

  // this is to take care of the PEAR-style of naming classes
  $pearClassName = str_replace('_', '/', $psr0ClassName);
  if (@include_once $pearClassName){
    if ($debug) println("$pearClassName Loaded as PEAR", __FUNCTION__);
    return;
  }

  // And finally see if we can load it as a Zend class which (might)
  // have a directory with the same name as the class.
  $xClassName = str_replace('\\', '/', ltrim($className, '\\'));
  $classes = explode('/', str_replace('_', '/', $xClassName));
  $zendClassName = $xClassName . '/' . array_pop($classes) . '.php';
  if (@include_once $zendClassName) {
    if ($debug) println("$zendClassName Loaded as Zend", __FUNCTION__);
    return;
  }
  if ($debug) {
    println("$className Not Loaded", __FUNCTION__);
  }
}

spl_autoload_register('__autoload');
