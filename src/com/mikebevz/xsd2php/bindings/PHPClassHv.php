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
    }

    if ($class->getAttribute('simpleType') != '') {
      $phpClass->type = $class->getAttribute('simpleType');
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

    $docs = $xPath->query('docs/doc', $class);
    $docBlock = array();

    $docBlock['xmlNamespace'] = strtolower($phpClass->parent->expandNS($phpClass->namespace));
    $docBlock['xmlType']      = $phpClass->type;
    $docBlock['xmlName']      = $phpClass->name;
    if ($phpClass->namespace != '') {
      $docBlock['var'] = $phpClass->parent->namespaceToPhp($phpClass->parent->expandNS($phpClass->namespace))."\\".$phpClass->name;
    } else {
      $docBlock['var'] = $phpClass->name;
    }

    foreach ($docs as $doc) {
      if ($doc->nodeValue != '') {
        $docBlock["xml".$doc->getAttribute('name')] = $doc->nodeValue;
      } elseif ($doc->getAttribute('value') != '') {
        $docBlock["xml".$doc->getAttribute('name')] = $doc->getAttribute('value');
      }
    }

    $phpClass->classDocBlock = $docBlock;

    $props = $xPath->query('property', $class);
    $properties = array();
    $i = 0;
    $isArray = false;
    foreach($props as $prop) {
      $properties[$i]['name'] = $prop->getAttribute('name');
      $properties[$i]['phpName'] = static::phpIdentifier($properties[$i]['name']);

      $docs = $xPath->query('docs/doc', $prop);
      foreach ($docs as $doc) {
        $properties[$i]["docs"][$doc->getAttribute('name')] = $doc->nodeValue;
      }
      if ($prop->getAttribute('xmlType') != '') {
        $properties[$i]["docs"]['xmlType']      = $prop->getAttribute('xmlType');
      }
      if ($prop->getAttribute('namespace') != '') {
        $properties[$i]["docs"]['xmlNamespace'] = $phpClass->parent->expandNS($prop->getAttribute('namespace'));
      }
      if ($prop->getAttribute('minOccurs') != '') {
        $properties[$i]["docs"]['xmlMinOccurs'] = $prop->getAttribute('minOccurs');
      }
      if ($prop->getAttribute('maxOccurs') != '') {
        $properties[$i]["docs"]['xmlMaxOccurs'] = $prop->getAttribute('maxOccurs');
        // If maxOccurs > 1, mark type as an array
        if ($prop->getAttribute('maxOccurs') > 1) {
          $isArray = $prop->getAttribute('maxOccurs');

        } elseif($prop->getAttribute('maxOccurs')=='unbounded') {
          $isArray = true;
        }

      }
      if ($prop->getAttribute('name') != '') {
        $properties[$i]["docs"]['xmlName'] = $prop->getAttribute('name');
      }

      //@todo if $prop->getAttribute('maxOccurs') > 1 - var can be an array - in future special accessor cane be implemented
      if ($prop->getAttribute('type') != '' && $prop->getAttribute('typeNamespace') == '') {
        // In general it's strange to give to Type name's namespace. Reconsider this part
        if ($prop->getAttribute('namespace') != '' && $prop->getAttribute('namespace') != $phpClass->parent->xsd2php->xsdNs) {
          $ns = "";
          if ($prop->getAttribute('namespace') == "#default#") {
            $ns = $phpClass->parent->namespaceToPhp($phpClass->parent->xsd2php->targetNamespace);
          } else {
            $ns = $phpClass->parent->namespaceToPhp($phpClass->parent->expandNS($prop->getAttribute('namespace')));
          }
          $properties[$i]["docs"]['var'] = $ns.'\\'.$prop->getAttribute('type');
        } else {
          $properties[$i]["docs"]['var'] = $prop->getAttribute('type');
        }
        // Is it unbounded array?
        if ($isArray === true) {
          $properties[$i]["docs"]['var'] = $properties[$i]["docs"]['var']."[]";
          $isArray = false;
        }
        // Is it array with defined maximum amount of elements?
        if ($isArray > 1) {
          $properties[$i]["docs"]['var'] = $properties[$i]["docs"]['var']."[".$isArray."]";
          $isArray = false;
        }
      }

      if ($prop->getAttribute('type') != '' && $prop->getAttribute('typeNamespace') != '') {
        $ns = "";
        if ($prop->getAttribute('typeNamespace') == "#default#") {
          $ns = $phpClass->parent->namespaceToPhp($phpClass->parent->xsd2php->targetNamespace);
        } else {
          $ns = $phpClass->parent->namespaceToPhp($phpClass->parent->expandNS($prop->getAttribute('typeNamespace')));
        }

        if ($prop->getAttribute('typeNamespace') == $phpClass->parent->xsd2php->xsdNs) {
          $properties[$i]["docs"]['var'] = $phpClass->parent->normalizeType($prop->getAttribute('type'));
        } else {
          $properties[$i]["docs"]['var'] = $ns.'\\'.$prop->getAttribute('type');
        }
      }

      $i++;
    }

    $phpClass->classProperties = $properties;
//*/
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
    $this->buffer->line('');

    foreach ($this->classProperties as $property) {
      $this->sendClassProperty($property);
    }

    foreach ($this->classProperties as $property) {
      $this->sendClassPropertyGetter($property);
      $this->sendClassPropertySetter($property);
    }

    // Set the end of the
    $this->buffer->line('');
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

  /**
   * Return class properties from array with indent specified
   *
   * @param array $property  Property array
   * @param array $indent Indentation in tabs
   *
   */
  protected function sendClassProperty($property, $indent = "\t") {
    $this->buffer->line($indent);

    if (!empty($property['docs'])) {
      $this->sendClassPropertyDocBlock($property['docs'], "$indent\t");
    }

    $this->buffer->line("{$indent}protected \${$property['phpName']}");
  }

  /**
   * Send property docBlock
   *
   * @param array  $docs   Array of docs
   * @param string $indent Indentation
   *
   */
  protected function sendClassPropertyDocBlock($docs, $indent = '') {
    $this->buffer->line('/**');
    foreach ($docs as $key => $value) {
      $this->buffer->line("$indent * @$key $value");
    }
    $this->buffer->line("$indent */");
  }

  /**
   * Send property Getter
   *
   * @param array  $docs   Array of docs
   * @param string $indent Indentation
   *
   */
  protected function sendClassPropertyGetter($property, $indent = "\t") {
    $this->buffer->line($indent);

    $phpName = $property['phpName'];
    $ucPhpName = ucfirst($phpName);

    if (!empty($property['docs'])) {
      #$this->sendClassPropertyGetSetDocBlock($action, $phpName, $docs, "$indent\t");
    }

    $this->buffer->lines(array(
      "protected function get{$ucPhpName}() {",
      "{$indent}return \${$phpName};",
      '}',
      '',
    ), $indent);
  }

  /**
   * Send property Setter
   *
   * @param array  $docs   Array of docs
   * @param string $indent Indentation
   *
   */
  protected function sendClassPropertySetter($property, $indent = "\t") {
    $this->buffer->line($indent);

    $phpName = $property['phpName'];
    $ucPhpName = ucfirst($phpName);

    if (!empty($property['docs'])) {
      #$this->sendClassPropertyGetSetDocBlock($action, $phpName, $docs, "$indent\t");
    }

    $this->buffer->lines(array(
      "protected function set{$ucPhpName}(\${$phpName}) {",
      "{$indent}\$this->{$phpName} = \${$phpName};",
      '}',
      '',
    ), $indent);
  }

  /**
   * Send property Getter or Setter
   *
   * @param array  $docs   Array of docs
   * @param string $indent Indentation
   *
   */
  protected function sendClassPropertyGetSet($action, $phpName, $docs, $indent = "\t") {
  }
}
