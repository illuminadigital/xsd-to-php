<?php
namespace com\mikebevz\xsd2php;

/**
 *
 */

class PHPSaveFilesDefault extends Common implements \com\mikebevz\xsd2php\iPHPSaveFiles {

  protected $xsd2php;
  protected $dest = '';
  protected $nocreate = FALSE;

  public function __construct(Xsd2Php $xsd2php, array $options  = array()) {
    $this->xsd2php = $xsd2php;

    foreach ($options as $property => $value) {
      if (property_exists($this, $property)) {
        $this->$property = $value;
      }
    }
  }

  public function __get($var) {
    return property_exists($this, $var) ? $this->$var : NULL;
  }

  /**
   * Save PHP files to directory structure
   *
   * @param string  $dir             Directory to save files to
   * @param boolean $createDirectory Create $dir directory if it doesn't exist
   *
   * @throws RuntimeException if given directory does not exist
   *
   * @return void
   */
  public function savePhpFiles(\DOMDocument $xmlSource) {
    if (!fileexists($this->dest)) {
      if (!$this->nocreate) {
        throw new \RuntimeException(sprintf("'%s' does not exist", $this->dest));
      }
      else {
        //@todo Implement Recursive mkdir
        mkdir($this->dest, 0777, true);
      }
    }

    $classes = $this->getPHP($xmlSource);

    foreach ($classes as $fullkey => $value) {
      $keys = explode("|", $fullkey);
      $key = $keys[0];
      $namespace = $this->namespaceToPath($keys[1]);
      $targetDir = $this->dest . DIRECTORY_SEPARATOR . $namespace;
      if (!fileexists($targetDir)) {
        mkdir($targetDir, 0777, true);
      }
      file_put_contents($targetDir . DIRECTORY_SEPARATOR . $key . '.php', $value);
    }
    $this->xsd2php->debugln("Generated classes saved to '{$this->dest}'", __METHOD__);
  }

  /**
   * Return generated PHP source code. That's where we generate bindings code
   *
   * @return string
   */
  protected function getPHP(\DOMDocument $dom) {
    $sourceCode = array();

    $xPath = new \DOMXPath($dom);
    $classes = $xPath->query('//classes/class');

    foreach ($classes as $class) {

      $phpClass = new PHPClass();
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
        if (!in_array($class->getElementsByTagName('extends')->item(0)->getAttribute('name'), $this->xsd2php->basicTypes)) {
          $phpClass->extends = $class->getElementsByTagName('extends')->item(0)->getAttribute('name');
          $phpClass->type    = $class->getElementsByTagName('extends')->item(0)->getAttribute('name');
          $phpClass->extendsNamespace = $this->namespaceToPhp($class->getElementsByTagName('extends')->item(0)->getAttribute('namespace'));
        }
      }

      $docs = $xPath->query('docs/doc', $class);
      $docBlock = array();
      //if ($phpClass->namespace != $this->xsd2php->xsdNs) {
      $docBlock['xmlNamespace'] = $this->expandNS($phpClass->namespace);
      //}
      $docBlock['xmlType']      = $phpClass->type;
      $docBlock['xmlName']      = $phpClass->name;
      if ($phpClass->namespace != '') {
        $docBlock['var'] = $this->namespaceToPhp($this->expandNS($phpClass->namespace))."\\".$phpClass->name;
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
          $properties[$i]["docs"]['xmlNamespace'] = $this->expandNS($prop->getAttribute('namespace'));
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
          /**
           * In general it's stange to give to Type name's namespace. Reconsider this part
           */
          if ($prop->getAttribute('namespace') != '' && $prop->getAttribute('namespace') != $this->xsd2php->xsdNs) {
            $ns = "";
            if ($prop->getAttribute('namespace') == "#default#") {
              $ns = $this->namespaceToPhp($this->xsd2php->targetNamespace);
            } else {
              $ns = $this->namespaceToPhp($this->expandNS($prop->getAttribute('namespace')));
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
            $ns = $this->namespaceToPhp($this->xsd2php->targetNamespace);
          } else {
            $ns = $this->namespaceToPhp($this->expandNS($prop->getAttribute('typeNamespace')));
          }

          if ($prop->getAttribute('typeNamespace') == $this->xsd2php->xsdNs) {
            $properties[$i]["docs"]['var'] = $this->normalizeType($prop->getAttribute('type'));
          } else {
            $properties[$i]["docs"]['var'] = $ns.'\\'.$prop->getAttribute('type');
          }
        }

        $i++;
      }

      $phpClass->classProperties = $properties;
      $namespaceClause = '';
      if ($docBlock['xmlNamespace'] != '') {
        $namespaceClause           = "namespace ".$this->namespaceToPhp($docBlock['xmlNamespace']).";\n";
      }
      $sourceCode[$docBlock['xmlName']."|".$phpClass->namespace] = "<?php\n".
        $namespaceClause.
        $phpClass->getPhpCode();
    }
    return $sourceCode;
  }

  /**
   * Resolve short namespace
   * @param string $ns Short namespace
   *
   * @return string
   */
  protected function expandNS($ns) {
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

    /**
     if (preg_match('/http:\/\//', $ns)) {
     $ns = preg_replace('/http:\/\//', '', $ns);
     $ns = preg_replace('/\//','\\', $ns);
     $ns = preg_replace('/\./', '\\',$ns);
     }*/

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
  protected function namespaceToPath($xmlNS) {
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

