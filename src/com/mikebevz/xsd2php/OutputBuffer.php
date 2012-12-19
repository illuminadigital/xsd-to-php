<?php
namespace com\mikebevz\xsd2php;

/**
 *
 */
class OutputBuffer {
  protected $buffer = '', $eoln = "\n";

  public function __construct($init ='') {
    $this->buffer = $init;
  }

  public function __toString() {
    return $this->buffer . $this->eoln;
  }

  public function __get($var) {
    $val = NULL;
    switch ($var) {
      case 'strlen':
        $val = strlen($this->buffer);
        break;
    }
    return $val;
  }

  public function reset($str = '') {
    $this->buffer = (string) $str;
  }

  public function prepend($str = '') {
    $this->buffer = (string) $str . $this->buffer;
  }

  public function append($str = '') {
    $this->buffer .= (string) $str;
  }

  public function line($str = '') {
    $this->append($this->eoln . $str);
  }

  public function lines(array $lines = array(), $indent = "") {
    foreach ($lines as $line) {
      $this->line("$indent$line");
    }
  }
}