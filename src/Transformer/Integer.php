<?php
namespace Swango\HttpServer\Transformer;

/**
 * 用于检测每项含义都相同的数组
 */
class Integer extends \Swango\HttpServer\Transformer {
    public function transform(&$value) {
        if (! isset($value))
            return;
        if ($value instanceof \Swango\Model\AbstractModel) {
            if (method_exists($value, '__toString')) {
                $temp = (string)$value;
                if (is_numeric($temp)) {
                    $value = (int)$temp;
                    return;
                }
            }
            throw new Exception\CannotCovertModelToIntException($value::$model_name);
        }
        if (! is_numeric($value))
            throw new Exception\CannotCovertToIntException(var_export($value, true));
        $value = (int)$value;
    }
}