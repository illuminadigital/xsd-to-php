<?php
namespace com\mikebevz\xsd2php;

/**
 *
 */

class PHPSaveFilesHv extends \com\mikebevz\xsd2php\PHPSaveFilesDefault {

  /**
   * Return generated PHP source code.
   * Here's where we generate bindings code
   *
   * @return array of string
   */
  protected function getPHP(\DOMDocument $dom) {
    $sourceCode = array();

    $xPath = new \DOMXPath($dom);
    $classes = $xPath->query('//classes/class');

    foreach ($classes as $class) {
      $phpClass = \com\mikebevz\xsd2php\PHPClassHv::factory($this, $dom, $class);
      $docBlock = $phpClass->classDocBlock;

      $namespaceClause = '';
      if ($docBlock->xmlNamespace != '') {
        $namespaceClause = 'namespace ' . $this->namespaceToPhp($docBlock->xmlNamespace) . ';';
      }

      $phpClass->buffer->reset("<?php\n{$namespaceClause}\n");

      $sourceCode["{$docBlock->xmlName}|{$phpClass->namespace}"] = (string) $phpClass;
    }

    return $sourceCode;
  }

  /**
   * Resolve short namespace
   * @param string $ns Short namespace
   *
   * @return string
   */
  public function expandNS($ns) {
    if ($ns == "#default#") {
      $ns = $this->xsd2php->targetNamespace;
    }
    foreach($this->xsd2php->shortNamespaces as $shortNs => $longNs) {
      if ($ns == $shortNs) {
        $ns = $longNs;
      }

    }
    return $ns;
  }

  /**
   * Convert XML URI to PHP complient namespace
   *
   * @param string $xmlNS XML URI
   *
   * @return string
   */
  public function namespaceToPhp($xmlNS) {
    $ns = $xmlNS;
    $ns = $this->expandNS($ns);
    if (preg_match('/urn:/',$ns)) {
      //@todo check if there are any components of namespace which are
      $ns = preg_replace('/-/', '_',$ns);
      $ns = preg_replace('/urn:/', '', $ns);
      $ns = preg_replace('/:/','\\', $ns);
    }

    $matches = array();
    if (preg_match("#((http|https|ftp)://(\S*?\.\S*?))(\s|\;|\)|\]|\[|\{|\}|,|\"|'|:|\<|$|\.\s)#", $ns, $matches)) {
      $elements = explode("/", $matches[3]);
      $domain = $elements[0];
      array_shift($elements);
      //print_r($domain."\n");
      $ns = implode("\\",array_reverse(explode(".", $domain)));
      //$ns = preg_replace('/\./', '\\', );
      //print $ns."\n";
      foreach($elements as $key => $value) {
        if ($value != '') {
          $value = preg_replace('/\./', '_', $value);
          $ns .= "\\" . $value;
        }
      }
    }


    $ns = explode('\\', $ns);
    $i = 0;
    foreach($ns as $elem) {
      if (preg_match('/^([0-9]+)(.*)$/', $elem)) {
        $ns[$i] = "_".$elem;
      }

      if (in_array($elem, $this->xsd2php->reservedWords)) {
        $ns[$i] = "_".$elem;
      }
      $i++;
    }

    $ns = implode('\\', $ns);

    return $ns;
  }

  /**
   * Convert XML URI to Path
   * @param string $xmlNS XML URI
   *
   * @return string
   */
  public function namespaceToPath($xmlNS) {
    $ns = $xmlNS;
    $ns = $this->expandNS($ns);

    if (preg_match('/urn:/', $ns)) {
      $ns = preg_replace('/-/', '_', $ns);
      $ns = preg_replace('/urn:/', '', $ns);
      $ns = preg_replace('/:/', DIRECTORY_SEPARATOR, $ns);
    }


    $matches = array();
    if (preg_match("#((http|https|ftp)://(\S*?\.\S*?))(\s|\;|\)|\]|\[|\{|\}|,|\"|'|:|\<|$|\.\s)#", $ns, $matches)) {
      $elements = explode("/", $matches[3]);
      $domain = $elements[0];
      array_shift($elements);
      //print_r($domain."\n");
      $ns = implode(DIRECTORY_SEPARATOR, array_reverse(explode(".", $domain)));
      //$ns = preg_replace('/\./', '\\', );
      //print $ns."\n";
      foreach($elements as $key => $value) {
        if ($value != '') {
          $value = preg_replace('/[\.|-]/', '_', $value);
          $ns .= DIRECTORY_SEPARATOR . $value;
        }
      }
    }

    $ns = explode(DIRECTORY_SEPARATOR, $ns);
    $i = 0;
    foreach($ns as $elem) {
      if (preg_match('/^([0-9]+)(.*)$/', $elem)) {
        $ns[$i] = "_".$elem;
      }
      if (in_array($elem, $this->xsd2php->reservedWords)) {
        $ns[$i] = "_".$elem;
      }
      $i++;
    }
    $ns = implode(DIRECTORY_SEPARATOR, $ns);
    return $ns;
  }

}

