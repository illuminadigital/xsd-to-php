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
        'url' => $phpClass->xmlNamespace,
        'prefix' => $phpClass->parent->shrinkNS($phpClass->xmlNamespace),
      ),
    );

    $namespaceInfo = '';
    foreach ($namespaces as $namespace) {
      $names = array();
      foreach ($namespace as $k => $v) {
        $names[] = "$k=\"$v\"";
      }
      $namespaceInfo[] = "@XmlNamespace(" . implode(', ', $names) . ')';
    }

    $phpClass->docBlock->XmlNamespaces = $namespaceInfo;

    if ($phpClass->dummyProperty!='true') {
      $phpClass->docBlock->XmlEntity = static::docBlockPropertyModifiers($modifiers);
    }
    else {
      $phpClass->docBlock->XmlRootEntity = static::docBlockPropertyModifiers($modifiers);
    }
  }

  static public function docBlockProperty(\DOMDocument $dom, \DOMElement $property, PHPPropertyHv $phpProperty) {
    $modifiers = array(
      'type' => $phpProperty->nameSpacedType(),
    );
    if ($phpProperty->isArray) {
      $modifiers['collection'] = 'true';
    }

    if ($phpProperty->dummyProperty=='true' && strpos($modifiers['type'], '\\')===FALSE) {
      #println($phpProperty->type, 'XmlValue: ' . $modifiers['type']);
      $modifiers['name'] = $phpProperty->myClass->name;
      $phpProperty->docBlock->XmlValue = static::docBlockPropertyModifiers($modifiers);
    }
    elseif ($phpProperty->simpleType) {
      #println($phpProperty->type, 'XmlText: ' . $modifiers['type']);
      $modifiers['name'] = $phpProperty->name;
      // Can use any type but must be normalized if not namespaced
      if (strpos($modifiers['type'], '\\') === FALSE) {
	    $modifiers['type'] = $phpProperty->phpType;
      } 
      $phpProperty->docBlock->XmlText = static::docBlockPropertyModifiers($modifiers);
    }
    elseif (@$phpProperty->xmlType == 'attribute') {
      #println($phpProperty->type, 'XmlAttribute: ' . $modifiers['type']);
      $modifiers['name'] = $phpProperty->name;
      $modifiers['type'] = $phpProperty->phpType; // Must use the simple PHP Type but that was done earlier
      $phpProperty->docBlock->XmlAttribute = static::docBlockPropertyModifiers($modifiers);
    }
    else {
      #println($phpProperty->type, 'XmlElement: ' . $modifiers['type']);
      $modifiers['name'] = $phpProperty->name;
      // Can use any type but must be normalized if not namespaced
      if (strpos($modifiers['type'], '\\') === FALSE) {
	    $modifiers['type'] = $phpProperty->phpType;
      } 
      $phpProperty->docBlock->XmlElement = static::docBlockPropertyModifiers($modifiers);
    }
  }

  static protected function docBlockPropertyModifiers($modifiers) {
    if (empty($modifiers)) {
      return NULL;
    }
    $modes = array();
    foreach ($modifiers as $k => $v) {
      $mods[] = "$k=\"$v\"";
    }
    return '(' . implode(', ', $mods) . ')';
  }
}
