<?php
namespace com\mikebevz\xsd2php;

/**
 * PHP Class representation for HV
 *
 * @version 0.0.1
 *
 */
class PHPPropertyHv extends PHPCommonHv {

  static public function factory(PHPClassHv $myClass, \DOMDocument $dom, \DOMElement $property) {
    $xPath = new \DOMXPath($dom);
    $phpProperty = new static($myClass->parent, $myClass);

    $phpProperty->name = $property->getAttribute('name');
    $phpProperty->phpName = static::phpIdentifier($phpProperty->name);
    $phpProperty->ucPhpName = ucfirst($phpProperty->phpName);
    $phpProperty->varName = "\${$phpProperty->phpName}";

    $phpProperty->type = $property->getAttribute('type');
    $phpProperty->phpType = $phpProperty->parent->normalizeType($phpProperty->type);

    $phpProperty->namespace = $phpProperty->parent->expandNS($property->getAttribute('namespace'));
    $phpProperty->typeNamespace = $phpProperty->parent->expandNS($property->getAttribute('typeNamespace'));

    $docs = $xPath->query('docs/doc', $property);
    foreach ($docs as $doc) {
      $phpProperty->info->{$doc->getAttribute('name')} = $doc->nodeValue;
    }

    $phpProperty->enumeration = \com\mikebevz\xsd2php\PHPEnumeration::factory($dom, $property, $phpProperty);

    $restrictions = $xPath->query('restrictions/restriction', $property);
    foreach ($restrictions as $restriction) {
      $phpProperty->restrictions[] = \com\mikebevz\xsd2php\PHPRestrict::factory($dom, $property, $restriction);
    }

    if ($property->getAttribute('name') != '') {
      $phpProperty->info->xmlName = $property->getAttribute('name');
    }

    if ($property->getAttribute('xmlType') != '') {
      $phpProperty->info->xmlType = $property->getAttribute('xmlType');
    }

    if ($property->getAttribute('namespace') != '') {
      $phpProperty->info->xmlNamespace = $phpProperty->namespace;
    }

    if ($property->getAttribute('minOccurs') != '') {
      $phpProperty->minOccurs = (int) ($property->getAttribute('minOccurs'));
      $phpProperty->info->xmlMinOccurs = $phpProperty->minOccurs;
    }

    if ($property->getAttribute('maxOccurs') != '') {
      $maxOccurs = $property->getAttribute('maxOccurs');
      if (is_numeric($maxOccurs)) {
        $phpProperty->maxOccurs = (int) $maxOccurs;
        $phpProperty->info->xmlMaxOccurs = $phpProperty->maxOccurs;
        $phpProperty->isArray = ($phpProperty->maxOccurs != 1);
      }
      elseif ($maxOccurs == 'unbounded') {
        $phpProperty->maxOccurs = 0;
        $phpProperty->info->xmlMaxOccurs = $maxOccurs;
        $phpProperty->isArray = true;
      }
    }

    if ($phpProperty->type) {
      $ns = '';
      if (empty($phpProperty->typeNamespace)) {
        if (empty($phpProperty->namespace)) {
          $phpProperty->info->var = $phpProperty->type;
        }
        else {
          if ($phpProperty->namespace != $phpProperty->parent->xsd2php->xsdNs) {
            if ($phpProperty->namespace == '#default#') {
              $ns = $phpProperty->parent->namespaceToPhp($phpProperty->parent->xsd2php->targetNamespace);
            } else {
              $ns = $phpProperty->parent->namespaceToPhp($phpProperty->namespace);
            }
            $phpProperty->info->var = $ns . '\\' . $phpProperty->type;
          }
          else {
            $phpProperty->info->var = $phpProperty->type;
          }
        }
      }
      else {
        if ($phpProperty->typeNamespace == $phpProperty->parent->xsd2php->xsdNs) {
          $phpProperty->info->var = $phpProperty->type;
        } else {
          $ns = $phpProperty->parent->namespaceToPhp($phpProperty->typeNamespace);
          $phpProperty->info->var = $ns . '\\' . $phpProperty->type;
        }
      }

      // Is it an array?
      if ($phpProperty->isArray) {
        $maxOccurs = $phpProperty->maxOccurs ? $phpProperty->maxOccurs : '';
        $phpProperty->info->var = $phpProperty->info->var . "[$maxOccurs]";
      }
    }

    $phpProperty->dummyProperty = $myClass->dummyProperty;

    OXMGen::docBlockProperty($dom, $property, $phpProperty);

    return $phpProperty;
  }

  static public function buildConstructor(array $properties, OutputBuffer $buffer, $indent = "\t") {
    $indent2 = "$indent\t";
    $buffer->line('');

    $params = array();
    foreach ($properties as $property) {
      $params[] = $property->buildParam(TRUE, TRUE);
    }
    $buffer->line("{$indent}public function __construct(" . implode(', ', $params) . ') {');
    foreach ($properties as $property) {
      $buffer->line("{$indent2}\$this->{$property->phpName} = " . $property->buildValidateCall(TRUE) . ';');
    }
    $buffer->line("{$indent}}");
  }

  /**
   * The PHP name as a var
   *
   * @var string
   */
  protected $varName;

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
  protected $phpType;

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
   * An enumeration object
   *
   * @var object PHPEnumeration
   */
  protected $enumeration;

  /**
   * Array of restriction objects
   *
   * @var object PHPRestrict
   */
  protected $restrictions = array();

