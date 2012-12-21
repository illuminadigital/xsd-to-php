<?php
namespace com\mikebevz\xsd2php;

/**
 * PHP Common code for HV
 *
 * @version 0.0.1
 *
 */
class PHPCommonHv {

  /**
   * Make any changes to turn a supplied identifier into a valid PHP one.
   *
   * @param string $raw
   * @return string valid PHP identifier derived from $raw
   */
  static protected function phpIdentifier($raw, $instance = TRUE) {
    $raw = str_replace(array('-', '/', '\\'), '_', $raw);
    $raw = array_map('ucfirst', array_filter(explode('_', $raw)));
    if ($instance) {
      $s = $raw[0];
      if (count($raw)>1) {
        $s = strtolower($s);
      }
      else {
        $c = strtolower(substr($s, 0, 1));
        $s = $c . substr($s, 1);
      }
      $raw[0] = $s;
    }

    return implode('', $raw);
  }

  /**
   * Raw property name
   *
   * @var string
   */
  protected $name;

  /**
   * PHP property name
   *
   * @var string
   */
  protected $phpName;

  /**
   * PHP property name uppercased first letter
   *
   * @var string
   */
  protected $ucPhpName;

  /**
   * Class namespace
   * @var string
   */
  protected $namespace;

  /**
   * Class type namespace
   * @var string
   */
  protected $typeNamespace;

  /**
   * Parent save-files object
   * @var object PHPSaveFilesDefault
   */
  protected $parent;

  /**
   * Array of information about this object
   * used for various purposes such as the
   * docBlock and deciding where to save the
   * class - but I'm using a docBlock 'cos
   * it's easy.
   *
   * @var object PHPDocBlock
   */
  protected $info = array();

  /**
   * PHP Doc Block for this object
   *
   * @var object PHPDocBlock
   */
  protected $docBlock;

  protected function __construct(PHPSaveFilesDefault $parent) {
    $this->parent = $parent;
    $this->info = new PHPDocBlock();
    $this->docBlock = new PHPDocBlock();
  }

  public function __get($var) {
    return property_exists($this, $var) ? $this->$var : $this->info->$var;
  }

  public function __set($var, $val) {
    if (!property_exists($this, $var)) {
      $this->info->$var = $val;
    }
  }

  /**
   * Send Class Doc block to output buffer
   *
   * @param $docBlock instance of PHPDocBlock class
   *
   * return string
   */
  protected function sendDocBlock(OutputBuffer $buffer, $indent = '') {
    $buffer->lines($this->docBlock->getDocBlock($indent));
  }

}
