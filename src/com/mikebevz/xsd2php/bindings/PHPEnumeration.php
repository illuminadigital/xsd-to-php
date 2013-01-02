<?php
namespace com\mikebevz\xsd2php;

/**
 * PHP Enumeration object
 *
 * @version 0.0.1
 *
 */
class PHPEnumeration {

  static public function factory(\DOMDocument $dom, \DOMElement $property, PHPPropertyHv $parent) {
    $xPath = new \DOMXPath($dom);
    $phpEnumeration = new static($parent);

    $enumerations = $xPath->query('restrictions/enumeration', $property);
    foreach ($enumerations as $enumeration) {
      $phpEnumeration->items[] = \com\mikebevz\xsd2php\PHPEnumerationItem::factory($dom, $property, $enumeration);
    }
    $phpEnumeration->items = array_filter($phpEnumeration->items);

    return $phpEnumeration;
  }

  protected $items = array();

  protected $parent;

  protected function __construct(PHPPropertyHv $parent) {
    $this->parent = $parent;
  }

  public function __get($var) {
    return property_exists($this, $var) ? $this->$var : NULL;
  }

  public function declaration(OutputBuffer $buffer, $indent = '') {
    if (empty($this->items)) {
      return;
    }
    $enum = array();
    foreach ($this->items as $item) {
      $value = addslashes($item->value);
      $content = addslashes($item->content);
      $enum[] = "'{$value}' => '{$content}'";
    }
    $enums = implode(', ', $enum);

    $buffer->line("{$indent}static protected \$enum{$this->parent->ucPhpName} = array({$enums});");
  }

  public function buildValidator(OutputBuffer $buffer, $indent = '') {
    if (empty($this->items)) {
      return;
    }

    $indent2 = "{$indent}\t";
    $buffer->line();

    $buffer->lines(array(
      "{$indent}if (empty(\$enum{$this->parent->ucPhpName}[{$this->parent->varName}])) {",
      "{$indent2}throw new \\Exception(sprintf('Supplied %s value (%s) was not a valid enumerated value.', '{$this->parent->myClass->name}', {$this->parent->varName}));",
      "{$indent}}",
    ));
  }

}
