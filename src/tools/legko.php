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

    $this->legko = new \com\mikebevz\xsd2php\LegkoXML();

    $this->opts = new Zend_Console_Getopt(
      array(
          'dest|d=s' => 'Destination directory',
          'schema|s-s' => 'XML schema folder',
          'class|c-s' => 'PHP class',
          'wsdl-location|l-s' => 'WSDL service address',
          'wsdl-schema-url-s' => 'Public schema directory',
      ),
      $argv
    );
  }

  public function showHelp() {
    $version = $this->legko->getVersion();
    $help = <<<EOH
Legko XML Tool v. $version
Syntax: legko action options

Actions:
\033[32mcompile-schema\033[0m \033[33m--schema PATH \033[0m \033[33m--dest PATH \033[0m  Compile XML Schema to PHP bindings
\033[32mgenerate-wsdl\033[0m \033[33m--class PATH \033[0m \033[33m--dest PATH \033[0m Generate WSDL from PHP class

Options:
\033[33m--dest PATH \033[0m Output directory, generated files saved there
\033[33m--schema PATH \033[0m Path to XML Schema file
\033[33m--class PATH \033[0m PHP class
\033[33m--wsdl-location URL \033[0m Web service address
\033[33m--wsdl-schema-url URL \033[0m Public schema directory

Examples:
Generate PHP bindings
    \033[32m$ legko compile-schema --schema MySchema.xsd --dest ../bindings \033[0m

EOH;
    $this->println($help);
    $this->println("");
  }

  public function compileSchema() {
    try {
      $schema = $this->opts->getOption('schema');
      if (!file_exists($schema)) {
        throw new RuntimeException("Schema file '$schema' was not found");
      }
      add_include_path(dirname($schema));
    }
    catch (\RuntimeException $e) {
      throw new RuntimeException("Specify path to XML Schema file (--schema PATH) [{$e->getMessage()}]");
    }

    try {
      $dest = $this->opts->getOption('dest');
    }
    catch (\RuntimeException $e) {
      throw new RuntimeException("Specify path to XML Schema file (--schema PATH) [{$e->getMessage()}]");
    }

    $this->legko->compileSchema($schema, $dest);
    $this->println('Bindings successfully generated in ' . realpath($dest));
  }

  protected function println($msg = "") {
    print("$msg\n");
  }
}

try {
  LegkoTool::command();
}
catch (\RuntimeException $e) {
  println($e->getMessage());
}

