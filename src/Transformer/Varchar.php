<?php
namespace Swango\HttpServer\Transformer;
class Varchar extends \Swango\HttpServer\Transformer {
    public function transform(&$value) {
        if (! isset($value))
            return;
        if ($value instanceof \Swango\Model\AbstractModel) {
            if (method_exists($value, '__toString')) {
                $value = (string)$value;
                return;
            }
            throw new Exception\CannotCovertModelToStringException($value::$model_name);
        }
        $value = (string)$value;
    }
}