<?php
require_once './support.php';

use com\mikebevz\xsd2php;

class LegkoTool {
  static protected $actionMap = array(
    'help' => 'showHelp',
    'compile-schema' => 'compileSchema',
    'generate-wsdl' => 'generateWSDL',
  );

  static public function command() {
    global $argv;

    array_shift($argv);
    $action = trim(array_shift($argv), '-');

    if (!isset(static::$actionMap[$action])) {
      $action = 'help';
    }

    $legko = new LegkoTool();
    $method = static::$actionMap[$action];
    return $legko->$method();
  }

  /**
   * Legko XML Facade
   * @var com\mikebevz\xsd2php\LegkoXML
   */
  protected $legko;

  /**
   * @var Zend_Console_Getopt
   */
  protected $opts;

  public function __construct() {
    global $argv;

    $this->opts = new Zend_Console_Getopt(
      array(
          'schema|s-s' => 'Source XML schema file',
          'dest|d=s' => 'Destination directory',
          'nocreate' => 'Flag to prevent creation of destination directories',
          'debug' => 'Flag to debug the conversion',
          'class|c-s' => 'PHP class to be turned into a WSDL',
          'wsdl-location|l-s' => 'WSDL service address',
          'wsdl-schema-url-s' => 'Public schema directory',
      ),
      $argv
    );

    $opts = array();
    if ($this->opts->debug) {
      $opts['debug'] = TRUE;
    }
    if ($this->opts->nocreate) {
      $opts['nocreate'] = TRUE;
    }

    $this->legko = new \com\mikebevz\xsd2php\LegkoXML($opts);
  }

  public function showHelp() {
    $version = $this->legko->getVersion();
    $help = <<<EOH
Legko XML Tool v. $version
Syntax: legko action options

Actions:
\033[32mcompile-schema\033[0m \033[33m--schema FILEPATH \033[0m \033[33m--dest DIRPATH \033[0m \033[33m--debug \033[0m \033[33m--nocreate \033[0m  Compile XML Schema to PHP bindings
\033[32mgenerate-wsdl\033[0m \033[33m--class FILEPATH \033[0m \033[33m--dest DIRPATH \033[0m Generate WSDL from PHP class

Options:
\033[33m--schema FILEPATH \033[0m Path to XML Schema file
\033[33m--dest DIRPATH \033[0m Output directory, generated files saved there
\033[33m--debug \033[0m Display debugging information
\033[33m--nocreate \033[0m Do not create the output directory even if it doesn't exist
\033[33m--class FILEPATH \033[0m PHP class
\033[33m--wsdl-location URL \033[0m Web service address
\033[33m--wsdl-schema-url URL \033[0m Public schema directory

Examples:
Generate PHP bindings with debug on
    \033[32m$ legko compile-schema --schema MySchema.xsd --dest ../bindings --debug \033[0m

EOH;

    $this->println($help);
    $this->println();
  }

  public function compileSchema() {
    try {
      $schema = $this->opts->schema;
      if (!fileexists($schema)) {
        throw new RuntimeException("Schema file '$schema' was not found");
      }
      add_include_path(dirname($schema));
    }
    catch (\RuntimeException $e) {
      throw new RuntimeException("Specify path to XML Schema file (--schema PATH) [{$e->getMessage()}]");
    }

    try {
      $dest = $this->opts->dest;
    }
    catch (\RuntimeException $e) {
      throw new RuntimeException("Specify path to XML Schema file (--schema PATH) [{$e->getMessage()}]");
    }

    $this->legko->compileSchema($schema, $dest);
    $this->println('Bindings successfully generated in ' . realpath($dest));
  }

  protected function println($msg = '') {
    print("$msg\n");
  }
}

try {
  LegkoTool::command();
}
catch (\RuntimeException $e) {
  println($e->getMessage());
}

