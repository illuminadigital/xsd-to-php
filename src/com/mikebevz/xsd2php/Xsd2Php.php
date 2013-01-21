<?php
namespace com\mikebevz\xsd2php;

/**
 * Copyright 2010 Mike Bevz <myb@mikebevz.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Generate PHP classes based on XSD schema
 *
 * @author Mike Bevz <myb@mikebevz.com>
 * @version 0.0.1
 *
 */
class Xsd2Php extends Common
{
  /**
   * XSD schema to convert from
   * @var String
   */
  protected $xsdFile;

  /**
   *
   * @var DOMXPath
   */
  protected $xpath;

  /**
   * Namespaces in the current xsd schema
   * @var array
   */
  protected $nspace;

  /**
   * XML file suitable for PHP code generation
   * @var string
   */
  protected $xmlForPhp;

  /**
   * The binding value
   *
   * @var string
   */
  public $binding = 'default';

  /**
   * The class that performs the final conversion
   *
   * @var interface iPHPSaveFiles
   */
  public $saveFiles;

  /**
   * Show debug info
   * @var boolean
   */
  public $debug = false;

  /**
   * Namespaces = array (className => namespace ), used in dirs/files generation
   * @var array
   */
  //protected $namespaces;

  /**
   * Short namespaces
   *
   * @var array
   */
  protected $shortNamespaces;

  /**
   * XML Source
   *
   * @var string
   */
  protected $xmlSource;

  /**
   * Target namespace
   *
   * @var string
   */
  protected $targetNamespace;

  /**
   * XSD root namespace alias (fx, xsd = http://www.w3.org/2001/XMLSchema)
   *
   * @var string
   */
  protected $xsdNs;

  /**
   * Already processed imports
   *
   * @var array
   */
  protected $loadedImportFiles = array();

  /**
   * Processed namespaces
   *
   * @var array
  */
  protected $importHeadNS = array();


  /**
   * XML Schema converted to XML
   *
   * @return string $xmlSource
  */
  public function getXmlSource() {
    return $this->xmlSource;
  }

  /**
   * Set XML representation of the XML Schema
   *
   * @param string $xmlSource XML Source
   *
   * @return void
   */
  public function setXmlSource($xmlSource) {
    $this->xmlSource = $xmlSource;
  }

  /**
   * Convert XSD to XML suitable for PHP code generation
   *
   * @return string
   */
  public function getXmlForPhp() {
    return $this->xmlForPhp;
  }

  /**
   * @param string $xmlForPhp XML
   *
   * @return void
   */
  public function setXmlForPhp($xmlForPhp) {
    $this->xmlForPhp = $xmlForPhp;
  }

  /**
   *
   *
   * @param string  $xsdFile Xsd file to convert
   * @param boolean $debug   Show debug messages[optional, default = false]
   *
   * @return void
   */
  public function __construct($xsdFile, $binding, array $options = array()) {
    $this->xsdFile = $xsdFile;

    foreach ($options as $opt => $value) {
      if (property_exists($this, $opt)) {
        $this->$opt = $value;
      }
    }

    $this->dom = new \DOMDocument();
    $this->dom->load($this->xsdFile,
      LIBXML_DTDLOAD |
      LIBXML_DTDATTR |
      LIBXML_NOENT |
      LIBXML_XINCLUDE
    );

    $this->xpath = new \DOMXPath($this->dom);
    $this->targetNamespace = $this->getTargetNS($this->xpath);
    $this->shortNamespaces = $this->getNamespaces($this->xpath);
    $options['shortNamespaces'] = $this->shortNamespaces;

    $this->dom = $xsd = $this->loadIncludes($this->dom, dirname($this->xsdFile), $this->targetNamespace);
    $this->dom = $this->loadImports($this->dom, $this->xsdFile);

    $this->saveFiles = new $binding($this, $options);
    $this->debugln($this->shortNamespaces);
  }

  public function __get($var) {
    $val = NULL;
    switch ($var) {
      case 'binding':
      case 'xsd2php':
      case 'basicTypes':
      case 'targetNamespace':
      case 'shortNamespaces':
      case 'reservedWords':
      case 'xsdNs':
        $val = $this->$var;
        break;
    }
    return $val;
  }

