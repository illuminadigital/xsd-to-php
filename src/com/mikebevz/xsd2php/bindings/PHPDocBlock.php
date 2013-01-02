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
    if (is_array($val)) {
      $this->docs[$var] = $val;
    }
    else {
      // Remove redundant white space
      $this->docs[$var] = trim(preg_replace('/\s+/', ' ', $val));
    }
  }

  /**
   * Builds a complete docBlock returning it as a string array
   *
   * @param string $indent
   * @return array string
   */
  public function getDocBlock($indent = '') {
    if (empty($this->docs)) {
      return array();
    }
    $indent2 = "{$indent}\t";
    $output = array("{$indent}/**");
    foreach ($this->docs as $key => $value) {
      if (is_array($value)) {
        $output[] = "{$indent} * @{$key} ({";
        foreach ($value as $item) {
          $output[] = "{$indent} *{$indent2}{$item}";
        }
        $output[] = "{$indent} * })";
      }
      else {
        $output[] = "{$indent} * @{$key}\t{$value}";
      }
    }
    $output[] = "{$indent} */";
    return $output;
  }

}
