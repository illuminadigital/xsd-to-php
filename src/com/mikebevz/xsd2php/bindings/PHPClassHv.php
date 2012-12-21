<?php
namespace com\mikebevz\xsd2php;

/**
 * PHP Class representation for HV
 *
 * @version 0.0.1
 *
 */
class PHPClassHv extends PHPCommonHv {

  static public function factory(PHPSaveFilesDefault $parent, \DOMDocument $dom, \DOMElement $class) {
    $xPath = new \DOMXPath($dom);
    $phpClass = new static($parent);

    $phpClass->name = $class->getAttribute('name');
    $phpClass->phpName = static::phpIdentifier($phpClass->name, FALSE);
    $phpClass->ucPhpName = ucfirst($phpClass->phpName);

    if ($class->getAttribute('type') != '') {
      $phpClass->type = $class->getAttribute('type');
      $phpClass->simpleType = false;
    }

    if ($class->getAttribute('simpleType') != '') {
      $phpClass->type = $class->getAttribute('simpleType');
      $phpClass->simpleType = true;
    }
    if ($class->getAttribute('namespace') != '') {
      $phpClass->namespace = $class->getAttribute('namespace');
    }

    if ($class->getElementsByTagName('extends')->length > 0) {
      if (!in_array($class->getElementsByTagName('extends')->item(0)->getAttribute('name'), $phpClass->parent->basicTypes)) {
        $phpClass->extends = $class->getElementsByTagName('extends')->item(0)->getAttribute('name');
        $phpClass->type    = $class->getElementsByTagName('extends')->item(0)->getAttribute('name');
        $phpClass->extendsNamespace = $phpClass->parent->namespaceToPhp($class->getElementsByTagName('extends')->item(0)->getAttribute('namespace'));
      }
    }

    $phpClass->info->dummyProperty = $class->getAttribute('dummyProperty');
    $phpClass->info->xmlNamespace = strtolower($phpClass->parent->expandNS($phpClass->namespace));
    $phpClass->info->xmlType      = $phpClass->type;
    $phpClass->info->xmlName      = $phpClass->name;
    if ($phpClass->namespace != '') {
      $phpClass->info->var = $phpClass->parent->namespaceToPhp($phpClass->parent->expandNS($phpClass->namespace))."\\".$phpClass->name;
    } else {
      $phpClass->info->var = $phpClass->name;
    }

    $docs = $xPath->query('docs/doc', $class);
    foreach ($docs as $doc) {
      $field = "xml" . $doc->getAttribute('name');
      if ($doc->nodeValue != '') {
        $phpClass->info->$field = $doc->nodeValue;
      } elseif ($doc->getAttribute('value') != '') {
        $phpClass->info->$field = $doc->getAttribute('value');
      }
    }

    // Fetch all the properties for this
    $properties = $xPath->query('property', $class);
    foreach($properties as $property) {
      $phpClass->classProperties[] = \com\mikebevz\xsd2php\PHPPropertyHv::factory($phpClass, $dom, $property);
    }

    OXMGen::docBlockClass($dom, $class, $phpClass);

    return $phpClass;
  }

  /**
   * Class type
   *
   * @var string
   */
  protected $type;

  /**
   * Class simple type
   *
   * @var boolean
   */
  protected $simpleType;

  /**
   * Class properties
   *
   * @var array
   */
  protected $classProperties = array();

  /**
   * Class to extend
   * @var string
   */
  protected $extends;

  /**
   * Namespace of parent class
   * @var string
   */
  protected $extendsNamespace;

  /**
   * All the classes used by this class
   * @var array of string
   */
  protected $usedMap = array();

  /**
   * The output buffer
   * @var object OutputBuffer
   */
  protected $buffer;

  protected function __construct(PHPSaveFilesDefault $parent) {
    parent::__construct($parent);
    $this->buffer = new OutputBuffer();
  }

  public function __toString() {
    $sourceCode =  (string) $this->getPhpCode();

    // Build the namespace clause for the file...
    $namespaceClause = '';
    if ($this->info->xmlNamespace != '') {
      $namespace = $this->parent->namespaceToPhp($this->info->xmlNamespace);
      $namespace = str_replace('.', '\\', $namespace);
      $namespaceClause = "namespace {$namespace};";
    }
    $firstBit = "<?php\n{$namespaceClause}\n";

    // Now collect the namespaces to be used...

    $uses = array();
    foreach ($this->usedMap as $type) {
      $uses[] = $this->parent->phpClasses[$type]->useClause();
    }

    return $firstBit . implode("\n", $uses) . $sourceCode;
  }

  public function addUsed($type) {
    $this->usedMap[$type] = $type;
  }

  protected function namespaceClause() {
    $namespaceClause = '';
    if ($this->info->xmlNamespace != '') {
      $namespace = $this->parent->namespaceToPhp($this->info->xmlNamespace);
      $namespace = str_replace('.', '\\', $namespace);
      $namespaceClause = "namespace {$namespace};";
    }
    return "<?php\n{$namespaceClause}\n";
  }

  protected function useClause() {
    $useClause = '';
    if ($this->xmlNamespace != '') {
      $use = $this->parent->namespaceToPhp($this->info->xmlNamespace);
      $use = str_replace('.', '\\', $use);
      $useClause = "use {$use}\\{$this->phpName};";
    }
    return $useClause;
  }

  /**
   * Returns a PHP class
   *
   * @return string
   */
  protected function getPhpCode() {
    if ($this->extendsNamespace != '') {
      $this->buffer->line("use {$this->extendsNamespace}");
    }

    // Send the class docBlock
    $this->sendDocBlock($this->buffer);

    // Build the class identification line and send
    $define = "class {$this->phpName}";
    if ($this->extends != '') {
      if ($this->extendsNamespace != '') {
        $nsLastName = array_reverse(explode('\\', $this->extendsNamespace));
        $define .= " extends {$nsLastName[0]} \\ {$this->extends}";
      } else {
        $define .= " extends {$this->extends}";
      }
    }
    $this->buffer->line("{$define} {");

    // Output all the property enumerations
    foreach ($this->classProperties as $property) {
      $property->enumeration($this->buffer);
    }

    // Output all the property declarations
    foreach ($this->classProperties as $property) {
      $property->declaration($this->buffer);
    }

    PHPPropertyHv::buildConstructor($this->classProperties, $this->buffer);

    // Output all the property getters & setters
    foreach ($this->classProperties as $property) {
      $property->setter($this->buffer);
      $property->validator($this->buffer);
      $property->getter($this->buffer);
      if ($property->maxOccurs!=1) {
        #$property->adder($this->buffer);
        #$property->remover($this->buffer);
      }
    }

    // And finish up
    $this->buffer->line("} // end class {$this->phpName}");

    return (string) $this->buffer;
  }
}