  /**
   * Return target namespace for given DOMXPath object
   *
   * @param DOMXPath $xpath DOMXPath Object
   *
   * @return string
   */
  protected function getTargetNS($xpath) {
    $query   = "//*[local-name()='schema' and namespace-uri()='http://www.w3.org/2001/XMLSchema']/@targetNamespace";
    $targetNs =  $xpath->query($query);

    if ($targetNs) {
      foreach ($targetNs as $entry) {
        return $entry->nodeValue;
      }
    }
  }

  /**
   * Return array of namespaces of the document
   *
   * @param DOMXPath $xpath
   *
   * @return array
   */
  public function getNamespaces($xpath) {
    $query   = "//namespace::*";
    $entries =  $xpath->query($query);
    $nspaces = array();

    foreach ($entries as $entry) {
      if ($entry->nodeValue == "http://www.w3.org/2001/XMLSchema") {
        $this->xsdNs = $this->namespacePrefix($entry->nodeName);
      }

      if (!in_array($entry->nodeName, array('xmlns:xml', 'xmlns:this')))  {
        $this->addNamespace($entry->nodeName, $entry->nodeValue, $nspaces);
      }
    }

    return $nspaces;
  }

  protected function addNamespace($prefix, $namespace, &$nspaces) {
    $nspaces[$this->namespacePrefix($prefix)] = $namespace;
  }

  protected function namespacePrefix($prefix) {
    list($def, $pref, ) = explode(':', "$prefix:");
    return empty($pref) ? ($def=='xmlns'?'xs':$def) : $pref;
  }

  /**
   * Load imports
   *
   * @param DOMDocument $dom     DOM model of the schema
   * @param string      $xsdFile Full path to first XSD Schema
   *
   * @return void
   */
  public function loadImports($dom, $xsdFile = '') {
    $xpath = new \DOMXPath($dom);
    $query = "//*[local-name()='import' and namespace-uri()='http://www.w3.org/2001/XMLSchema']";
    $entries = $xpath->query($query);
    if ($entries->length == 0) {
      return $dom;
    }
    foreach ($entries as $entry) {
	  $schemaFileParts = explode('/', str_replace('\\', '/', $entry->getAttribute("schemaLocation")));
	  $schemaFile = array_pop($schemaFileParts);
      if (strpos($entry->getAttribute("schemaLocation"), '://') !== FALSE)
      {
	    // copy or download the imported schema to tmpfile
	    $tmpname = tempnam('/tmp', 'schema');
	
	    $tmp = fopen($tmpname, 'w');
	    fwrite($tmp, file_get_contents($schemaFile, FILE_USE_INCLUDE_PATH));
	    fclose($tmp);
        $xsdFileName = realpath($tmpname);
      }
      else
      {
       $tmpname = FALSE;
       $location = $entry->getAttribute("schemaLocation");
       if (substr($location, 0, 1) == '/') {
       	$xsdFileName = $location;
       } else {
       	$xsdFileName = ( empty($xsdFile) ? '.' : dirname($xsdFile) ) . DIRECTORY_SEPARATOR . $location;
       }
       $xsdFileName = realpath($xsdFileName);
      }

      // load XSD file
      $namespace = $entry->getAttribute('namespace');
      $parent = $entry->parentNode;
      $xsd = new \DOMDocument();

      $this->debugln("Importing: '$schemaFile'", __METHOD__);

      if (!$this->file_exists($xsdFileName)) {
        $this->println("Error: '$schemaFile' ($xsdFileName) not found.");
        $parent->removeChild($entry); // Avoid loops if the file is not found
        continue;
      }
      if (in_array($schemaFile, $this->loadedImportFiles)) {
        $this->debugln("Schema '$schemaFile' has been already imported", __METHOD__);
        $parent->removeChild($entry);
        continue;
      }
      $filepath = dirname($xsdFileName);
      $result = $xsd->load($xsdFileName,
        LIBXML_DTDLOAD|LIBXML_DTDATTR|LIBXML_NOENT|LIBXML_XINCLUDE);
      if ($result) {
        $mxpath = new \DOMXPath($xsd);
        $this->shortNamespaces = array_merge($this->shortNamespaces, $this->getNamespaces($mxpath));

        $xsd = $this->loadIncludes($xsd, $filepath, $namespace,
          pathinfo($entry->getAttribute("schemaLocation"), PATHINFO_DIRNAME) . '/');

        $this->loadedImportFiles[] = $schemaFile;
        $this->loadedImportFiles = array_unique($this->loadedImportFiles);
      }
      
      $namespaces = $this->getDocNamespaces($xsd);
      
      foreach ($xsd->documentElement->childNodes as $node) {

        if ($node->nodeName == $this->xsdNs.":import" || $node->nodeName == 'import') {
          // Do not change Namespace for import and include tags
          #$this->debugln("Insert Import {$node->nodeName} NS=" . $node->getAttribute('namespace'), __METHOD__);

          $loc = realpath($filepath.DIRECTORY_SEPARATOR.$node->getAttribute('schemaLocation'));
          $node->setAttribute('schemaLocation', $loc);
          #$this->debugln("Change imported schema location to '$loc'", __METHOD__);
          $newNode = $dom->importNode($node, true);
          $parent->insertBefore($newNode, $entry);

          continue;
        } else {
          #$this->debugln("'{$node->nodeName}' => $namespace", __METHOD__);
          $newNodeNs = $xsd->createAttribute("namespace");
          $textEl = $xsd->createTextNode($namespace);
          $newNodeNs->appendChild($textEl);
          $node->appendChild($newNodeNs);

          if ( ! empty($namespaces) ) {
          	foreach ($namespaces as $prefix => $url) {
          		$newNodeNs = $xsd->createAttribute("xmlns:" . $prefix);
          		$textEl = $xsd->createTextNode($url);
          		$newNodeNs->appendChild($textEl);
          		$node->appendChild($newNodeNs);
          	}
          }
          
          $newNode = $dom->importNode($node, true);
          $parent->insertBefore($newNode, $entry);
        }
      }
      // add to $dom
      $parent->removeChild($entry);

      // Delete the schema tempfile
      if ($tmpname)
      {
      	unlink($tmpname);
      }
    }

    $xpath = new \DOMXPath($dom);
    $query = "//*[local-name()='import' and namespace-uri()='http://www.w3.org/2001/XMLSchema']";
    $imports = $xpath->query($query);
    if ($imports->length != 0) {
      $dom = $this->loadImports($dom, $xsdFileName);
    }
    return $dom;
  }

