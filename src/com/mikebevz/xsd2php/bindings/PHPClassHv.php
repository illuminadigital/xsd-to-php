<?php
namespace com\mikebevz\xsd2php;

/**
 * PHP Class representation for HV
 *
 * @version 0.0.1
 *
 */
class PHPClassHv extends PHPCommonHv {

  static public function factory(PHPSaveFilesDefault $parent, \DOMDocument $dom, \DOMElement $class) {
    $xPath = new \DOMXPath($dom);
    $phpClass = new static($parent);

    $phpClass->name = $class->getAttribute('name');
    $phpClass->phpName = static::phpIdentifier($phpClass->name, FALSE);
    $phpClass->ucPhpName = ucfirst($phpClass->phpName);

    if ($class->getAttribute('type') != '') {
      $phpClass->type = $class->getAttribute('type');
      $phpClass->simpleType = false;
    }

    if ($class->getAttribute('simpleType') != '') {
      $phpClass->type = $class->getAttribute('simpleType');
      $phpClass->simpleType = true;
    }
    if ($class->getAttribute('namespace') != '') {
      $phpClass->namespace = $class->getAttribute('namespace');
    }
    else {
      $phpClass->namespace = '#default#';
    }

    if ($class->getAttribute('id') != '') {
    	$phpClass->constants[] = array('name' => 'ID', 'value' => "'" . $class->getAttribute('id') . "'"); 
    }
    
    if ($class->getAttribute('refName') != '') {
    	$phpClass->constants[] = array('name' => 'NAME', 'value' => "'" . $class->getAttribute('refName') . "'"); 
    }
    
    if ($extension = $class->getAttribute('extends')) {
      if (!$phpClass->parent->isBasicType($extension) && strpos($extension, ':') !== FALSE) {
        list($ns, $extension) = explode(':', $extension);
 #   println($extension, $ns);
 #       if (!$extension) {
 #         $extension = $ns;
 #         $ns = '';
 #       }
        $phpClass->extends = $phpClass->type = $extension;
        if ($ns == 'this' && $phpClass->namespace != '#default#') {
        	$phpClass->extendsNamespace = $phpClass->parent->namespaceToPhp($phpClass->namespace);
        }
        else {
        	$phpClass->extendsNamespace = $phpClass->parent->namespaceToPhp($ns);
        }
      }
    }
    elseif ($class->getElementsByTagName('extends')->length > 0) {
      $name = trim($class->getElementsByTagName('extends')->item(0)->getAttribute('name'));
      if (!$phpClass->parent->isBasicType($name)) {
        $phpClass->extends = $phpClass->type = $name;
        $phpClass->extendsNamespace = $phpClass->parent->namespaceToPhp($class->getElementsByTagName('extends')->item(0)->getAttribute('namespace'));
      } else {
        // Basic types don't actually extend from anything so we treat it as a value property.
        
        $propertyNamespace = $class->getElementsByTagName('extends')->item(0)->getAttribute('namespace');
        // Build a fake document fragment and use this to create the missing property  
        $propertyDoc = new \DOMDocument('1.0');
        //$propertyDoc->registerNamespace('', $propertyNamespace);
        $propertyEl = $propertyDoc->createElement('property');

        $propertyAttrs = array(
            'xmlns' => $propertyNamespace,
            'xmlType' => 'value',
            'name' => 'value',
            'type' => $name,
            'minOccurs' => 1,
            'maxOccurs' => 1,
            'typeNamespace' => $propertyNamespace,
            'namespace' => $propertyNamespace,
        );
        
        foreach ($propertyAttrs as $attrName => $attrValue)
        {
            $attrNode = $propertyDoc->createAttribute($attrName);
            $attrNode->value = $attrValue;

            $propertyEl->appendChild($attrNode);
        }
        
        $propertyDoc->appendChild($propertyEl); 

        $phpClass->classProperties['value'] = PHPPropertyHv::factory($phpClass, $propertyDoc, $propertyEl);
      }
    }

    $phpClass->info->dummyProperty = $class->getAttribute('dummyProperty');

    $phpClass->info->xmlNamespace = $phpClass->parent->expandNS($phpClass->namespace);
    $phpClass->info->xmlType      = $phpClass->type;
    $phpClass->info->xmlName      = $phpClass->name;
    if ($phpClass->namespace != '') {
      $phpClass->info->var = $phpClass->parent->namespaceToPhp($phpClass->parent->expandNS($phpClass->namespace))."\\".$phpClass->name;
    } else {
      $phpClass->info->var = $phpClass->name;
    }

    $docs = $xPath->query('docs/doc', $class);
    $text  = array();
    foreach ($docs as $doc) {
      $field = ucfirst(strtolower($doc->getAttribute('name')));
      $info = '';
      if ($doc->nodeValue != '') {
        $info = $doc->nodeValue;
      } elseif ($doc->getAttribute('value') != '') {
        $info = $doc->getAttribute('value');
      }
      $info = trim(preg_replace('/\s+/', ' ', $info), '\' ');
      if ($info) {
        $text[$field] = $info;
      }
    }
    $phpClass->textInfo = $text;

    // Fetch all the properties for this
    $properties = $xPath->query('property', $class);
    foreach($properties as $property) {
      $phpProperty = \com\mikebevz\xsd2php\PHPPropertyHv::factory($phpClass, $dom, $property);
      $phpClass->classProperties[$phpProperty->phpName] = $phpProperty;
    }

    OXMGen::docBlockClass($dom, $class, $phpClass);

    return $phpClass;
  }

