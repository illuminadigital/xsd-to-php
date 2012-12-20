<?php
namespace com\mikebevz\xsd2php;

/**
 * PHP Restriction object
 *
 * @version 0.0.1
 *
 */
class PHPRestrict {

  static public function factory(\DOMDocument $dom, \DOMElement $property, \DOMElement $restriction) {
    $xPath = new \DOMXPath($dom);
    $phpRestriction = new static($property);

    $phpRestriction->name = $restriction->getAttribute('name');
    $phpRestriction->value = $restriction->getAttribute('value');

    $docs = $xPath->query('docs/doc', $property);
    foreach ($docs as $doc) {
      $phpRestriction->docs[$doc->getAttribute('name')] = $doc->nodeValue;
    }

    return $phpRestriction;
  }

  protected $name;

  protected $value;

  protected $docs = array();

  protected $parent;

  protected function __construct(\DOMElement $parent) {
    $this->parent = $parent;
  }

  public function buildEnumerations(OutputBuffer $buffer, PHPPropertyHv $property) {
    if ($name!='enumeration') {
      return;
    }

  }

  public function buildValidator(OutputBuffer $buffer, PHPPropertyHv $property, $indent = '') {
    $builder = "{$this->name}BuildValidator";
    if (method_exists($this, $builder)) {
      $this->$builder($buffer, $property, $indent);
    }
  }

  public function minInclusiveBuildValidator(OutputBuffer $buffer, PHPPropertyHv $property, $indent = '') {
    $indent2 = "{$indent}\t";
    $buffer->line();

    $buffer->lines(array(
      "{$indent}if ({$property->varName} < {$this->value}) {",
      "{$indent2}throw new \\Exception(sprintf('Supplied %s value was less than the minimum (%d)', '{$property->myClass->name}', {$this->value}));",
      "{$indent}}",
    ));
  }

  public function maxInclusiveBuildValidator(OutputBuffer $buffer, PHPPropertyHv $property, $indent = '') {
    $indent2 = "{$indent}\t";
    $buffer->line();

    $buffer->lines(array(
      "{$indent}if ({$property->varName} > {$this->value}) {",
      "{$indent2}throw new \\Exception(sprintf('Supplied %s value was greater than the maximum (%d)', '{$property->myClass->name}', {$this->value}));",
      "{$indent}}",
    ));
  }

  public function minExclusiveBuildValidator(OutputBuffer $buffer, PHPPropertyHv $property, $indent = '') {
    $indent2 = "{$indent}\t";
    $buffer->line();

    $buffer->lines(array(
      "{$indent}if ({$property->varName} < {$this->value}) {",
      "{$indent2}throw new \\Exception(sprintf('Supplied %s value was less than/equal to the minimum (%d)', '{$property->myClass->name}', {$this->value}));",
      "{$indent}}",
    ));
  }

  public function maxExclusiveBuildValidator(OutputBuffer $buffer, PHPPropertyHv $property, $indent = '') {
    $indent2 = "{$indent}\t";
    $buffer->line();

    $buffer->lines(array(
      "{$indent}if ({$property->varName} > {$this->value}) {",
      "{$indent2}throw new \\Exception(sprintf('Supplied %s value was greater than/equal to the maximum (%d)', '{$property->myClass->name}', {$this->value}));",
      "{$indent}}",
    ));
  }

  public function minLengthBuildValidator(OutputBuffer $buffer, PHPPropertyHv $property, $indent = '') {
    $indent2 = "{$indent}\t";
    $buffer->line();

    $buffer->lines(array(
      "{$indent}if (strlen({$property->varName}) < {$this->value}) {",
      "{$indent2}throw new \\Exception(sprintf('Supplied %s value was shorter than the minimum (%d)', '{$property->myClass->name}', {$this->value}));",
      "{$indent}}",
    ));
  }

  public function maxLengthBuildValidator(OutputBuffer $buffer, PHPPropertyHv $property, $indent = '') {
    $indent2 = "{$indent}\t";
    $buffer->line();

    $buffer->lines(array(
      "{$indent}if (strlen({$property->varName}) > {$this->value}) {",
      "{$indent2}throw new \\Exception(sprintf('Supplied %s value was longer than the maximum (%d)', '{$property->myClass->name}', {$this->value}));",
      "{$indent}}",
    ));
  }

  public function patternBuildValidator(OutputBuffer $buffer, PHPPropertyHv $property, $indent = '') {
    $indent2 = "{$indent}\t";
    $buffer->line();

    $buffer->lines(array(
      "{$indent}if (!preg_match('/^{$this->value}$/', {$property->varName})) {",
      "{$indent2}throw new \\Exception(sprintf('Supplied %s value did not match the right pattern.', '{$property->myClass->name}', {$this->value}));",
      "{$indent}}",
    ));
  }

}