  /**
   * Load includes in XML Schema
   *
   * @param DOMDocument $dom       Instance of DOMDocument
   * @param string      $filepath
   * @param string	  $namespace
   *
   * @return void
   */
  public function loadIncludes($dom, $filepath = '', $namespace = '', $urlpath = '') {
    $xpath = new \DOMXPath($dom);
    $query = "//*[local-name()='include' and namespace-uri()='http://www.w3.org/2001/XMLSchema']";
    $includes = $xpath->query($query);

    foreach ($includes as $entry) {
    	$schemaFileParts = explode('/', str_replace('\\', '/', $entry->getAttribute("schemaLocation")));
    	$schemaFile = array_pop($schemaFileParts);
    	if (strpos($entry->getAttribute("schemaLocation"), '://') !== FALSE)
    	{
    		// copy or download the imported schema to tmpfile
    		$tmpname = tempnam('/tmp', 'schema');
    	
    		$tmp = fopen($tmpname, 'w');
    		fwrite($tmp, file_get_contents($urlpath . $schemaFile));
    		fclose($tmp);
    		$xsdFileName = realpath($tmpname);
    	}
    	else
    	{
    		$tmpname = FALSE;
    		$location = $entry->getAttribute("schemaLocation");
    		if (substr($location, 0, 1) == '/') {
    			$xsdFileName = $location;
    		} else {
    			$xsdFileName = ( empty($xsdFile) ? '.' : dirname($xsdFile) ) . DIRECTORY_SEPARATOR . $location;
    		}
    		$xsdFileName = realpath($xsdFileName);
    	}
    	 
      $parent = $entry->parentNode;
      $xsd = new \DOMDocument();

      $this->debugln("Including '$xsdFileName'", __METHOD__);

      if (!$this->file_exists($xsdFileName)) {
        $this->debugln("'$xsdFileName' not found", __METHOD__);
        continue;
      }

      $result = $xsd->load($xsdFileName,
        LIBXML_DTDLOAD|LIBXML_DTDATTR|LIBXML_NOENT|LIBXML_XINCLUDE);
      if ($result) {
        $mxpath = new \DOMXPath($xsd);
        $this->shortNamespaces = array_merge($this->shortNamespaces, $this->getNamespaces($mxpath));

      }
      
      $namespaces = $this->getDocNamespaces($xsd);

      foreach ($xsd->documentElement->childNodes as $node) {
        if ($node->nodeName == "{$this->xsdNs}:include") {
          $loc = realpath($filepath.DIRECTORY_SEPARATOR.$node->getAttribute('schemaLocation'));
          $node->setAttribute('schemaLocation', $loc);
          $this->debugln("Change included schema location to '$loc'", __METHOD__);
          $newNode = $dom->importNode($node, true);
          $parent->insertBefore($newNode, $entry);
        } else {

          if ($namespace != '') {
            $newNodeNs = $xsd->createAttribute("namespace");
            $textEl = $xsd->createTextNode($namespace);
            $newNodeNs->appendChild($textEl);
            $node->appendChild($newNodeNs);
          }
          
          if ( ! empty($namespaces) ) {
          	foreach ($namespaces as $prefix => $url) {
          		$newNodeNs = $xsd->createAttribute("xmlns:" . $prefix);
          		$textEl = $xsd->createTextNode($url);
          		$newNodeNs->appendChild($textEl);
          		$node->appendChild($newNodeNs);
          	}
          }

          $newNode = $dom->importNode($node, true);
          $parent->insertBefore($newNode, $entry);
        }
      }
      $parent->removeChild($entry);

      if ($tmpname) {
	      unlink($tmpname);
      }
    }

    $xpath = new \DOMXPath($dom);
    $query = "//*[local-name()='include' and namespace-uri()='http://www.w3.org/2001/XMLSchema']";
    $includes = $xpath->query($query);
    if ($includes->length != 0) {
      $dom = $this->loadIncludes($dom);
    }

    return $dom;
  }