  /**
   * Parent class object
   * @var object PHPClassHv
   */
  protected $myClass;

  protected function __construct(PHPSaveFilesDefault $parent, PHPClassHv $myClass) {
    parent::__construct($parent);
    $this->myClass = $myClass;
  }

  /**
   * Buffer property enumeration with indent specified
   *
   * @param object $buffer The output buffer to use
   * @param array $indent Indentation in tabs
   *
   */
  public function enumeration(OutputBuffer $buffer, $indent = "\t") {
    $this->enumeration->declaration($buffer, $indent);
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
    $this->sendDocBlock($buffer, $indent);
    $buffer->line("{$indent}protected \${$this->phpName};");
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
    $buffer->line('');


    $buffer->lines(array(
      "public function set{$this->ucPhpName}(" . $this->buildParam() . ") {",
      "{$indent}\$this->{$this->phpName} = " . $this->buildValidateCall() . ';',
      '}',
    ), $indent);
  }

  protected function buildValidateCall($incNull = FALSE) {
    $nullCheck = '';
    if ($incNull) {
      $nullCheck = "({$this->varName}===NULL) ? NULL : ";
    }
    return "{$nullCheck}\$this->validate{$this->ucPhpName}({$this->varName})";
  }

  protected function buildParam($incArray = TRUE, $incNull = FALSE) {
    $typeHint = $this->buildTypeHint($incArray);
    return "{$typeHint}{$this->varName}" . ($incNull?' = NULL':'');
  }

  /**
   * Buffer property Getter function
   *
   * @param object $buffer The output buffer to use
   * @param string $indent Indentation
   *
   */
  public function validator(OutputBuffer $buffer, $indent = "\t") {
    $indent2 = "$indent\t";
    $indent3 = "$indent\t\t";
    $typeHint = $this->buildTypeHint();

    $buffer->line('');
    $buffer->line("{$indent}protected function validate{$this->ucPhpName}({$typeHint}{$this->varName}) {");

    // Array bounds check:
    if ($this->maxOccurs != 1) {
      $buffer->lines($this->buildBoundsCheck($indent2));
    }
    else {
      // Build the validation for simple php types
      $buffer->lines($this->buildPHPTypeCheck($this->type, $indent2));
    }

    $this->enumeration->buildValidator($buffer, $indent2);

    foreach ($this->restrictions as $restriction) {
      $restriction->buildValidator($buffer, $this, $indent2);
    }

    $buffer->lines(array(
      '',
      "{$indent}return {$this->varName};",
      '}',
    ), $indent);
  }

  protected function buildTypeHint($incArray = TRUE) {
    $typeHint = '';

    if ($incArray && $this->maxOccurs!=1) {
      $typeHint = 'array';
    }
    else if (!empty($this->parent->classMap[$this->type])) {
      $typeHint = $this->parent->classMap[$this->type];
      $this->myClass->addUsed($this->type);
    }

    return $typeHint ? "$typeHint " : '';
  }

  protected function buildPHPTypeCheck($type, $indent, $varName = NULL) {
    $indent2 = "$indent\t";
    if (!$varName) {
      $varName = $this->varName;
    }
    // Build the validation for simple php types
    if (empty($this->parent->classMap[$type])) {
      return array(
        "{$indent}if (is_{$this->phpType}({$varName})) {",
        "{$indent2}throw new \\Exception(sprintf('Supplied %s value was not %s', '{$this->phpName}', '{$this->phpType}'));",
        "{$indent}}",
      );
    }
    return array();
  }

  protected function buildObjectTypeCheck($type, $indent, $varName = NULL) {
    $indent2 = "$indent\t";
    if (!$varName) {
      $varName = $this->varName;
    }
    // Build the validation for simple php types
    if (!empty($this->parent->classMap[$type])) {
      return array(
        "{$indent}if ({$varName} typeof {$this->parent->classMap[$type]}) {",
        "{$indent2}throw new \\Exception(sprintf('Supplied %s value was not %s', '{$this->phpName}', '{$this->phpType}'));",
        "{$indent}}",
      );
    }
    return array();
  }

  protected function buildBoundsCheck($indent) {
    $indent2 = "$indent\t";

    // Check minimum
    $output = array(
      "{$indent}\$count = count($this->varName);",
      "{$indent}if (\$count < {$this->minOccurs}) {",
      "{$indent2}throw new \\Exception(sprintf('Supplied %s array has less than the required number (%d) of entries.', '{$this->phpName}', {$this->minOccurs}));",
      "{$indent}}",
    );

    if ($this->maxOccurs>1) {
      // Check maximum (unless unbounded)
      $output = array_merge($output, array(
        "{$indent}if (\$count > {$this->maxOccurs}) {",
        "{$indent2}throw new \\Exception(sprintf('Supplied %s array has more than the required number (%d) of entries.', '{$this->phpName}', {$this->maxOccurs}));",
        "{$indent}}",
      ));
    }

    // Check types of every item
    $typeHint = $this->buildTypeHint(FALSE);
    if ($typeHint) {
      $output[] = "{$indent}foreach ({$this->varName} as \$entry) {";
      $output = array_merge($output, $this->buildObjectTypeCheck($this->type, $indent2, '$entry'));
      $output[] = "{$indent}}";
    }
    else {
      $output[] = "{$indent}foreach ({$this->varName} as \$entry) {";
      $output = array_merge($output, $this->buildPHPTypeCheck($this->type, $indent2, '$entry'));
      $output[] = "{$indent}}";
    }


    return $output;
  }
}