  /**
   * Class type
   *
   * @var string
   */
  protected $type;

  /**
   * Class simple type
   *
   * @var boolean
   */
  protected $simpleType;

  /**
   * Class properties
   *
   * @var array
   */
  protected $classProperties = array();

  /**
   * Class to extend
   * @var string
   */
  protected $extends;

  /**
   * Namespace of parent class
   * @var string
   */
  protected $extendsNamespace;

  /**
   * All the classes used by this class
   * @var array of string
   */
  protected $usedMap = array();

  /**
   * An array of informational strings
   * @var array of string
   */
  protected $textInfo = array();

  /**
   * The output buffer
   * @var object OutputBuffer
   */
  protected $buffer;
  
  /**
   * An array of constants
   * 
   * @var array of arrays
   */
  protected $constants = array();

  protected function __construct(PHPSaveFilesDefault $parent) {
    parent::__construct($parent);
    $this->buffer = new OutputBuffer();
  }

  public function getProperty($phpName) {
    return !empty($this->classProperties[$phpName]) ? $this->classProperties[$phpName] : NULL;
  }

  public function __toString() {
    $sourceCode = (string) $this->getPhpCode();

    $namespaceClause = $this->namespaceClause();

    // Now collect the namespaces to be used...
    $uses = array();
    foreach ($this->usedMap as $type) {
      //$uses[] = $this->parent->phpClasses[$type]->useClause();
    }

    return "$namespaceClause\n" . implode("\n", array_filter(array_map('trim', $uses))) . "\n$sourceCode";
  }

  public function addUsed($type) {
    $this->usedMap[$type] = $type;
  }

  protected function namespaceClause() {
    $namespaceClause = '';
    if ($this->info->xmlNamespace != '') {
      $namespace = $this->parent->namespaceToPhp($this->info->xmlNamespace);
      $namespace = str_replace('.', '\\', $namespace);
      $namespaceClause = "namespace {$namespace};";
    }
    return "<?php\n{$namespaceClause}\n";
  }

  protected function useClause() {
    $useClause = '';
    if ($this->xmlNamespace != '') {
      $useClause = $this->buildUseClause($this->xmlNamespace, $this->phpName);
    }
    return $useClause;
  }

  protected function buildUseClause($ns, $name) {
    $use = $this->parent->namespaceToPhp($ns);
    $use = str_replace('.', '\\', $use);
    
    if ( empty($use) )
    {
    	return '';
    }
    // else
    return "use {$use}\\{$name};";
  }

  public function nameSpacedType() {
    $ns = $this->parent->namespaceToPhp($this->xmlNamespace);
    $ns = str_replace('.', '\\', $ns);
    return "{$ns}\\{$this->phpName}";
  }

  /**
   * Returns a PHP class
   *
   * @return string
   */
  protected function getPhpCode() {
    // Build the class identification line
    $define = "class {$this->phpName}";
    if ($this->extends != '') {
      $extension = static::phpIdentifier($this->extends, FALSE);
      if ($this->extendsNamespace != '') {
      	$path = $this->parent->namespaceToPhp($this->extendsNamespace);
        $define .= " extends \\{$path}\\{$extension}";
      } else {
        $define .= " extends {$extension}";
      }
      //$this->buffer->line($this->buildUseClause($this->extendsNamespace, $extension));
    }

    // Send the class docBlock
    $this->sendDocBlock($this->buffer);
    $this->buffer->line("{$define} {");

    // Output the annotation content for this class...
    $this->buffer->line("\t/**");
    $this->buffer->lines($this->textInfo, "\t * ");
    $this->buffer->line("\t */");
    
    // Handle the constants
    if ( ! empty($this->constants)) {
    	$this->buffer->line();

	    foreach ($this->constants as $constant) {
	    	$this->buffer->line("\tconst {$constant['name']} = {$constant['value']};");
	    }
    }

    // Output all the property enumerations
    foreach ($this->classProperties as $property) {
      $property->enumeration($this->buffer);
    }

    // Output all the property declarations
    foreach ($this->classProperties as $property) {
      $property->declaration($this->buffer);
    }

    PHPPropertyHv::buildConstructor($this->classProperties, $this->buffer);

    // Output all the property getters & setters
    foreach ($this->classProperties as $property) {
      $property->getter($this->buffer);
      $property->creator($this->buffer);
      $property->setter($this->buffer);
      $property->validator($this->buffer);
      if ($property->isArray) {
        $property->adder($this->buffer);
        $property->typeValidator($this->buffer);
      }
    }
    
    if ($this->dummyProperty) {
    	$this->sendToString($this->buffer);
    }

    // And finish up
    $this->buffer->line("} // end class {$this->phpName}");

    return (string) $this->buffer;
  }
  
  protected function sendToString($buffer, $indent = "\t") {
  	$indent2 = $indent . $indent;
  	
  	$buffer->lines(array(
  		'',
  		"{$indent}public function __toString() {",
  		"{$indent2}return (string) \$this->value;",
  		"{$indent}}",	
  	));
  }
}