  // =========== Support functions ============

  /**
   * Save generated classes to directory
   *
   * @param string  $destination  Directory to save classes to
   *
   * @return void
   */
  public function saveClasses() {
    $this->setXmlSource($this->getXML()->saveXML());

    $phpfile = $this->getXmlForPhp();
    if ($phpfile == '' && $this->getXmlSource() == '') {
      throw new \RuntimeException('There is no XML generated');
    }

    $dom = new \DOMDocument();
    if ($this->getXmlSource() != '') {
      $dom->loadXML($this->getXmlSource(), LIBXML_DTDLOAD | LIBXML_DTDATTR |
        LIBXML_NOENT | LIBXML_XINCLUDE);
    } else {
      $dom->load($phpfile, LIBXML_DTDLOAD | LIBXML_DTDATTR |
        LIBXML_NOENT | LIBXML_XINCLUDE);
    }

    $this->saveFiles->savePhpFiles($dom);
  }

  /**
   * Convert XSD to XML suitable for further processing
   *
   * @return string XML string
   *
   * @return DOMDocument
   */
  public function getXML() {
    try {
      $xslDom = new \DOMDocument();
      $xsl    = new \XSLTProcessor();
      $xslFile = '/xsd2php.' . strtolower($this->binding) . '.xsl';
      $xslDom->load(dirname(__FILE__) . $xslFile);
      $xsl->registerPHPFunctions();
      $xsl->importStyleSheet($xslDom);
      $dom = $xsl->transformToDoc($this->dom);
      $dom->formatOutput = true;

      $fname = implode('.', array_slice(explode('.', basename($this->xsdFile)), 0, -1));
      file_put_contents(dirname(__FILE__) . sprintf('/tmp/%s.xml', $fname), $dom->saveXML() . "\n");

      return $dom;
    }
    catch (\Exception $e) {
      throw new \Exception(sprintf('Error interpreting XSD document (%s)', $e->getMessage()));
    }
  }

  // =========== Support functions ============

  protected function realpath($file) {
    if ($new = realpath($file)) {
      return $new;
    }
    foreach (explode(PATH_SEPARATOR, get_include_path()) as $path) {
      if ($new = realpath("$path/$file")) {
        return $new;
      }
    }
    return '';
  }

  protected function file_exists($file) {
    if (is_file($file)) return TRUE;
    foreach (explode(PATH_SEPARATOR, get_include_path()) as $path) {
      if (is_file("$path/$file")) return TRUE;
    }
    return FALSE;
  }

  public function println($msg = '') {
    print "$msg\n";
  }

  public function debugln($msg, $prefix = '') {
    if (!$this->debug) {
      return;
    }
    if (!is_string($msg)) {
      $msg = print_r($msg, TRUE);
    }
    print ($prefix?"$prefix => ":'') . "$msg\n";
  }


}