<?php
namespace com\mikebevz\xsd2php;

/**
 * PHP Class representation for HV
 *
 * @version 0.0.1
 *
 */
class PHPClassHv extends Common {

  static public function factory(PHPSaveFilesDefault $parent, \DOMDocument $dom, \DOMElement $class) {
    $xPath = new \DOMXPath($dom);
    $phpClass = new static($parent);

    $phpClass->name = $class->getAttribute('name');
    $phpClass->phpName = static::phpIdentifier($phpClass->name, FALSE);

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
      if (!in_array($class->getElementsByTagName('extends')->item(0)->getAttribute('name'), $phpClass->basicTypes)) {
        $phpClass->extends = $class->getElementsByTagName('extends')->item(0)->getAttribute('name');
        $phpClass->type    = $class->getElementsByTagName('extends')->item(0)->getAttribute('name');
        $phpClass->extendsNamespace = $phpClass->parent->namespaceToPhp($class->getElementsByTagName('extends')->item(0)->getAttribute('namespace'));
      }
    }

    $docBlock = new PHPDocBlock();

    $docBlock->xmlNamespace = strtolower($phpClass->parent->expandNS($phpClass->namespace));
    $docBlock->xmlType      = $phpClass->type;
    $docBlock->xmlName      = $phpClass->name;
    if ($phpClass->namespace != '') {
      $docBlock->var = $phpClass->parent->namespaceToPhp($phpClass->parent->expandNS($phpClass->namespace))."\\".$phpClass->name;
    } else {
      $docBlock->var = $phpClass->name;
    }

    $docs = $xPath->query('docs/doc', $class);
    foreach ($docs as $doc) {
      $field = "xml" . $doc->getAttribute('name');
      if ($doc->nodeValue != '') {
        $docBlock->$field = $doc->nodeValue;
      } elseif ($doc->getAttribute('value') != '') {
        $docBlock->$field = $doc->getAttribute('value');
      }
    }

    $phpClass->classDocBlock = $docBlock;

    $properties = $xPath->query('property', $class);
    foreach($properties as $property) {
      $phpClass->classProperties[] = \com\mikebevz\xsd2php\PHPPropertyHv::factory($phpClass->parent, $dom, $property);
    }

    return $phpClass;

  }

  /**
   * Make any changes to turn a supplied identifier into a valid PHP one.
   *
   * @param string $raw
   * @return string valid PHP identifier derived from $raw
   */
  static protected function phpIdentifier($raw, $instance = TRUE) {
    $raw = str_replace(array('-', '/', '\\'), '_', $raw);
    $raw = array_map('ucfirst', array_filter(explode('_', $raw)));
    if ($instance) {
      $s = $raw[0];
      if (count($raw)>1) {
        $s = strtolower($s);
      }
      else {
        $c = strtolower(substr($s, 0, 1));
        $s = $c . substr($s, 1);
      }
      $raw[0] = $s;
    }

    return implode('', $raw);
  }

  /**
   * Raw class name
   *
   * @var class name
   */
  protected $name;

  /**
   * PHP class name
   *
   * @var class name
   */
  protected $phpName;

  /**
   * Array of class level documentation
   *
   * @var array
   */
  protected $classDocBlock;

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
   * Class namespace
   * @var string
   */
  protected $namespace;

  /**
   * Class type namespace
   * @var string
   */
  protected $typeNamespace;

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
   * The output buffer
   * @var object OutputBuffer
   */
  protected $buffer;

  /**
   * Parent save-files object
   * @var object PHPSaveFilesDefault
   */
  protected $parent;

  protected function __construct(PHPSaveFilesDefault $parent) {
    $this->parent = $parent;
    $this->buffer = new OutputBuffer();
  }

  public function __get($var) {
    return property_exists($this, $var) ? $this->$var : NULL;
  }

  public function __toString() {
    return (string) $this->getPhpCode();
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

    // Send the OXM entity header
    $this->sendClassDocBlock($this->classDocBlock);

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

    // Output all the property declarations
    foreach ($this->classProperties as $property) {
      $property->declaration($this->buffer);
    }

    // Output all the property getters & setters
    foreach ($this->classProperties as $property) {
      $property->getter($this->buffer);
      $property->setter($this->buffer);
    }

    // And finish up
    $this->buffer->line("} // end class {$this->phpName}");

    return $this->buffer;
  }

  /**
   * Send Class Doc block to output buffer
   *
   * @param array  $docs   Array of docs
   *
   * return string
   */
  protected function sendClassDocBlock($docs) {
    $this->buffer->lines(array(
      '/**',
      ' * @XmlEntity',
      ' */',
    ));
  }
}
