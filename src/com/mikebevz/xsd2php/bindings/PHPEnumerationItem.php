<?php
namespace com\mikebevz\xsd2php;

/**
 * PHP Enumeration Item object
 *
 * @version 0.0.1
 *
 */
class PHPEnumerationItem {

  static public function factory(\DOMDocument $dom, \DOMElement $property, \DOMElement $enumeration) {
    $xPath = new \DOMXPath($dom);
    $phpEnumerationItem = new static($property, $enumeration);

    $phpEnumerationItem->value = $enumeration->getAttribute('value');

    $text = array();
    $docs = $xPath->query('docs/doc', $enumeration);
    foreach ($docs as $doc) {
      $text[$doc->getAttribute('name')] = trim(preg_replace('/\s\s+/', ' ', $doc->nodeValue));
    }
    $phpEnumerationItem->description = implode(' ', $text);

    return empty($phpEnumerationItem->value) ? NULL : $phpEnumerationItem;
  }

  protected $value;

  protected $description;

  protected $parent;

  protected $enumeration;

  protected function __construct(\DOMElement $parent, \DOMElement $enumeration) {
    $this->parent = $parent;
    $this->enumeration = $enumeration;
  }

  public function __get($var) {
    switch ($var) {
      case 'content':
        $val = empty($this->description) ? $this->value : $this->description;
        break;
      default:
        $val = property_exists($this, $var) ? $this->$var : NULL;
    }
    return $val;
  }

}
