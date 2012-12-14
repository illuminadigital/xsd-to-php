<?php
namespace com\mikebevz\xsd2php;

/**
 *
 */

interface iPHPSaveFiles {
  public function __construct(Xsd2Php $xsd2php, array $options  = array());
  public function savePhpFiles(\DOMDocument $xmlSource);
}