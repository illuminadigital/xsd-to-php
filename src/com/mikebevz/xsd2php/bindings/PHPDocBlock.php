<?php
namespace com\mikebevz\xsd2php;

/**
 * PHP Doc Block representation
 *
 * @version 0.0.1
 *
 */
class PHPDocBlock {

  protected $docs = array();

  public function __get($var) {
    if (array_key_exists($var, $this->docs)) {
      return $this->docs[$var];
    }
    elseif ($var=='docs') {
      return $this->docs;
    }
  }

  public function __set($var, $val) {
    // Remove redundant white space
    $this->docs[$var] = trim(preg_replace('/\s+/', ' ', $val));
  }

  /**
   * Builds a complete docBlock returning it as a string array
   *
   * @param string $indent
   * @return array string
   */
  public function getDocBlock($indent = '') {
    $output = array("{$indent}/**");
    foreach ($this->docs as $key => $value) {
      $output[] = "{$indent} * @{$key}\t{$value}";
    }
    $output[] = "{$indent} */";
    return $output;
  }

}
