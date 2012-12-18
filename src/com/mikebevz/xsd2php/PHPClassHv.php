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
    //if ($phpClass->namespace != $this->xsd2php->xsdNs) {
    $docBlock['xmlNamespace'] = $phpClass->parent->expandNS($phpClass->namespace);
    //}
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

    $props      = $xPath->query('property', $class);
    $properties = array();
    $i = 0;
    $isArray = false;
    foreach($props as $prop) {
      $properties[$i]['name'] = $prop->getAttribute('name');
      $docs                   = $xPath->query('docs/doc', $prop);
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
        $properties[$i]["docs"]['xmlName']      = $prop->getAttribute('name');
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
   * Class name
   *
   * @var class name
   */
  protected $name;

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
  protected $classProperties;

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
   * Array of class properties  array(array('name'=>'propertyName', 'docs' => array('property'=>'value')))
   * @var array
   */
  protected $properties;

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
   * Returns array of PHP classes
   *
   * @return array
   */
  protected function getPhpCode() {
    if ($this->extendsNamespace != '') {
      $this->buffer->line("use {$this->extendsNamespace}");
    }

    if (!empty($this->classDocBlock)) {
      $this->getDocBlock($this->classDocBlock);
    }

    $define = "class {$this->name}";
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
    if (in_array($this->type, $this->basicTypes)) {
      $this->buffer->line("\t\t" . $this->getDocBlock(array('xmlType'=>'value', 'var' => $this->normalizeType($this->type)), "\t\t"));
      $this->buffer->line("\tprotected \$value;");
    }

    if (!empty($this->classProperties)) {
      $this->buffer->line($this->getClassProperties($this->classProperties));
    }

    $this->buffer->line('');
    $this->buffer->line('');
    $this->buffer->line("} // end class {$this->name}");

    return $this->buffer;
  }

  /**
   * Return class properties from array with indent specified
   *
   * @param array $props  Properties array
   * @param array $indent Indentation in tabs
   *
   * @return string
   */
  protected function getClassProperties($props, $indent = "\t") {
    $this->buffer->line($indent);

    foreach ($props as $prop) {
      if (!empty($prop['docs'])) {
        $this->getDocBlock($prop['docs'], "$indent\t");
      }
      $this->buffer->line("{$indent}public \${$prop['name']}");
    }
  }

  /**
   * Return docBlock
   *
   * @param array  $docs   Array of docs
   * @param string $indent Indentation
   *
   * return string
   */
  public function getDocBlock($docs, $indent = '') {
    $this->buffer->line('/**');
    foreach ($docs as $key => $value) {
      $this->buffer->line("$indent * @$key $value");
    }
    $this->buffer->line("$indent */");
  }
}
