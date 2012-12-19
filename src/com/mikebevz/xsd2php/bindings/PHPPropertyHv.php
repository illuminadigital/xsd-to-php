<?php
namespace com\mikebevz\xsd2php;

/**
 * PHP Class representation for HV
 *
 * @version 0.0.1
 *
 */
class PHPPropertyHv extends Common {

  static public function factory(PHPSaveFilesDefault $parent, \DOMDocument $dom, \DOMElement $property) {
    $xPath = new \DOMXPath($dom);
    $phpProperty = new static($parent);

    $phpProperty->name = $property->getAttribute('name');
    $phpProperty->phpName = static::phpIdentifier($phpProperty->name);
    $phpProperty->ucPhpName = ucfirst($phpProperty->phpName);
    $phpProperty->docBlock = new PHPDocBlock();

    $docs = $xPath->query('docs/doc', $property);
    foreach ($docs as $doc) {
      $phpProperty->docBlock->{$doc->getAttribute('name')} = $doc->nodeValue;
    }

    if ($property->getAttribute('name') != '') {
      $phpProperty->docBlock->xmlName = $property->getAttribute('name');
    }

    if ($property->getAttribute('xmlType') != '') {
      $phpProperty->docBlock->xmlType = $property->getAttribute('xmlType');
    }

    if ($property->getAttribute('namespace') != '') {
      $phpProperty->docBlock->xmlNamespace = $phpProperty->parent->expandNS($property->getAttribute('namespace'));
    }

    if ($property->getAttribute('minOccurs') != '') {
      $phpProperty->minOccurs = (int) ($property->getAttribute('minOccurs'));
      $phpProperty->docBlock->xmlMinOccurs = $phpProperty->minOccurs;
    }

    if ($property->getAttribute('maxOccurs') != '') {
      $maxOccurs = $property->getAttribute('maxOccurs');
      if (is_numeric($maxOccurs)) {
        $phpProperty->maxOccurs = (int) $maxOccurs;
        $phpProperty->docBlock->xmlMaxOccurs = $phpProperty->maxOccurs;
        $phpProperty->isArray = ($phpProperty->maxOccurs != 1);
      }
      elseif ($maxOccurs == 'unbounded') {
        $phpProperty->maxOccurs = 0;
        $phpProperty->docBlock->xmlMaxOccurs = $maxOccurs;
        $phpProperty->isArray = true;
      }
    }

    if ($property->getAttribute('type') != '' && $property->getAttribute('typeNamespace') == '') {
      $ns = '';
      // In general it's strange to give to Type name's namespace. Reconsider this part
      if ($property->getAttribute('namespace') != '' && $property->getAttribute('namespace') != $phpProperty->parent->xsd2php->xsdNs) {
        if ($property->getAttribute('namespace') == "#default#") {
          $ns = $phpProperty->parent->namespaceToPhp($phpProperty->parent->xsd2php->targetNamespace);
        } else {
          $ns = $phpProperty->parent->namespaceToPhp($phpProperty->parent->expandNS($property->getAttribute('namespace')));
        }
        $phpProperty->docBlock->var = $ns . '\\' . $property->getAttribute('type');
      } else {
        $phpProperty->docBlock->var = $property->getAttribute('type');
      }

      if ($property->getAttribute('typeNamespace') == $phpProperty->parent->xsd2php->xsdNs) {
        $phpProperty->docBlock->var = $phpProperty->parent->normalizeType($property->getAttribute('type'));
      } else {
        $phpProperty->docBlock->var = $ns . '\\' . $property->getAttribute('type');
      }

      // Is it an array?
      if ($phpProperty->isArray) {
        $maxOccurs = $phpProperty->maxOccurs ? $phpProperty->maxOccurs : '';
        $phpProperty->docBlock->{'var'} = $phpProperty->docBlock->{'var'} . "[$maxOccurs]";
      }
    }

    return $phpProperty;
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
   * Raw property name
   *
   * @var string
   */
  protected $name;

  /**
   * PHP property name
   *
   * @var string
   */
  protected $phpName;

  /**
   * PHP property name uppercased first letter
   *
   * @var string
   */
  protected $ucPhpName;

  /**
   * The type of this property
   *
   * @var boolean
   */
  protected $type;

  /**
   * Whether this property is a simpleType
   *
   * @var boolean
   */
  protected $simpleType;

  /**
   * Tag whether this property is an array
   *
   * @var boolean
   */
  protected $isArray = false;

  /**
   * The minimum number of items in this property
   *
   * @var integer
   */
  protected $minOccurs = 1;

  /**
   * The maximum number of items in this property,
   * if the value is zero then it's unbounded.
   *
   * @var integer
   */
  protected $maxOccurs = 1;

  /**
   * PHP Doc Block for this property
   *
   * @var object PHPDocBlock
   */
  protected $docBlock;

  /**
   * Parent save-files object
   * @var object PHPSaveFilesDefault
   */
  protected $parent;

  protected function __construct(PHPSaveFilesDefault $parent) {
    $this->parent = $parent;
  }

  public function __get($var) {
    return property_exists($this, $var) ? $this->$var : NULL;
  }

  /**
   * Buffer property declaration with indent specified
   *
   * @param object $buffer The output buffer to use
   * @param array $indent Indentation in tabs
   *
   */
  public function declaration(OutputBuffer $buffer, $indent = "\t") {
    $buffer->line('');
    $buffer->lines($this->docBlock->getDocBlock($indent));
    $buffer->line("{$indent}protected \${$this->phpName}");
  }

  /**
   * Buffer property Getter function
   *
   * @param object $buffer The output buffer to use
   * @param string $indent Indentation
   *
   */
  public function getter(OutputBuffer $buffer, $indent = "\t") {
    $buffer->line('');

    $buffer->lines(array(
      "public function get{$this->ucPhpName}() {",
      "{$indent}return \$this->{$this->phpName};",
      '}',
    ), $indent);
  }

  /**
   * Buffer property Getter function
   *
   * @param object $buffer The output buffer to use
   * @param string $indent Indentation
   *
   */
  public function setter(OutputBuffer $buffer, $indent = "\t") {
    $buffer->line($indent);

    $buffer->lines(array(
      "protected function set{$this->ucPhpName}(\${$this->phpName}) {",
      "{$indent}\$this->{$this->phpName} = \$this->validate(\${$this->phpName});",
      '}',
      '',
    ), $indent);
  }
}
