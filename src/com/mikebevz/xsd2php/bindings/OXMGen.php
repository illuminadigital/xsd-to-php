<?php
namespace com\mikebevz\xsd2php;

/**
 * OXMGen
 *
 * @version 0.0.1
 *
 */
class OXMGen {

  static public function docBlockClass(\DOMDocument $dom, \DOMElement $class, PHPClassHv $phpClass) {
    $modifiers = array(
      'xml' => $phpClass->xmlName,
    );

    $namespaces = array(
      array(
        'namespace' => $phpClass->xmlNamespace,
        'prefix' => $phpClass->parent->shrinkNS($phpClass->xmlNamespace),
      ),
    );

    $namespaceInfo = '';
    foreach ($namespaces as $namespace) {
      $names = array();
      foreach ($namespace as $k => $v) {
        $names[] = "$k='$v'";
      }
      $namespaceInfo[] = "\n * \t@XmlNamespace(" . implode(', ', $names) . ')';
    }

    $phpClass->docBlock->XmlNamespaces = '({' . implode('', $namespaceInfo) . "\n * })";

    #println($phpClass->info, __METHOD__ . ' (' . __LINE__ . ')');
    if ($phpClass->dummyProperty!='true') {
      $phpClass->docBlock->XmlEntity = static::docBlockPropertyModifiers($modifiers);
    }
    else {
      $phpClass->docBlock->XmlRootEntity = static::docBlockPropertyModifiers($modifiers);
    }
  }

  static public function docBlockProperty(\DOMDocument $dom, \DOMElement $property, PHPPropertyHv $phpProperty) {
    $modifiers = array(
      'type' => $phpProperty->type,
    );
    if ($phpProperty->isArray) {
      $modifiers['collection'] = 'true';
    }

    if ($phpProperty->dummyProperty=='true') {
      $modifiers['xml-name'] = $phpProperty->myClass->name;
      $phpProperty->docBlock->XmlField = static::docBlockPropertyModifiers($modifiers);
    }
    else {
      $modifiers['xml-name'] = $phpProperty->name;
      $phpProperty->docBlock->XmlElement = static::docBlockPropertyModifiers($modifiers);
    }
  }

  static protected function docBlockPropertyModifiers($modifiers) {
    if (empty($modifiers)) {
      return NULL;
    }
    $modes = array();
    foreach ($modifiers as $k => $v) {
      $mods[] = "$k='$v'";
    }
    return '(' . implode(', ', $mods) . ')';
  }
}
