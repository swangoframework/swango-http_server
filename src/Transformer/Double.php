<?php
namespace Swango\HttpServer\Transformer;

/**
 * 用于检测每项含义都相同的数组
 */
class Double extends \Swango\HttpServer\Transformer {
    private $decimal;
    public function __construct($decimal = 2) {
        $this->decimal = $decimal;
    }
    public function transform(&$value) {
        if (! isset($value))
            return;
        if ($value instanceof \Swango\Model\AbstractModel) {
            if (method_exists($value, '__toString')) {
                $temp = (string)$value;
                if (is_numeric($temp)) {
                    $value = (double)sprintf("%.{$this->decimal}f", $temp);
                    return;
                }
            }
            throw new Exception\CannotCovertModelToDoubleException($value::$model_name);
        }
        if (! is_numeric($value))
            throw new Exception\CannotCovertToDoubleException(var_export($value, true));
        $value = (double)sprintf("%.{$this->decimal}f", $value);
    }
}