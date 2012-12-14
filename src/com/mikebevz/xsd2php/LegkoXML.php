<?php
namespace com\mikebevz\xsd2php;

class LegkoXML {

    protected $version = "0.0.4";

    /**
     * @var Xsd2Php
     */
    protected $xsd2php;

    protected $php2wsdl;

    protected $debug = FALSE;

    protected $nocreate = FALSE;

    public function __construct(array $opts = array()) {
      foreach ($opts as $opt=> $value) {
        if (property_exists($this, $opt)) {
          $this->$opt = $value;
        }
      }
    }

    public function compileSchema($schema, $destination) {
      $this->xsd2php = new Xsd2Php($schema, $this->debug);
      $this->xsd2php->saveClasses($destination, !$this->nocreate);
    }

    public function generateWsdl($phpClass) {}

    /**
     * @return the $version
     */
    public function getVersion() {
        return $this->version;
    }
}