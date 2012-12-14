<?php
namespace com\mikebevz\xsd2php;

class LegkoXML {

  protected $version = "0.0.4";

  /**
   * @var Xsd2Php
   */
  protected $xsd2php;

  protected $nocreate = FALSE;

  protected $binding = 'default';

  protected $options;

  public function __construct(array $options = array()) {
    $this->options = $options;
    foreach ($options as $opt => $value) {
      if (property_exists($this, $opt)) {
        $this->$opt = $value;
      }
    }
  }

  public function compileSchema($schema) {
    $binding = '\com\mikebevz\xsd2php\PHPSaveFiles' . ucfirst(strtolower($this->binding));
    $this->xsd2php = new Xsd2Php($schema, $binding, $this->options);
    $this->xsd2php->saveClasses();
  }

  public function generateWsdl($phpClass) {
  }

  /**
   * @return the $version
   */
  public function getVersion() {
    return $this->version;
  }
}